<?php

namespace App\Http\Controllers\Api;

use App\Core\Contracts\MediaServiceInterface;
use App\Core\Resources\MediaResource;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Validation\Rule;
use Modules\Blog\app\Models\Blog;
use Modules\Chat\app\Models\Message;
use Modules\User\app\Models\User;

class MediaController extends Controller
{
    /**
     * Public alias => model class. Only these models can receive uploads; the client can
     * never name an arbitrary PHP class.
     */
    private const MODEL_TYPES = [
        'user' => User::class,
        'blog' => Blog::class,
        'message' => Message::class,
    ];

    /**
     * File types accepted by the generic upload endpoint (no scripts, HTML or SVG).
     */
    private const ALLOWED_MIMES = 'jpeg,jpg,png,gif,webp,pdf,doc,docx,xls,xlsx,csv,txt,zip,mp4,mp3';

    protected MediaServiceInterface $mediaService;

    public function __construct(MediaServiceInterface $mediaService)
    {
        $this->mediaService = $mediaService;
    }

    public function store(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'max:10240', 'mimes:' . self::ALLOWED_MIMES],
            'model_type' => ['required', 'string', Rule::in(array_keys(self::MODEL_TYPES))],
            'model_id' => ['required', 'integer'],
            'collection' => ['nullable', 'string', 'alpha_dash', 'max:50'],
        ]);

        $model = self::MODEL_TYPES[$request->model_type]::findOrFail($request->model_id);

        $media = $this->mediaService->upload($model, $request->file('file'), $request->collection ?? 'default');

        return $this->successResponse(__('messages.media_uploaded'), new MediaResource($media));
    }

    public function show(int $id)
    {
        $media = $this->mediaService->find($id);
        return $this->successResponse(__('messages.media_fetched'), new MediaResource($media));
    }

    public function destroy(int $id)
    {
        $this->mediaService->delete($id);
        return $this->successResponse(__('messages.media_deleted'));
    }

    public function download(int $id)
    {
        $media = $this->mediaService->find($id);
        return response()->download($media->getPath(), $media->file_name);
    }
}
