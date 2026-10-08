<?php

namespace App\Services;

use App\Models\Route as RouteModel;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Route (transport line) validation and persistence.
 */
class RouteService
{
    public function validateFromRequest(Request $request): array
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
                    'boarding_points_json' => 'Boarding point #'.($index + 1).' requires a name.',
                ]);
            }
        }

        foreach ($dropping as $index => $point) {
            if (empty($point['name'])) {
                throw ValidationException::withMessages([
                    'dropping_points_json' => 'Dropping point #'.($index + 1).' requires a name.',
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

    public function duplicateExists(int $departureId, int $arrivalId, ?int $exceptId = null): bool
    {
        $query = RouteModel::where('departure_station_id', $departureId)
            ->where('arrival_station_id', $arrivalId);

        if ($exceptId !== null) {
            $query->where('id', '!=', $exceptId);
        }

        return $query->exists();
    }

    /** @return list<array<string, mixed>> */
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
