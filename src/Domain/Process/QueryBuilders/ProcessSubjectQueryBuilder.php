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
     * Filter subjects by process ID.
     *
     * @return $this
     */
    public function whereProcess(string $processId): self
    {
        return $this->where('process_id', $processId);
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
     * Filter subjects by process and subject registration ID.
     *
     * @return $this
     */
    public function whereProcessAndRegistrationId(string $processId, int $subjectRegistrationId): self
    {
        return $this->whereProcess($processId)
            ->whereSubjectRegistrationId($subjectRegistrationId);
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
