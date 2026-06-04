<?php

namespace App\Http\Controllers;

use App\Models\Route as RouteModel;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class RouteController extends Controller
{
    public function store(Request $request)
    {
        $validated = $this->validateRoute($request);

        $exists = RouteModel::where('departure_station_id', $validated['departure_station_id'])
            ->where('arrival_station_id', $validated['arrival_station_id'])
            ->first();

        if ($exists) {
            return $this->adminTabRedirect($request)->withInput()->withErrors([
                'departure_station_id' => 'A route between these stations already exists.',
            ]);
        }

        RouteModel::create($validated);

        return $this->adminTabRedirect($request)->with('success', 'Transport line route connection configured successfully!');
    }

    public function update(Request $request, $id)
    {
        $route = RouteModel::findOrFail($id);
        $validated = $this->validateRoute($request);

        $exists = RouteModel::where('departure_station_id', $validated['departure_station_id'])
            ->where('arrival_station_id', $validated['arrival_station_id'])
            ->where('id', '!=', $id)
            ->first();

        if ($exists) {
            return $this->adminTabRedirect($request)->withInput()->withErrors([
                'departure_station_id' => 'A route between these stations already exists.',
            ]);
        }

        $route->update($validated);

        return $this->adminTabRedirect($request)->with('success', 'Route updated successfully!');
    }

    public function destroy(Request $request, $id)
    {
        RouteModel::findOrFail($id)->delete();

        return $this->adminTabRedirect($request)->with('success', 'Route deleted successfully!');
    }

    protected function validateRoute(Request $request): array
    {
        $request->validate([
            'departure_station_id' => 'required|exists:stations,id',
            'arrival_station_id' => 'required|exists:stations,id|different:departure_station_id',
            'distance' => 'nullable|string|max:50',
            'duration' => 'nullable|string|max:50',
            'boarding_points_json' => 'nullable|string',
            'dropping_points_json' => 'nullable|string',
        ]);

        $boarding = $this->parsePointsJson($request->input('boarding_points_json'));
        $dropping = $this->parsePointsJson($request->input('dropping_points_json'));

        if (empty($boarding)) {
            throw ValidationException::withMessages([
                'boarding_points_json' => 'Add at least one boarding point.',
            ]);
        }

        if (empty($dropping)) {
            throw ValidationException::withMessages([
                'dropping_points_json' => 'Add at least one dropping point.',
            ]);
        }

        foreach ($boarding as $index => $point) {
            if (empty($point['name'])) {
                throw ValidationException::withMessages([
                    'boarding_points_json' => 'Boarding point #' . ($index + 1) . ' requires a name.',
                ]);
            }
        }

        foreach ($dropping as $index => $point) {
            if (empty($point['name'])) {
                throw ValidationException::withMessages([
                    'dropping_points_json' => 'Dropping point #' . ($index + 1) . ' requires a name.',
                ]);
            }
        }

        return [
            'departure_station_id' => $request->input('departure_station_id'),
            'arrival_station_id' => $request->input('arrival_station_id'),
            'distance' => $request->input('distance'),
            'duration' => $request->input('duration'),
            'boarding_points' => $boarding,
            'dropping_points' => $dropping,
        ];
    }

    protected function parsePointsJson(?string $json): array
    {
        if (! $json) {
            return [];
        }

        $decoded = json_decode($json, true);

        if (! is_array($decoded)) {
            return [];
        }

        return array_values(array_filter(array_map(function ($row) {
            if (! is_array($row)) {
                return null;
            }

            $cleaned = array_map(fn ($v) => is_string($v) ? trim($v) : $v, $row);

            return empty($cleaned['name']) ? null : $cleaned;
        }, $decoded)));
    }
}
