<?php

namespace App\Http\Controllers\API;

use App\Models\Station;
use Illuminate\Http\Request;

class StationController extends BaseController
{
    public function index()
    {
        $stations = Station::orderBy('name', 'asc')->get();
        return response()->json($stations);
    }
}
