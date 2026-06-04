<?php

namespace App\Http\Controllers;

use App\Models\Schedule;
use Illuminate\Http\Request;

class ScheduleController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'bus_id' => 'required|exists:buses,id',
            'route_id' => 'required|exists:routes,id',
            'departure_time' => 'required|date|after_or_equal:today',
            'arrival_time' => 'required|date|after:departure_time',
            'fare' => 'required|numeric|min:0'
        ]);

        Schedule::create($request->only('bus_id', 'route_id', 'departure_time', 'arrival_time', 'fare'));

        return $this->adminTabRedirect($request)->with('success', 'Schedule run registered successfully!');
    }

    public function update(Request $request, $id)
    {
        $schedule = Schedule::findOrFail($id);

        $request->validate([
            'bus_id' => 'required|exists:buses,id',
            'route_id' => 'required|exists:routes,id',
            'departure_time' => 'required|date',
            'arrival_time' => 'required|date|after:departure_time',
            'fare' => 'required|numeric|min:0'
        ]);

        $schedule->update($request->only('bus_id', 'route_id', 'departure_time', 'arrival_time', 'fare'));

        return $this->adminTabRedirect($request)->with('success', 'Schedule updated successfully!');
    }

    public function destroy(Request $request, $id)
    {
        Schedule::findOrFail($id)->delete();

        return $this->adminTabRedirect($request)->with('success', 'Schedule deleted successfully!');
    }
}
