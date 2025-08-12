<?php

declare(strict_types=1);

namespace Core\Shared\Infrastructure\Persistence\Eloquent\Models;

use Core\Shared\Infrastructure\Traits\Uuid;
use Database\Factories\ProcessSubjectFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read string $id
 * @property-read string $process_id
 * @property-read int $subject_registration_id
 * @property-read string $subject_type
 * @property-read bool $is_cited
 * @property-read string|null $identification
 * @property-read string $name_or_business_name
 * @property-read Carbon $created_at
 * @property-read Carbon $updated_at
 * @property-read Process $process
 */
class ProcessSubject extends Model
{
    use HasFactory, Uuid;

    protected $table = 'process_subjects';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'process_id',
        'subject_registration_id',
        'subject_type',
        'is_cited',
        'identification',
        'name_or_business_name',
    ];

    protected $casts = [
        'is_cited' => 'boolean',
        'subject_registration_id' => 'integer',
    ];

    protected static function newFactory(): Factory|ProcessSubjectFactory
    {
        return ProcessSubjectFactory::new();
    }

    public function process(): BelongsTo
    {
        return $this->belongsTo(Process::class);
    }
}
