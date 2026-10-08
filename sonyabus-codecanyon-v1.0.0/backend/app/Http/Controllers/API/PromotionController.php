<?php

namespace App\Http\Controllers\API;

use App\Models\Promotion;
use Illuminate\Http\Request;

class PromotionController extends BaseController
{
    public function check(Request $request)
    {
        $request->validate([
            'code' => 'required|string'
        ]);

        $code = strtoupper($request->query('code'));

        $promotion = Promotion::where('code', $code)->first();

        if (!$promotion) {
            return response()->json([
                'message' => 'Invalid promo code.'
            ], 404);
        }

        return response()->json([
            'code' => $promotion->code,
            'discount_amount' => floatval($promotion->discount_amount),
            'description' => $promotion->description
        ]);
    }

    public function index()
    {
        $promotions = Promotion::all();
        return response()->json($promotions);
    }
}
