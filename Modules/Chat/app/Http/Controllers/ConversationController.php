<?php

namespace Modules\Chat\app\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Chat\app\Services\ChatService;

use Modules\Chat\app\Transformers\ConversationResource;

class ConversationController extends Controller
{
    protected ChatService $chatService;

    public function __construct(ChatService $chatService)
    {
        $this->chatService = $chatService;
    }

    public function index(Request $request)
    {
        $userId = auth()->id();
        $conversations = $this->chatService->getConversations($userId, $this->perPage($request, 'limit', 15));
        
        return $this->paginated(
            $conversations,
            ConversationResource::class,
            __('chat.conversations_retrieved')
        );
    }

    public function store(Request $request)
    {
        $userId = auth()->id();

        $request->validate([
            'user_ids' => 'required|array|min:1',
            // Prevent user from starting a conversation solely with themselves
            'user_ids.*' => 'integer|exists:users,id|not_in:' . $userId,
            'type' => 'nullable|in:direct,group',
            'name' => 'nullable|string|max:255'
        ], [
            'user_ids.*.not_in' => __('chat.cannot_create_with_self')
        ]);

        $conversation = $this->chatService->createConversation($userId, $request->user_ids, $request->input('type', 'direct'), $request->name);

        return $this->successResponse(__('chat.conversation_created'), new ConversationResource($conversation));
    }

    public function markAsRead(Request $request, $id)
    {
        $userId = auth()->id();
        $this->chatService->markAsRead($id, $userId);
        return $this->successResponse(__('chat.marked_as_read'));
    }

    public function destroy($id)
    {
        $userId = auth()->id();
        $this->chatService->leaveConversation($id, $userId);
        return $this->successResponse(__('chat.left_conversation'));
    }
}
