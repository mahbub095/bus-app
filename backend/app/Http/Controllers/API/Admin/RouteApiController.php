<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\API\Admin\Concerns\FormatsAdminResources;
use App\Models\Route as RouteModel;
use App\Services\RouteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Transport line routes (dashboard "Routes" tab).
 *
 * Validation of the boarding / dropping point lists lives in RouteService so
 * the API and the Blade form cannot diverge.
 */
class RouteApiController extends BaseAdminApiController
{
    use FormatsAdminResources;

    public function __construct(protected RouteService $routeService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'search' => 'nullable|string|max:100',
            'departure_station_id' => 'nullable|integer|exists:stations,id',
            'arrival_station_id' => 'nullable|integer|exists:stations,id',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $routes = RouteModel::with(['departureStation', 'arrivalStation'])
            ->when($request->filled('departure_station_id'), function ($query) use ($request) {
                $query->where('departure_station_id', (int) $request->query('departure_station_id'));
            })
            ->when($request->filled('arrival_station_id'), function ($query) use ($request) {
                $query->where('arrival_station_id', (int) $request->query('arrival_station_id'));
            })
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = $this->likeTerm((string) $request->query('search'));
                $query->where(function ($inner) use ($term) {
                    $inner->whereHas('departureStation', fn ($station) => $station->where('name', 'like', $term))
                        ->orWhereHas('arrivalStation', fn ($station) => $station->where('name', 'like', $term));
                });
            })
            ->orderBy('id', 'desc')
            ->paginate($this->perPage($request))
            ->through(fn (RouteModel $route) => $this->formatRoute($route));

        return response()->json($routes);
    }

    public function show(int $id): JsonResponse
    {
        $route = RouteModel::with(['departureStation', 'arrivalStation'])->findOrFail($id);

        return $this->itemResponse($this->formatRoute($route));
    }

    public function store(Request $request): JsonResponse
    {
        $this->normalisePoints($request);
        $validated = $this->routeService->validateFromRequest($request);

        if ($this->routeService->duplicateExists(
            (int) $validated['departure_station_id'],
            (int) $validated['arrival_station_id']
        )) {
            return response()->json([
                'message' => 'A route between these stations already exists.',
                'errors' => ['departure_station_id' => ['A route between these stations already exists.']],
            ], 422);
        }

        $route = RouteModel::create($validated);
        $route->load(['departureStation', 'arrivalStation']);

        return $this->itemResponse(
            $this->formatRoute($route),
            'Transport line route connection configured successfully.',
            201
        );
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $route = RouteModel::findOrFail($id);

        $this->normalisePoints($request, $route);
        $validated = $this->routeService->validateFromRequest($request);

        if ($this->routeService->duplicateExists(
            (int) $validated['departure_station_id'],
            (int) $validated['arrival_station_id'],
            $id
        )) {
            return response()->json([
                'message' => 'A route between these stations already exists.',
                'errors' => ['departure_station_id' => ['A route between these stations already exists.']],
            ], 422);
        }

        $route->update($validated);
        $route->load(['departureStation', 'arrivalStation']);

        return $this->itemResponse($this->formatRoute($route), 'Route updated successfully.');
    }

    public function destroy(int $id): JsonResponse
    {
        RouteModel::findOrFail($id)->delete();

        return response()->json(['message' => 'Route deleted successfully.']);
    }

    /**
     * RouteService reads the point lists as JSON text. API clients may send a
     * real array (or the *"_json" key); on update, unchanged lists fall back to
     * the stored value so a partial payload does not wipe them.
     */
    protected function normalisePoints(Request $request, ?RouteModel $existing = null): void
    {
        foreach (['boarding', 'dropping'] as $kind) {
            $key = $kind.'_points';
            $jsonKey = $key.'_json';

            $raw = $request->input($key) ?? $request->input($jsonKey);

            if (is_array($raw)) {
                $request->merge([$jsonKey => json_encode($raw)]);
            } elseif (is_string($raw)) {
                $request->merge([$jsonKey => $raw]);
            } elseif ($existing) {
                $request->merge([$jsonKey => json_encode($existing->{$key} ?? [])]);
            }
        }
    }
}
