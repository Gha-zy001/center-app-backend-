<?php

use Illuminate\Support\Facades\Broadcast;
use Modules\Chat\app\Models\Participant;

/*
| Private/presence channel for one conversation (MessageSent, Typing).
| Only participants of the conversation may subscribe.
*/
Broadcast::channel('conversation.{conversationId}', function ($user, int $conversationId) {
    $isParticipant = Participant::where('conversation_id', $conversationId)
        ->where('user_id', $user->id)
        ->exists();

    // Presence channels need member info; private channels just need true.
    return $isParticipant ? ['id' => $user->id, 'name' => $user->name] : false;
});
