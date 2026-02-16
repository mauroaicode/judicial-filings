<?php

declare(strict_types=1);

namespace Src\Domain\Process\QueryBuilders;

use Illuminate\Database\Eloquent\Builder;

/**
 * @extends Builder<\Src\Domain\Process\Models\ProcessSubject>
 */
class ProcessSubjectQueryBuilder extends Builder
{
    /**
     * Filter subjects attached to the given process (via pivot).
     *
     * @return $this
     */
    public function whereProcess(string $processId): self
    {
        return $this->whereHas('processes', function ($query) use ($processId): void {
            $query->where('processes.id', $processId);
        });
    }

    /**
     * Filter subjects by subject registration ID.
     *
     * @return $this
     */
    public function whereSubjectRegistrationId(int $subjectRegistrationId): self
    {
        return $this->where('subject_registration_id', $subjectRegistrationId);
    }

    /**
     * Verifica si ya existe un sujeto con el subject_registration_id dado (en toda la tabla).
     */
    public function existsBySubjectRegistrationId(int $subjectRegistrationId): bool
    {
        return $this->where('subject_registration_id', $subjectRegistrationId)->exists();
    }

    /**
     * Verifica si el proceso ya tiene asociado un sujeto con ese subject_registration_id (via pivot).
     */
    public function existsByProcessAndSubjectRegistrationId(string $processId, int $subjectRegistrationId): bool
    {
        return $this->where('subject_registration_id', $subjectRegistrationId)
            ->whereHas('processes', function ($query) use ($processId): void {
                $query->where('processes.id', $processId);
            })
            ->exists();
    }

    /**
     * Filter subjects by process (via pivot) and subject registration ID.
     *
     * @return $this
     */
    public function whereProcessAndRegistrationId(string $processId, int $subjectRegistrationId): self
    {
        return $this->where('subject_registration_id', $subjectRegistrationId)
            ->whereHas('processes', function ($query) use ($processId): void {
                $query->where('processes.id', $processId);
            });
    }

    /**
     * Filter subjects by subject type.
     *
     * @return $this
     */
    public function whereSubjectType(string $subjectType): self
    {
        return $this->where('subject_type', $subjectType);
    }

    /**
     * Filter subjects that are cited.
     *
     * @return $this
     */
    public function whereCited(): self
    {
        return $this->where('is_cited', true);
    }

    /**
     * Order subjects by subject type.
     *
     * @return $this
     *
     * @phpstan-return static
     */
    public function orderedBySubjectType(): self
    {
        $this->orderBy('subject_type');

        return $this;
    }
}
