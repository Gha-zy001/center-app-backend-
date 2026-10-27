<?php

namespace Modules\Classroom\app\Services;

use Modules\Classroom\app\Models\Exam;
use Modules\Classroom\app\Models\Group;
use Modules\Classroom\app\Models\Student;
use Modules\Classroom\app\Models\TeacherBot;

/**
 * Messages the parents linked to a student. Must be called inside the teacher's request: the
 * linked accounts are read right away (the teacher scope is closed once the response is out),
 * and only the slow network calls wait until after the response.
 */
class ParentNotifier
{
    private const DAYS = [
        'sat' => 'السبت', 'sun' => 'الأحد', 'mon' => 'الاثنين', 'tue' => 'الثلاثاء',
        'wed' => 'الأربعاء', 'thu' => 'الخميس', 'fri' => 'الجمعة',
    ];

    public function __construct(private readonly TelegramClient $telegram) {}

    public static function scheduleText(Group $group): string
    {
        $days = collect($group->days)->map(fn (string $day) => self::DAYS[$day] ?? $day)->implode(' و');

        return "{$days} الساعة {$group->time}";
    }

    public function present(Student $student, string $date): bool
    {
        return $this->send($student, "✅ تم تسجيل حضور {$student->name} في حصة {$this->groupName($student)} بتاريخ {$date}.");
    }

    public function absent(Student $student, string $date): bool
    {
        return $this->send($student, "❌ {$student->name} غاب عن حصة {$this->groupName($student)} بتاريخ {$date}.");
    }

    public function grade(Student $student, Exam $exam, float $score): bool
    {
        return $this->send($student, "📝 درجة {$student->name} في {$exam->name}: {$score} من {$exam->max_score}.");
    }

    public function schedule(Student $student, Group $group): bool
    {
        return $this->send($student, "📅 مواعيد مجموعة {$group->name} ({$student->name}): ".self::scheduleText($group).'.');
    }

    /**
     * @return bool whether at least one parent account is linked (and so was queued a message)
     */
    private function send(Student $student, string $text): bool
    {
        $contacts = $student->telegramContacts()
            // Parents linked through the old shared bot are only messaged while that bot is still in use.
            ->when(! config('services.telegram.shared_parents_bot'), fn ($query) => $query->whereNotNull('teacher_bot_id'))
            ->get(['chat_id', 'teacher_bot_id']);

        if ($contacts->isEmpty()) {
            return false;
        }

        // Each parent is reached through the bot they linked with. A parent linked on both the teacher's own
        // bot and the shared one is told once, through the teacher's bot (chat_id is the parent's Telegram id).
        $contacts = $contacts->groupBy('chat_id')
            ->map(fn ($same) => $same->whereNotNull('teacher_bot_id')->first() ?? $same->first())
            ->values();
        $bots = TeacherBot::whereIn('id', $contacts->pluck('teacher_bot_id')->filter()->unique())->get()->keyBy('id');
        $deliveries = $contacts->map(fn ($contact) => [
            'chatId' => $contact->chat_id,
            'client' => $contact->teacher_bot_id ? $this->telegram->withToken($bots[$contact->teacher_bot_id]?->token) : $this->telegram,
        ])->all();

        // After the response, so a slow Telegram never slows the teacher's screen.
        defer(function () use ($deliveries, $text) {
            foreach ($deliveries as $delivery) {
                $delivery['client']->sendMessage($delivery['chatId'], $text);
            }
        });

        return true;
    }

    private function groupName(Student $student): string
    {
        return $student->group?->name ?? '';
    }
}
