<?php

namespace Modules\Blog\app\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Blog\app\Models\Blog;
use Modules\Blog\Transformers\BlogResource;

class BlogController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $this->perPage($request);

        $blogs = Blog::with(['category', 'media'])
            ->latest()
            ->paginate($perPage);

        return BlogResource::collection($blogs);
    }

    public function show($slug)
    {
        $blog = Blog::with(['category', 'media'])->where('slug', $slug)->firstOrFail();
        return new BlogResource($blog);
    }
}