<?php

declare(strict_types=1);

namespace Src\Application\Admin\Presence\Controllers;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Src\Application\Admin\Presence\Resources\OnlineAppUsersResource;
use Src\Application\Admin\Presence\Services\ListAppUserPresenceSummaryService;
use Src\Application\Admin\Presence\Services\ListOnlineAppUsersService;
use Src\Application\Admin\Presence\Services\ListOrganizationPresenceService;
use Src\Application\Admin\Presence\Services\ListTodayAppUserActivityService;

readonly class AppUserPresenceController
{
    public function __construct(
        private ListTodayAppUserActivityService $listTodayAppUserActivityService,
        private ListOnlineAppUsersService $listOnlineAppUsersService,
        private ListAppUserPresenceSummaryService $listAppUserPresenceSummaryService,
        private ListOrganizationPresenceService $listOrganizationPresenceService,
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

    public function organizations(Request $request): JsonResponse
    {
        $perPage = min(100, max(1, $this->integerQuery($request, 'per_page', 20)));
        $page = max(1, $this->integerQuery($request, 'page', 1));
        $inactiveDays = min(365, max(1, $this->integerQuery($request, 'inactive_days', 30)));
        $status = $this->organizationStatus($request);
        $result = $this->listOrganizationPresenceService->handle(
            $status,
            $inactiveDays,
            $this->isActiveFilter($request),
            $this->search($request),
            $perPage,
            $page,
        );

        $payload = $result->organizations->toArray();
        $payload['status'] = $result->status;
        $payload['inactive_days'] = $result->inactiveDays;
        $payload['is_active'] = $result->isActive;
        $payload['search'] = $result->search;
        $payload['totals'] = [
            'never' => $result->never,
            'stale' => $result->stale,
            'recent' => $result->recent,
        ];

        return response()->json($payload);
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

        $parsed = \Illuminate\Support\Facades\Date::createFromFormat('Y-m-d', $date);

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

    private function organizationStatus(Request $request): string
    {
        $status = $request->query('status', 'never');

        if (! is_string($status) || ! in_array($status, ['never', 'stale', 'all'], true)) {
            throw ValidationException::withMessages([
                'status' => [__('validation.in', ['attribute' => 'status'])],
            ]);
        }

        return $status;
    }

    private function isActiveFilter(Request $request): ?bool
    {
        if (! $request->query->has('is_active')) {
            return true;
        }

        $value = $request->query('is_active');

        if ($value === null || $value === '') {
            return null;
        }

        if (! is_string($value)) {
            throw ValidationException::withMessages([
                'is_active' => [__('validation.boolean', ['attribute' => 'is_active'])],
            ]);
        }

        $normalized = strtolower($value);

        if (in_array($normalized, ['1', 'true', 'yes'], true)) {
            return true;
        }

        if (in_array($normalized, ['0', 'false', 'no'], true)) {
            return false;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        throw ValidationException::withMessages([
            'is_active' => [__('validation.boolean', ['attribute' => 'is_active'])],
        ]);
    }

    private function search(Request $request): ?string
    {
        $search = $request->query('search');

        if ($search === null || $search === '') {
            return null;
        }

        if (! is_string($search)) {
            throw ValidationException::withMessages([
                'search' => [__('validation.string', ['attribute' => 'search'])],
            ]);
        }

        $search = trim($search);

        return $search === '' ? null : mb_substr($search, 0, 100);
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
