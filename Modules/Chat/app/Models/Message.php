<?php

namespace Modules\Chat\app\Models;

use Modules\User\app\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\MediaLibrary\HasMedia;
use App\Core\Traits\HasStandardMedia;

class Message extends Model implements HasMedia
{
    use HasFactory, HasStandardMedia;

    protected $fillable = ['conversation_id', 'sender_id', 'body', 'type'];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
