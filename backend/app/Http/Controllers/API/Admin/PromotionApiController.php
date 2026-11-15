<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\API\Admin\Concerns\FormatsAdminResources;
use App\Models\Promotion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Coupon / promotion management (dashboard "Promotions" tab).
 */
class PromotionApiController extends BaseAdminApiController
{
    use FormatsAdminResources;

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'search' => 'nullable|string|max:100',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $promotions = Promotion::query()
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = $this->likeTerm((string) $request->query('search'));
                $query->where(function ($inner) use ($term) {
                    $inner->where('code', 'like', $term)
                        ->orWhere('description', 'like', $term);
                });
            })
            ->orderBy('code')
            ->paginate($this->perPage($request))
            ->through(fn (Promotion $promotion) => $this->formatPromotion($promotion));

        return response()->json($promotions);
    }

    public function show(int $id): JsonResponse
    {
        return $this->itemResponse($this->formatPromotion(Promotion::findOrFail($id)));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->rules());

        $promotion = Promotion::create($this->payload($validated));

        return $this->itemResponse(
            $this->formatPromotion($promotion),
            'Promotion code coupon generated successfully.',
            201
        );
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $promotion = Promotion::findOrFail($id);

        $validated = $request->validate($this->rules($id));

        $promotion->update($this->payload($validated));

        return $this->itemResponse($this->formatPromotion($promotion->fresh()), 'Coupon updated successfully.');
    }

    public function destroy(int $id): JsonResponse
    {
        Promotion::findOrFail($id)->delete();

        return response()->json(['message' => 'Coupon deleted successfully.']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(?int $id = null): array
    {
        return [
            'code' => 'required|string|max:50|unique:promotions,code'.($id ? ','.$id : ''),
            'discount_amount' => 'required|numeric|min:0',
            'description' => 'required|string|max:255',
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    protected function payload(array $validated): array
    {
        return [
            'code' => strtoupper(trim((string) $validated['code'])),
            'discount_amount' => $validated['discount_amount'],
            'description' => trim((string) $validated['description']),
        ];
    }
}
