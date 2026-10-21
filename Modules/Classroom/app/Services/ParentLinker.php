<?php

namespace Modules\Classroom\app\Services;

use Illuminate\Support\Facades\DB;
use Modules\Classroom\app\Models\Student;
use Modules\Classroom\app\Models\TelegramContact;
use Modules\Classroom\app\Support\PhoneKey;

/**
 * Handles what parents send to the bot. Parent presses Start, shares their own phone number, and
 * every student whose parent number matches is linked to that Telegram account.
 *
 * This runs outside any teacher's request, so it reads across teachers on purpose (and stamps
 * each new row with the student's own teacher).
 */
class ParentLinker
{
    public function __construct(private readonly TelegramClient $telegram) {}

    public function handle(array $update): void
    {
        $message = $update['message'] ?? null;

        if (! is_array($message) || ($message['chat']['type'] ?? null) !== 'private') {
            return;
        }

        $chatId = (string) $message['chat']['id'];

        if (isset($message['contact'])) {
            $this->link($chatId, $message);

            return;
        }

        $this->askForPhone($chatId);
    }

    private function askForPhone(string $chatId): void
    {
        $this->telegram->sendMessage($chatId, "أهلاً بيك 👋\nعشان أتأكد إنك ولي الأمر وأبعتلك غياب ودرجات ابنك، دوس على الزرار تحت وشارك رقمك.", [
            'reply_markup' => [
                'keyboard' => [[['text' => '📱 مشاركة رقمي', 'request_contact' => true]]],
                'resize_keyboard' => true,
                'one_time_keyboard' => true,
            ],
        ]);
    }

    private function link(string $chatId, array $message): void
    {
        $contact = $message['contact'];

        // Only the sender's own number counts: forwarding someone else's contact must not link anything.
        if ((string) ($contact['user_id'] ?? '') !== (string) ($message['from']['id'] ?? '')) {
            $this->telegram->sendMessage($chatId, 'من فضلك شارك رقمك انت بالزرار، مش رقم تاني.');

            return;
        }

        $students = $this->studentsOfParent((string) ($contact['phone_number'] ?? ''));

        if ($students->isEmpty()) {
            $this->telegram->sendMessage($chatId, 'الرقم ده مش مسجل كرقم ولي أمر عندنا. اتواصل مع المدرس وتأكد من الرقم المسجل.', [
                'reply_markup' => ['remove_keyboard' => true],
            ]);

            return;
        }

        $name = trim(($message['from']['first_name'] ?? '').' '.($message['from']['last_name'] ?? '')) ?: 'ولي أمر';

        DB::transaction(function () use ($students, $chatId, $name) {
            foreach ($students as $student) {
                $row = TelegramContact::withoutGlobalScopes()->firstOrNew(['student_id' => $student->id, 'chat_id' => $chatId]);
                $row->forceFill(['teacher_id' => $student->teacher_id, 'name' => $name])->save();
            }
        });

        $lines = $students->map(fn (Student $student) => "• {$student->name} — {$student->group->name}: ".ParentNotifier::scheduleText($student->group));

        $this->telegram->sendMessage($chatId, "تم ربط حسابك ✅\nهتوصلك رسايل الغياب والدرجات والمواعيد لـ:\n".$lines->implode("\n"), [
            'reply_markup' => ['remove_keyboard' => true],
        ]);
    }

    // The parent's number, or the student's own when no parent number was given.
    private function studentsOfParent(string $phone)
    {
        $key = PhoneKey::of($phone);

        if ($key === null) {
            return collect();
        }

        return Student::withoutGlobalScopes()
            ->with(['group' => fn ($query) => $query->withoutGlobalScopes()])
            ->where(function ($query) use ($key) {
                $query->where('parent_phone', 'like', '%'.$key)
                    ->orWhere(fn ($own) => $own->where('parent_phone', '')->where('phone', 'like', '%'.$key));
            })
            ->get()
            // The SQL LIKE is only a shortlist: confirm with the same normalisation.
            ->filter(fn (Student $student) => PhoneKey::of($student->parent_phone ?: $student->phone) === $key)
            ->values();
    }
}
