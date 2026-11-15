<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\API\Admin\Concerns\FormatsAdminResources;
use App\Models\Station;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Station terminal management (dashboard "Stations" tab).
 */
class StationApiController extends BaseAdminApiController
{
    use FormatsAdminResources;

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'search' => 'nullable|string|max:100',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $stations = Station::query()
            ->when($request->filled('search'), function ($query) use ($request) {
                $query->where('name', 'like', $this->likeTerm((string) $request->query('search')));
            })
            ->orderBy('name')
            ->paginate($this->perPage($request))
            ->through(fn (Station $station) => $this->formatStation($station));

        return response()->json($stations);
    }

    public function show(int $id): JsonResponse
    {
        return $this->itemResponse($this->formatStation(Station::findOrFail($id)));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:stations,name',
        ]);

        $station = Station::create(['name' => strtoupper(trim($validated['name']))]);

        return $this->itemResponse(
            $this->formatStation($station),
            'Station terminal created successfully.',
            201
        );
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $station = Station::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:stations,name,'.$id,
        ]);

        $station->update(['name' => strtoupper(trim($validated['name']))]);

        return $this->itemResponse(
            $this->formatStation($station->fresh()),
            'Station terminal updated successfully.'
        );
    }

    public function destroy(int $id): JsonResponse
    {
        $station = Station::findOrFail($id);

        if ($station->departureRoutes()->exists() || $station->arrivalRoutes()->exists()) {
            return response()->json([
                'message' => 'Cannot delete station — it is linked to existing routes.',
            ], 409);
        }

        $station->delete();

        return response()->json(['message' => 'Station terminal deleted successfully.']);
    }
}
