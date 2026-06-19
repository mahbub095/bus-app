<?php

namespace App\Http\Controllers\API;

use App\Services\CoachServicesService;
use Illuminate\Http\Request;

class SearchController extends BaseController
{
    public function __construct(protected CoachServicesService $coachServicesService)
    {
    }

    public function search(Request $request)
    {
        $request->validate([
            'from' => 'required|exists:stations,id',
            'to' => 'required|exists:stations,id',
            'date' => 'required|date_format:Y-m-d',
            'coach_type' => 'nullable|string',
        ]);

        $results = $this->coachServicesService->search(
            (int) $request->query('from'),
            (int) $request->query('to'),
            $request->query('date'),
            $request->query('coach_type')
        );

        return response()->json($results);
    }
}
