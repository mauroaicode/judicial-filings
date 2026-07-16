<?php

declare(strict_types=1);

namespace Src\Domain\JudicialSync\Enums;

/**
 * Data source that a {@see \Src\Domain\JudicialSync\Models\JudicialSyncRun} synchronizes.
 *
 * Values align with {@see \Src\Domain\Process\Enums\ProcessDataSourceSlug} where applicable.
 * `tyba` is reserved for a future source and may appear in history filters before sync is implemented.
 */
enum JudicialSyncDataSource: string
{
    case JudicialBranch = 'judicial_branch';

    case Samai = 'samai';

    case Tyba = 'tyba';

    public function getLabel(): string
    {
        return __('enums.judicial_sync_data_source.'.$this->value);
    }

    /**
     * Sources that the admin sync endpoint can execute today.
     *
     * @return list<self>
     */
    public static function executable(): array
    {
        return [
            self::JudicialBranch,
            self::Samai,
        ];
    }

    public function isExecutable(): bool
    {
        return in_array($this, self::executable(), true);
    }

    public function artisanCommand(): string
    {
        return match ($this) {
            self::JudicialBranch => 'judicial:sync-processes',
            self::Samai => 'samai:sync-processes',
            self::Tyba => throw new \LogicException('TYBA sync is not implemented yet.'),
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * @return list<string>
     */
    public static function executableValues(): array
    {
        return array_map(
            static fn (self $case): string => $case->value,
            self::executable(),
        );
    }
}
