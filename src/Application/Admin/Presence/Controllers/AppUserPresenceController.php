<?php

declare(strict_types=1);

namespace Src\Application\Admin\Presence\Controllers;

use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Src\Application\Admin\Presence\Resources\OnlineAppUsersResource;
use Src\Application\Admin\Presence\Services\ListAppUserPresenceSummaryService;
use Src\Application\Admin\Presence\Services\ListOnlineAppUsersService;
use Src\Application\Admin\Presence\Services\ListTodayAppUserActivityService;

readonly class AppUserPresenceController
{
    public function __construct(
        private ListTodayAppUserActivityService $listTodayAppUserActivityService,
        private ListOnlineAppUsersService $listOnlineAppUsersService,
        private ListAppUserPresenceSummaryService $listAppUserPresenceSummaryService,
    ) {}

    public function today(Request $request): LengthAwarePaginator
    {
        $perPage = min(100, max(1, $this->integerQuery($request, 'per_page', 20)));
        $page = max(1, $this->integerQuery($request, 'page', 1));
        [$from, $to] = $this->dateRange($request, true);

        return $this->listTodayAppUserActivityService->handle(
            $this->organizationId($request),
            $from,
            $to,
            $perPage,
            $page,
        );
    }

    public function summary(Request $request): JsonResponse
    {
        $perPage = min(100, max(1, $this->integerQuery($request, 'per_page', 20)));
        $page = max(1, $this->integerQuery($request, 'page', 1));
        [$from, $to] = $this->dateRange($request, false);
        $result = $this->listAppUserPresenceSummaryService->handle(
            $this->organizationId($request),
            $from,
            $to,
            $perPage,
            $page,
        );

        $payload = $result->activities->toArray();
        $payload['from'] = $result->from;
        $payload['to'] = $result->to;
        $payload['organization_id'] = $result->organizationId;
        $payload['totals'] = [
            'users' => $result->users,
            'login_count' => $result->loginCount,
            'days_online' => $result->daysOnline,
        ];

        return response()->json($payload);
    }

    public function online(Request $request): OnlineAppUsersResource
    {
        return $this->listOnlineAppUsersService->handle($this->organizationId($request));
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function dateRange(Request $request, bool $defaultToday): array
    {
        $date = $request->query('date');
        $from = $request->query('from');
        $to = $request->query('to');
        $hasFrom = is_string($from) && $from !== '';
        $hasTo = is_string($to) && $to !== '';

        if (is_string($date) && $date !== '' && ! $hasFrom && ! $hasTo) {
            $day = $this->assertDate('date', $date);

            return [$day, $day];
        }

        if (! $hasFrom && ! $hasTo) {
            if ($defaultToday) {
                $today = now()->toDateString();

                return [$today, $today];
            }

            return [now()->startOfMonth()->toDateString(), now()->toDateString()];
        }

        $fromDate = now()->startOfMonth()->toDateString();
        $toDate = now()->toDateString();

        if (is_string($from) && $from !== '') {
            $fromDate = $this->assertDate('from', $from);
        }

        if (is_string($to) && $to !== '') {
            $toDate = $this->assertDate('to', $to);
        }

        if ($fromDate > $toDate) {
            throw ValidationException::withMessages([
                'from' => [__('validation.before_or_equal', ['attribute' => 'from', 'date' => $toDate])],
            ]);
        }

        return [$fromDate, $toDate];
    }

    private function assertDate(string $attribute, string $date): string
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            throw ValidationException::withMessages([
                $attribute => [__('validation.date', ['attribute' => $attribute])],
            ]);
        }

        try {
            $parsed = Carbon::createFromFormat('Y-m-d', $date);
        } catch (InvalidFormatException) {
            $parsed = false;
        }

        if ($parsed === false || $parsed->format('Y-m-d') !== $date) {
            throw ValidationException::withMessages([
                $attribute => [__('validation.date', ['attribute' => $attribute])],
            ]);
        }

        return $date;
    }

    private function organizationId(Request $request): ?string
    {
        $organizationId = $request->query('organization_id');

        if ($organizationId === null || $organizationId === '') {
            return null;
        }

        if (! is_string($organizationId) || preg_match('/^[0-9a-fA-F-]{36}$/', $organizationId) !== 1) {
            throw ValidationException::withMessages([
                'organization_id' => [__('validation.uuid', ['attribute' => 'organization_id'])],
            ]);
        }

        return $organizationId;
    }

    private function integerQuery(Request $request, string $key, int $default): int
    {
        $value = $request->query($key, $default);

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        return $default;
    }
}
