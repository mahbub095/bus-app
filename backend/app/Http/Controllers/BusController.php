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
            'total_seats' => 'required|integer|min:10|max:100'
        ]);

        Bus::create($request->only('operator_name', 'coach_number', 'coach_type', 'total_seats'));

        return $this->adminTabRedirect($request)->with('success', 'Bus fleet registered successfully!');
    }

    public function update(Request $request, $id)
    {
        $bus = Bus::findOrFail($id);

        $request->validate([
            'operator_name' => 'required|string|max:100',
            'coach_number' => 'required|string|max:50|unique:buses,coach_number,' . $id,
            'coach_type' => 'required|in:AC,Non AC',
            'total_seats' => 'required|integer|min:10|max:100'
        ]);

        $bus->update($request->only('operator_name', 'coach_number', 'coach_type', 'total_seats'));

        return $this->adminTabRedirect($request)->with('success', 'Coach updated successfully!');
    }

    public function destroy(Request $request, $id)
    {
        Bus::findOrFail($id)->delete();

        return $this->adminTabRedirect($request)->with('success', 'Coach deleted successfully!');
    }
}
