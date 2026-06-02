<?php

namespace App\Http\Controllers;

use App\Models\Station;
use Illuminate\Http\Request;

class StationController extends Controller
{
    public function index()
    {
        $stations = Station::orderBy('name', 'asc')->get();
        return response()->json($stations);
    }
}
