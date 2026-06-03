<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Route as RouteModel;
use Illuminate\Http\Request;

class RouteController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'departure_station_id' => 'required|exists:stations,id',
            'arrival_station_id' => 'required|exists:stations,id|different:departure_station_id',
            'distance' => 'nullable|string|max:50',
            'duration' => 'nullable|string|max:50'
        ]);

        $exists = RouteModel::where('departure_station_id', $request->input('departure_station_id'))
            ->where('arrival_station_id', $request->input('arrival_station_id'))
            ->first();

        if ($exists) {
            return $this->adminTabRedirect($request)->withInput()->withErrors(['departure_station_id' => 'A route between these stations already exists.']);
        }

        RouteModel::create($request->only('departure_station_id', 'arrival_station_id', 'distance', 'duration'));

        return $this->adminTabRedirect($request)->with('success', 'Transport line route connection configured successfully!');
    }

    public function update(Request $request, $id)
    {
        $route = RouteModel::findOrFail($id);

        $request->validate([
            'departure_station_id' => 'required|exists:stations,id',
            'arrival_station_id' => 'required|exists:stations,id|different:departure_station_id',
            'distance' => 'nullable|string|max:50',
            'duration' => 'nullable|string|max:50'
        ]);

        $exists = RouteModel::where('departure_station_id', $request->input('departure_station_id'))
            ->where('arrival_station_id', $request->input('arrival_station_id'))
            ->where('id', '!=', $id)
            ->first();

        if ($exists) {
            return $this->adminTabRedirect($request)->withInput()->withErrors(['departure_station_id' => 'A route between these stations already exists.']);
        }

        $route->update($request->only('departure_station_id', 'arrival_station_id', 'distance', 'duration'));

        return $this->adminTabRedirect($request)->with('success', 'Route updated successfully!');
    }

    public function destroy(Request $request, $id)
    {
        RouteModel::findOrFail($id)->delete();

        return $this->adminTabRedirect($request)->with('success', 'Route deleted successfully!');
    }
}
