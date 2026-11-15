<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\API\Admin\Concerns\FormatsAdminResources;
use App\Models\Schedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Schedule (departure run) management (dashboard "Schedules" tab).
 */
class ScheduleApiController extends BaseAdminApiController
{
    use FormatsAdminResources;

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'search' => 'nullable|string|max:100',
            'bus_id' => 'nullable|integer|exists:buses,id',
            'route_id' => 'nullable|integer|exists:routes,id',
            'date' => 'nullable|date_format:Y-m-d',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $schedules = Schedule::with(['bus', 'route.departureStation', 'route.arrivalStation'])
            ->when($request->filled('bus_id'), function ($query) use ($request) {
                $query->where('bus_id', (int) $request->query('bus_id'));
            })
            ->when($request->filled('route_id'), function ($query) use ($request) {
                $query->where('route_id', (int) $request->query('route_id'));
            })
            ->when($request->filled('date'), function ($query) use ($request) {
                $date = (string) $request->query('date');
                $query->whereBetween('departure_time', [$date.' 00:00:00', $date.' 23:59:59']);
            })
            ->when($request->filled('search'), function ($query) use ($request) {
                // "Operator & Coach" column — operator name or coach number.
                $term = $this->likeTerm((string) $request->query('search'));
                $query->whereHas('bus', function ($bus) use ($term) {
                    $bus->where('operator_name', 'like', $term)
                        ->orWhere('coach_number', 'like', $term);
                });
            })
            ->orderBy('id', 'desc')
            ->paginate($this->perPage($request))
            ->through(fn (Schedule $schedule) => $this->formatSchedule($schedule));

        return response()->json($schedules);
    }

    public function show(int $id): JsonResponse
    {
        $schedule = Schedule::with(['bus', 'route.departureStation', 'route.arrivalStation'])->findOrFail($id);

        return $this->itemResponse($this->formatSchedule($schedule));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'bus_id' => 'required|exists:buses,id',
            'route_id' => 'required|exists:routes,id',
            'departure_time' => 'required|date|after_or_equal:today',
            'arrival_time' => 'required|date|after:departure_time',
            'fare' => 'required|numeric|min:0',
        ]);

        $schedule = Schedule::create($validated);
        $schedule->load(['bus', 'route.departureStation', 'route.arrivalStation']);

        return $this->itemResponse(
            $this->formatSchedule($schedule),
            'Schedule run registered successfully.',
            201
        );
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $schedule = Schedule::findOrFail($id);

        // Editing an existing run may keep its (already started) departure.
        $validated = $request->validate([
            'bus_id' => 'required|exists:buses,id',
            'route_id' => 'required|exists:routes,id',
            'departure_time' => 'required|date',
            'arrival_time' => 'required|date|after:departure_time',
            'fare' => 'required|numeric|min:0',
        ]);

        $schedule->update($validated);
        $schedule->load(['bus', 'route.departureStation', 'route.arrivalStation']);

        return $this->itemResponse($this->formatSchedule($schedule), 'Schedule updated successfully.');
    }

    public function destroy(int $id): JsonResponse
    {
        Schedule::findOrFail($id)->delete();

        return response()->json(['message' => 'Schedule deleted successfully.']);
    }
}
