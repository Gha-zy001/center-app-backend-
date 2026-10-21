<?php

namespace Modules\Blog\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Blog extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = ['category_id', 'title', 'slug', 'content'];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($blog) {
            $blog->slug = static::generateUniqueSlug($blog->title);
        });

        static::updating(function ($blog) {
            if ($blog->isDirty('title') && !$blog->isDirty('slug')) {
                $blog->slug = static::generateUniqueSlug($blog->title, $blog->id);
            }
        });
    }

    protected static function generateUniqueSlug($title, $id = 0)
    {
        $slug = Str::slug($title);
        $originalSlug = $slug;
        $count = 1;

        while (static::whereSlug($slug)->where('id', '!=', $id)->exists()) {
            $slug = $originalSlug . '-' . $count++;
        }

        return $slug;
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('blogs')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp', 'image/jpg'])
            ->registerMediaConversions(function (?Media $media = null) {
                $this->addMediaConversion('thumb')
                    ->width(200)
                    ->height(200)
                    ->sharpen(10);
            });
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->getFirstMediaUrl('blogs', 'thumb');
    }

    public function category()
    {
        return $this->belongsTo(BlogCategory::class, 'category_id');
    }
}