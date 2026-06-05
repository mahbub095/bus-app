<?php

namespace App\Http\Controllers;

use App\Models\Bus;
use Illuminate\Http\Request;

class BusController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'operator_name' => 'required|string|max:100',
            'coach_number' => 'required|string|max:50|unique:buses,coach_number',
            'coach_type' => 'required|in:AC,Non AC',
            'total_seats' => 'required|integer|min:10|max:100',
            'seat_layout' => 'required|in:2+2_last5,2+2,1+2,sleeper',
            'seat_layout_grid' => 'nullable|string'
        ]);

        $data = $request->only('operator_name', 'coach_number', 'coach_type', 'total_seats', 'seat_layout');
        if ($request->filled('seat_layout_grid')) {
            $data['seat_layout_grid'] = json_decode($request->input('seat_layout_grid'), true);
        }

        Bus::create($data);

        return $this->adminTabRedirect($request)->with('success', 'Bus fleet registered successfully!');
    }

    public function update(Request $request, $id)
    {
        $bus = Bus::findOrFail($id);

        $request->validate([
            'operator_name' => 'required|string|max:100',
            'coach_number' => 'required|string|max:50|unique:buses,coach_number,' . $id,
            'coach_type' => 'required|in:AC,Non AC',
            'total_seats' => 'required|integer|min:10|max:100',
            'seat_layout' => 'required|in:2+2_last5,2+2,1+2,sleeper',
            'seat_layout_grid' => 'nullable|string'
        ]);

        $data = $request->only('operator_name', 'coach_number', 'coach_type', 'total_seats', 'seat_layout');
        if ($request->has('seat_layout_grid')) {
            $data['seat_layout_grid'] = $request->filled('seat_layout_grid') 
                ? json_decode($request->input('seat_layout_grid'), true) 
                : null;
        }

        $bus->update($data);

        return $this->adminTabRedirect($request)->with('success', 'Coach updated successfully!');
    }

    public function destroy(Request $request, $id)
    {
        Bus::findOrFail($id)->delete();

        return $this->adminTabRedirect($request)->with('success', 'Coach deleted successfully!');
    }
}
