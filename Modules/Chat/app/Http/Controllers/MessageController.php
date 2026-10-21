<?php

namespace Modules\Chat\app\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Chat\app\Services\ChatService;

use Modules\Chat\app\Transformers\MessageResource;

class MessageController extends Controller
{
    protected ChatService $chatService;

    public function __construct(ChatService $chatService)
    {
        $this->chatService = $chatService;
    }

    public function index(Request $request, $id)
    {
        $userId = auth()->id();
        $messages = $this->chatService->getMessages($id, $userId, $this->perPage($request, 'limit', 20));
        
        return $this->paginated(
            $messages,
            MessageResource::class,
            __('chat.messages_retrieved')
        );
    }

    public function store(Request $request, $id)
    {
        $request->validate([
            'body' => 'required|string',
            'type' => 'nullable|in:text,system'
        ]);

        $userId = auth()->id();
        $message = $this->chatService->sendMessage($id, $userId, $request->body, $request->input('type', 'text'));

        return $this->successResponse(__('chat.message_sent'), new MessageResource($message));
    }

    public function storeMedia(Request $request, $id)
    {
        $request->validate([
            'file' => 'required|file|max:10240',
            'type' => 'required|in:image,file'
        ]);

        $userId = auth()->id();
        $message = $this->chatService->sendMessage($id, $userId, null, $request->type, $request->file('file'));

        return $this->successResponse(__('chat.media_sent'), new MessageResource($message));
    }
}
