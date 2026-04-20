<?php

declare(strict_types=1);

namespace Src\Domain\AiChat\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Src\Domain\Shared\Traits\Uuid;

/**
 * @property-read string $id
 * @property-read string $ai_chat_id
 * @property-read string $role
 * @property-read string|null $search_mode
 * @property-read string $content
 * @property-read int|null $tokens
 * @property-read array|null $metadata
 * @property-read Carbon $created_at
 * @property-read Carbon $updated_at
 *
 * @property-read AiChat $chat
 */
class AiChatMessage extends Model
{
    use HasFactory;
    use Uuid;

    protected $table = 'ai_chat_messages';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'ai_chat_id',
        'role',
        'search_mode',
        'content',
        'tokens',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function chat(): BelongsTo
    {
        return $this->belongsTo(AiChat::class, 'ai_chat_id');
    }
}
