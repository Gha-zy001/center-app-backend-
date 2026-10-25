<?php

namespace Modules\Classroom\app\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Modules\Classroom\app\Models\TeacherBot;
use Modules\Classroom\app\Models\TeacherBotRequest;
use Modules\Teachers\app\Models\Teacher;

/**
 * The manager bot (Telegram "Bot Management Mode") creates a teacher's own bot:
 *
 *  1. The teacher opens the manager bot's start link from the app (it carries a one-time code), which
 *     ties their Telegram account to their teacher account. The manager bot answers with the
 *     "create bot" link holding a suggested name and username.
 *  2. After the teacher confirms in Telegram, Telegram sends a `managed_bot` update naming the creator
 *     and the new bot. The creator's Telegram id finds the request, we fetch the new bot's token,
 *     store it encrypted and point the bot's webhook at us.
 */
class ManagerBotHandler
{
    private const START_CODE = '/^\/start\s+([A-Za-z0-9]{32})$/';

    public function __construct(private readonly TelegramClient $telegram, private readonly ParentLinker $parents) {}

    public function handle(array $update): void
    {
        if (isset($update['managed_bot']) && is_array($update['managed_bot'])) {
            $this->botCreated($update['managed_bot']);

            return;
        }

        $message = $update['message'] ?? null;

        if (! is_array($message) || ($message['chat']['type'] ?? null) !== 'private') {
            return;
        }

        if (preg_match(self::START_CODE, trim((string) ($message['text'] ?? '')))) {
            $this->start($message);

            return;
        }

        // When the manager bot is also the shared parents bot (one bot, one webhook), parents' messages land here.
        if (config('services.telegram.manager_token') === config('services.telegram.token')) {
            $this->parents->handle($update);

            return;
        }

        $this->manager()->sendMessage((string) $message['chat']['id'], 'افتح الرابط من تطبيق المدرس عشان تنشئ بوت أولياء الأمور.');
    }

    private function manager(): TelegramClient
    {
        return $this->telegram->withToken(config('services.telegram.manager_token'));
    }

    private function start(array $message): void
    {
        $chatId = (string) $message['chat']['id'];

        preg_match(self::START_CODE, trim((string) ($message['text'] ?? '')), $match);

        $request = TeacherBotRequest::where('code', $match[1])->where('expires_at', '>', now())->first();
        $teacher = $request ? Teacher::find($request->teacher_id) : null;

        if (! $request || ! $teacher || ! $teacher->isActive()) {
            $this->manager()->sendMessage($chatId, 'الرابط ده منتهي. ارجع للتطبيق واطلب رابط جديد.');

            return;
        }

        $request->update(['telegram_user_id' => (int) ($message['from']['id'] ?? 0)]);

        $link = 'https://t.me/newbot/'.config('services.telegram.manager_username').'/'.$this->suggestedUsername($teacher)
            .'?name='.urlencode('متابعة '.$teacher->name);

        $this->manager()->sendMessage($chatId, "أهلاً {$teacher->name} 👋\nدوس على الزرار وأكمل إنشاء بوت أولياء الأمور الخاص بيك. تقدر تغيّر الاسم واسم المستخدم قبل التأكيد.", [
            'reply_markup' => ['inline_keyboard' => [[['text' => '🤖 إنشاء البوت', 'url' => $link]]]],
        ]);
    }

    private function botCreated(array $managed): void
    {
        $creatorId = (int) ($managed['user']['id'] ?? 0);
        $botId = (int) ($managed['bot']['id'] ?? 0);
        $username = (string) ($managed['bot']['username'] ?? '');

        $request = $creatorId && $botId
            ? TeacherBotRequest::where('telegram_user_id', $creatorId)->where('expires_at', '>', now())->latest('id')->first()
            : null;

        // A bot nobody asked for through the app is none of our business.
        if (! $request || $username === '') {
            return;
        }

        $token = $this->manager()->getManagedBotToken($botId);

        if ($token === null) {
            Log::warning('Managed bot token unavailable', ['teacher' => $request->teacher_id, 'bot' => $botId]);
            $this->manager()->sendMessage((string) $creatorId, 'حصلت مشكلة في ربط البوت. جرّب تاني بعد شوية.');

            return;
        }

        $bot = DB::transaction(function () use ($request, $botId, $username, $token) {
            // Replacing a bot drops the old one, and the parents linked through it.
            TeacherBot::where('teacher_id', $request->teacher_id)->get()->each(function (TeacherBot $old) {
                $this->telegram->withToken($old->token)->deleteWebhook();
                $old->delete();
            });

            return TeacherBot::create([
                'teacher_id' => $request->teacher_id,
                'telegram_bot_id' => $botId,
                'username' => $username,
                'token' => $token,
                'webhook_key' => Str::random(40),
                'webhook_secret' => Str::random(48),
            ]);
        });

        $url = URL::to('/api/v1/telegram/bot/'.$bot->webhook_key, [], true);

        if (! $this->telegram->withToken($token)->setWebhook($url, $bot->webhook_secret)) {
            $bot->delete();
            $this->manager()->sendMessage((string) $creatorId, 'البوت اتعمل بس معرفناش نفعّله. جرّب تاني بعد شوية.');

            return;
        }

        $request->delete();
        $this->manager()->sendMessage((string) $creatorId, "تم تفعيل بوتك ✅\n{$bot->link()}\nابعت الرابط ده لأولياء الأمور عشان يدوسوا Start ويشاركوا أرقامهم.");
    }

    // 5-32 characters, letters, digits and underscores, and it must end in "bot".
    private function suggestedUsername(Teacher $teacher): string
    {
        $base = substr(preg_replace('/[^a-z0-9]/', '', strtolower($teacher->username)), 0, 18) ?: 'teacher';

        return $base.'_parents_bot';
    }
}
