<?php

namespace App\Http\Controllers\Admin;

use App\Models\Route as RouteModel;
use App\Services\RouteService;
use Illuminate\Http\Request;

class RouteController extends BaseAdminController
{
    public function __construct(protected RouteService $routeService)
    {
    }

    public function store(Request $request)
    {
        $validated = $this->routeService->validateFromRequest($request);

        if ($this->routeService->duplicateExists($validated['departure_station_id'], $validated['arrival_station_id'])) {
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
        $validated = $this->routeService->validateFromRequest($request);

        if ($this->routeService->duplicateExists($validated['departure_station_id'], $validated['arrival_station_id'], (int) $id)) {
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
}
