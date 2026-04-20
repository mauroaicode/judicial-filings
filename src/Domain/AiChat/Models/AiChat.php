<?php

declare(strict_types=1);

namespace Src\Domain\AiChat\Models;

use Database\Factories\AiChatFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Src\Domain\AiChat\QueryBuilders\AiChatQueryBuilder;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Models\Process;
use Src\Domain\Shared\Traits\Uuid;

/**
 * @property-read string $id
 * @property-read string $organization_id
 * @property-read string $process_id
 * @property-read string $app_user_id
 * @property-read string $title
 * @property-read bool $is_private
 * @property-read bool $is_active
 * @property-read Carbon $created_at
 * @property-read Carbon $updated_at
 *
 * @method static AiChatQueryBuilder query()
 * @method AiChatQueryBuilder whereOrganization(string $organizationId)
 * @method AiChatQueryBuilder whereProcess(string $processId)
 * @method AiChatQueryBuilder wherePublicOrTransitive(string $appUserId)
 * @method AiChatQueryBuilder orderedByCreatedAt()
 */
class AiChat extends Model
{
    use HasFactory;
    use Uuid;

    protected $table = 'ai_chats';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'organization_id',
        'process_id',
        'app_user_id',
        'title',
        'is_private',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_private' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    protected static function newFactory(): AiChatFactory
    {
        return AiChatFactory::new();
    }

    public function newEloquentBuilder($query): AiChatQueryBuilder
    {
        return new AiChatQueryBuilder($query);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\Src\Domain\Organization\Models\Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\Src\Domain\Process\Models\Process, $this>
     */
    public function process(): BelongsTo
    {
        return $this->belongsTo(Process::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\Src\Domain\AppUser\Models\AppUser, $this>
     */
    public function appUser(): BelongsTo
    {
        return $this->belongsTo(AppUser::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\Src\Domain\AiChat\Models\AiChatMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(AiChatMessage::class, 'ai_chat_id');
    }
}
