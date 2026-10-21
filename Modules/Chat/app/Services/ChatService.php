<?php

namespace Modules\Chat\app\Services;

use Modules\Chat\app\Models\Conversation;
use Modules\Chat\app\Models\Message;
use Modules\Chat\app\Models\Participant;
use Modules\Chat\app\Events\MessageSent;
use Modules\Chat\app\Jobs\NotifyChatRecipients;
use Modules\UserNotifications\app\Interfaces\NotificationServiceInterface;

class ChatService
{
    protected NotificationServiceInterface $notificationService;

    public function __construct(NotificationServiceInterface $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    public function getConversations(int $userId, int $limit)
    {
        return Conversation::whereHas('participants', function ($query) use ($userId) {
            $query->where('user_id', $userId);
        })
        ->with(['participants.user', 'messages' => function($query) {
            $query->latest()->limit(1);
        }])
        ->latest('updated_at')
        ->paginate($limit);
    }

    public function createConversation(int $creatorId, array $participantIds, string $type = 'direct', ?string $name = null)
    {
        $participantIds = collect($participantIds)->push($creatorId)->unique()->values()->all();
        $isGroup = $type === 'group' || count($participantIds) > 2;

        if (!$isGroup && count($participantIds) === 2) {
            $existing = Conversation::where('type', 'direct')
                ->whereHas('participants', function($q) use ($participantIds) {
                    $q->whereIn('user_id', $participantIds);
                }, '=', 2)
                ->first();

            if ($existing) {
                return $existing->load('participants.user');
            }
        }

        $conversation = Conversation::create([
            'type' => $isGroup ? 'group' : 'direct',
            'name' => $isGroup ? $name : null,
        ]);

        foreach ($participantIds as $pid) {
            Participant::create([
                'conversation_id' => $conversation->id,
                'user_id' => $pid,
                'last_read_at' => $pid == $creatorId ? now() : null,
            ]);
        }

        return $conversation->load('participants.user');
    }

    public function markAsRead(int $conversationId, int $userId)
    {
        $participant = Participant::where('conversation_id', $conversationId)
            ->where('user_id', $userId)
            ->firstOrFail();
            
        $participant->update(['last_read_at' => now()]);
    }

    public function leaveConversation(int $conversationId, int $userId)
    {
        $conversation = Conversation::findOrFail($conversationId);
        $participant = Participant::where('conversation_id', $conversationId)
            ->where('user_id', $userId)
            ->firstOrFail();

        $participant->delete();

        if ($conversation->participants()->count() === 0) {
            $conversation->delete();
        }
    }

    public function getMessages(int $conversationId, int $userId, int $limit)
    {
        Participant::where('conversation_id', $conversationId)->where('user_id', $userId)->firstOrFail();

        return Message::where('conversation_id', $conversationId)
            ->with(['sender', 'media'])
            ->latest()
            ->paginate($limit);
    }

    public function sendMessage(int $conversationId, int $senderId, ?string $body, string $type = 'text', $mediaFile = null)
    {
        $conversation = Conversation::findOrFail($conversationId);
        Participant::where('conversation_id', $conversationId)->where('user_id', $senderId)->firstOrFail();

        $message = Message::create([
            'conversation_id' => $conversationId,
            'sender_id' => $senderId,
            'body' => $body,
            'type' => $type,
        ]);

        if ($mediaFile) {
            $message->addMedia($mediaFile)->toMediaCollection('chat_media');
        }

        $conversation->touch();
        
        $message->load('sender', 'media');

        broadcast(new MessageSent($message))->toOthers();

        NotifyChatRecipients::dispatch(
            $conversationId,
            $senderId,
            __('chat.new_message'),
            $type === 'text' ? substr((string) $body, 0, 100) : __('chat.attachment_received'),
        );

        return $message;
    }
}
