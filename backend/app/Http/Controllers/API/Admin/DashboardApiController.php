<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\API\Admin\Concerns\FormatsAdminResources;
use App\Services\AdminDashboardAnalyticsService;
use App\Services\AdminDashboardService;
use App\Services\AdminSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Mirrors the Blade dashboard: the four KPI cards, the analytics charts and the
 * first page of every management list (stations, coaches, routes, schedules,
 * promotions and users).
 */
class DashboardApiController extends BaseAdminApiController
{
    use FormatsAdminResources;

    public function __construct(
        protected AdminDashboardService $dashboardService,
        protected AdminDashboardAnalyticsService $analyticsService,
        protected AdminSessionService $adminSessionService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        // Same per-tab search terms the dashboard accepts.
        $request->validate([
            'buses_search' => 'nullable|string|max:100',
            'schedules_search' => 'nullable|string|max:100',
        ]);

        $data = $this->dashboardService->getDashboardData();

        return response()->json([
            'metrics' => $data['metrics'],
            'analytics' => $data['analytics'],
            'session' => [
                'admin_idle_timeout_minutes' => $this->adminSessionService->minutes(),
                'customer_token_lifetime_minutes' => $this->adminSessionService->customerMinutes(),
            ],
            'lists' => [
                'stations' => $this->formatPaginator($data['stations'], fn ($station) => $this->formatStation($station)),
                'all_stations' => collect($data['allStations'])->map(fn ($station) => $this->formatStation($station))->values(),
                'buses' => $this->formatPaginator($data['buses'], fn ($bus) => $this->formatBus($bus)),
                'routes' => $this->formatPaginator($data['routes'], fn ($route) => $this->formatRoute($route)),
                'schedules' => $this->formatPaginator($data['schedules'], fn ($schedule) => $this->formatSchedule($schedule)),
                'promotions' => $this->formatPaginator($data['promotions'], fn ($promotion) => $this->formatPromotion($promotion)),
                'users' => $this->formatPaginator($data['users'], fn ($user) => $this->formatUser($user)),
            ],
        ]);
    }

    public function analytics(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'period' => 'nullable|string|in:today,last_7_days,this_month,this_year',
        ]);

        return response()->json(
            $this->analyticsService->getAnalytics($validated['period'] ?? 'this_month')
        );
    }

    /**
     * Flatten a paginator into a compact, JSON friendly structure.
     *
     * @param  callable(mixed): array<string, mixed>  $formatter
     * @return array<string, mixed>
     */
    protected function formatPaginator(LengthAwarePaginator $paginator, callable $formatter): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'last_page' => $paginator->lastPage(),
            'total' => $paginator->total(),
            'data' => collect($paginator->items())->map($formatter)->values()->all(),
        ];
    }
}
