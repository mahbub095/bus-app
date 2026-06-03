<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Station;
use Illuminate\Http\Request;

class StationController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100|unique:stations,name',
            'district' => 'nullable|string|max:100'
        ]);

        Station::create([
            'name' => strtoupper(trim($request->input('name'))),
            'district' => trim($request->input('district'))
        ]);

        return $this->adminTabRedirect($request)->with('success', 'Station terminal created successfully!');
    }

    public function update(Request $request, $id)
    {
        $station = Station::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:100|unique:stations,name,' . $id,
            'district' => 'nullable|string|max:100'
        ]);

        $station->update([
            'name' => strtoupper(trim($request->input('name'))),
            'district' => trim($request->input('district'))
        ]);

        return $this->adminTabRedirect($request)->with('success', 'Station terminal updated successfully!');
    }

    public function destroy(Request $request, $id)
    {
        $station = Station::findOrFail($id);

        if ($station->departureRoutes()->exists() || $station->arrivalRoutes()->exists()) {
            return $this->adminTabRedirect($request)->withErrors(['message' => 'Cannot delete station — it is linked to existing routes.']);
        }

        $station->delete();

        return $this->adminTabRedirect($request)->with('success', 'Station terminal deleted successfully!');
    }
}
