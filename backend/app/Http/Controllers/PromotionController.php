<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Promotion;
use Illuminate\Http\Request;

class PromotionController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'code' => 'required|string|max:50|unique:promotions,code',
            'discount_amount' => 'required|numeric|min:0',
            'description' => 'required|string|max:255'
        ]);

        Promotion::create([
            'code' => strtoupper(trim($request->input('code'))),
            'discount_amount' => $request->input('discount_amount'),
            'description' => trim($request->input('description'))
        ]);

        return $this->adminTabRedirect($request)->with('success', 'Promotion code coupon generated successfully!');
    }

    public function update(Request $request, $id)
    {
        $promotion = Promotion::findOrFail($id);

        $request->validate([
            'code' => 'required|string|max:50|unique:promotions,code,' . $id,
            'discount_amount' => 'required|numeric|min:0',
            'description' => 'required|string|max:255'
        ]);

        $promotion->update([
            'code' => strtoupper(trim($request->input('code'))),
            'discount_amount' => $request->input('discount_amount'),
            'description' => trim($request->input('description'))
        ]);

        return $this->adminTabRedirect($request)->with('success', 'Coupon updated successfully!');
    }

    public function destroy(Request $request, $id)
    {
        Promotion::findOrFail($id)->delete();

        return $this->adminTabRedirect($request)->with('success', 'Coupon deleted successfully!');
    }
}
