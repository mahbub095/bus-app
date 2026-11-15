<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\API\Admin\Concerns\FormatsAdminResources;
use App\Models\Bus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Coach (bus) fleet management (dashboard "Coaches" tab).
 */
class BusApiController extends BaseAdminApiController
{
    use FormatsAdminResources;

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'search' => 'nullable|string|max:100',
            'coach_type' => 'nullable|in:AC,Non AC',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $buses = Bus::query()
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = $this->likeTerm((string) $request->query('search'));
                $query->where(function ($inner) use ($term) {
                    $inner->where('coach_number', 'like', $term)
                        ->orWhere('operator_name', 'like', $term);
                });
            })
            ->when($request->filled('coach_type'), function ($query) use ($request) {
                $query->where('coach_type', $request->query('coach_type'));
            })
            ->orderBy('operator_name')
            ->paginate($this->perPage($request))
            ->through(fn (Bus $bus) => $this->formatBus($bus));

        return response()->json($buses);
    }

    public function show(int $id): JsonResponse
    {
        return $this->itemResponse($this->formatBus(Bus::findOrFail($id)));
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate($this->rules());

        $data = $request->only('operator_name', 'coach_number', 'coach_type', 'total_seats', 'seat_layout');

        $grid = $this->seatLayoutGrid($request);
        if ($grid !== null) {
            $data['seat_layout_grid'] = $grid;
        }

        $logo = $this->logoToBase64($request);
        if ($logo !== null) {
            $data['logo_url'] = $logo;
        }

        $bus = Bus::create($data);

        return $this->itemResponse($this->formatBus($bus), 'Bus fleet registered successfully.', 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $bus = Bus::findOrFail($id);

        $request->validate($this->rules($id));

        $data = $request->only('operator_name', 'coach_number', 'coach_type', 'total_seats', 'seat_layout');

        // An explicit (even empty) seat grid replaces the stored one.
        if ($request->has('seat_layout_grid')) {
            $data['seat_layout_grid'] = $this->seatLayoutGrid($request);
        }

        $logo = $this->logoToBase64($request);
        if ($logo !== null) {
            $data['logo_url'] = $logo;
        } elseif ($request->input('logo_url_keep') === '0') {
            $data['logo_url'] = null;
        }
        // Otherwise keep the existing logo unchanged.

        $bus->update($data);

        return $this->itemResponse($this->formatBus($bus->fresh()), 'Coach updated successfully.');
    }

    public function destroy(int $id): JsonResponse
    {
        Bus::findOrFail($id)->delete();

        return response()->json(['message' => 'Coach deleted successfully.']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(?int $id = null): array
    {
        return [
            'operator_name' => 'required|string|max:100',
            'coach_number' => 'required|string|max:50|unique:buses,coach_number'.($id ? ','.$id : ''),
            'coach_type' => 'required|in:AC,Non AC',
            'total_seats' => 'required|integer|min:10|max:100',
            'seat_layout' => 'required|in:2+2_last5,2+2,1+2,sleeper',
            // Accepts a JSON string (Blade form) or a real array (API client).
            'seat_layout_grid' => 'nullable',
            'coach_logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp,svg|max:1024',
            'logo_url_keep' => 'nullable|in:0,1',
        ];
    }

    /**
     * Normalise the seat grid: the Blade form posts JSON text, API clients
     * may post the structure directly.
     *
     * @return array<string, mixed>|null
     */
    protected function seatLayoutGrid(Request $request): ?array
    {
        $raw = $request->input('seat_layout_grid');

        if (is_array($raw)) {
            return $raw;
        }

        if (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);

            return is_array($decoded) ? $decoded : null;
        }

        return null;
    }

    /**
     * Convert an uploaded logo into a base64 data URL, like the Blade form does.
     */
    protected function logoToBase64(Request $request): ?string
    {
        if (! $request->hasFile('coach_logo')) {
            return null;
        }

        $file = $request->file('coach_logo');
        $mime = $file->getMimeType() ?: 'image/png';
        $data = base64_encode(file_get_contents($file->getRealPath()));

        return "data:{$mime};base64,{$data}";
    }
}
