<?php

declare(strict_types=1);

namespace Src\Domain\Process\Models;

use Database\Factories\ProcessSubjectFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Src\Domain\Process\QueryBuilders\ProcessSubjectQueryBuilder;
use Src\Domain\Shared\Traits\Uuid;

/**
 * @property-read string $id
 * @property-read int $subject_registration_id
 * @property-read string $subject_type
 * @property-read bool $is_cited
 * @property-read string|null $identification
 * @property-read string $name_or_business_name
 * @property-read Carbon $created_at
 * @property-read Carbon $updated_at
 *
 * @method static ProcessSubjectQueryBuilder query()
 * @method ProcessSubjectQueryBuilder whereProcess(string $processId)
 * @method ProcessSubjectQueryBuilder whereSubjectRegistrationId(int $subjectRegistrationId)
 * @method ProcessSubjectQueryBuilder whereProcessAndRegistrationId(string $processId, int $subjectRegistrationId)
 * @method ProcessSubjectQueryBuilder whereSubjectType(string $subjectType)
 * @method ProcessSubjectQueryBuilder whereCited()
 * @method ProcessSubjectQueryBuilder orderedBySubjectType()
 */
class ProcessSubject extends Model
{
    use HasFactory;
    use Uuid;

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'subject_registration_id',
        'subject_type',
        'is_cited',
        'identification',
        'name_or_business_name',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_cited' => 'boolean',
            'subject_registration_id' => 'integer',
        ];
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): ProcessSubjectFactory
    {
        return ProcessSubjectFactory::new();
    }

    /**
     * @param  Builder  $query
     */
    public function newEloquentBuilder(mixed $query): ProcessSubjectQueryBuilder
    {
        return new ProcessSubjectQueryBuilder($query);
    }

    /**
     * Get the processes that have this subject.
     *
     * @return BelongsToMany<Process, $this>
     */
    public function processes(): BelongsToMany
    {
        return $this->belongsToMany(
            Process::class,
            'process_process_subject',
            'process_subject_id',
            'process_id'
        );
    }
}
