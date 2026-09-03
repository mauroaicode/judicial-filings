<?php

declare(strict_types=1);

namespace Src\Domain\Organization\Models;

use Database\Factories\OrganizationSettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Src\Domain\Shared\Traits\Uuid;

/**
 * Per-organization product/commercial settings (1:1 with {@see Organization}).
 *
 * @property-read string $id
 * @property-read string $organization_id
 * @property-read int|null $max_active_processes
 * @property-read Carbon $created_at
 * @property-read Carbon $updated_at
 */
class OrganizationSetting extends Model
{
    use HasFactory;
    use Uuid;

    protected $table = 'organization_settings';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'max_active_processes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'max_active_processes' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    protected static function newFactory(): OrganizationSettingFactory
    {
        return OrganizationSettingFactory::new();
    }
}
