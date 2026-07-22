<?php

namespace App\Http\Controllers\Admin;

use App\Models\Station;
use Illuminate\Http\Request;

class StationController extends BaseAdminController
{
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100|unique:stations,name',
        ]);

        Station::create([
            'name' => strtoupper(trim($request->input('name'))),
        ]);

        return $this->adminTabRedirect($request)->with('success', 'Station terminal created successfully!');
    }

    public function update(Request $request, $id)
    {
        $station = Station::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:100|unique:stations,name,'.$id,
        ]);

        $station->update([
            'name' => strtoupper(trim($request->input('name'))),
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
