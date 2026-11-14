<?php

namespace App\Http\Controllers\Admin;

use App\Models\Bus;
use Illuminate\Http\Request;

class BusController extends BaseAdminController
{
    /**
     * Convert uploaded logo file to a base64 data URL string.
     * Returns null if no file is present.
     */
    private function logoToBase64(Request $request): ?string
    {
        if (! $request->hasFile('coach_logo')) {
            return null;
        }

        $file = $request->file('coach_logo');
        $mime = $file->getMimeType() ?: 'image/png';
        $data = base64_encode(file_get_contents($file->getRealPath()));

        return "data:{$mime};base64,{$data}";
    }

    public function store(Request $request)
    {
        $request->validate([
            'operator_name'    => 'required|string|max:100',
            'coach_number'     => 'required|string|max:50|unique:buses,coach_number',
            'coach_type'       => 'required|in:AC,Non AC',
            'total_seats'      => 'required|integer|min:10|max:100',
            'seat_layout'      => 'required|in:2+2_last5,2+2,1+2,sleeper',
            'seat_layout_grid' => 'nullable|string',
            'coach_logo'       => 'nullable|image|mimes:jpeg,png,jpg,gif,webp,svg|max:1024',
        ]);

        $data = $request->only('operator_name', 'coach_number', 'coach_type', 'total_seats', 'seat_layout');

        if ($request->filled('seat_layout_grid')) {
            $data['seat_layout_grid'] = json_decode($request->input('seat_layout_grid'), true);
        }

        $logoB64 = $this->logoToBase64($request);
        if ($logoB64) {
            $data['logo_url'] = $logoB64;
        }

        Bus::create($data);

        return $this->adminTabRedirect($request)->with('success', 'Bus fleet registered successfully!');
    }

    public function update(Request $request, $id)
    {
        $bus = Bus::findOrFail($id);

        $request->validate([
            'operator_name'    => 'required|string|max:100',
            'coach_number'     => 'required|string|max:50|unique:buses,coach_number,' . $id,
            'coach_type'       => 'required|in:AC,Non AC',
            'total_seats'      => 'required|integer|min:10|max:100',
            'seat_layout'      => 'required|in:2+2_last5,2+2,1+2,sleeper',
            'seat_layout_grid' => 'nullable|string',
            'coach_logo'       => 'nullable|image|mimes:jpeg,png,jpg,gif,webp,svg|max:1024',
        ]);

        $data = $request->only('operator_name', 'coach_number', 'coach_type', 'total_seats', 'seat_layout');

        if ($request->has('seat_layout_grid')) {
            $data['seat_layout_grid'] = $request->filled('seat_layout_grid')
                ? json_decode($request->input('seat_layout_grid'), true)
                : null;
        }

        $logoB64 = $this->logoToBase64($request);
        if ($logoB64) {
            $data['logo_url'] = $logoB64;
        } elseif ($request->input('logo_url_keep') === '0') {
            $data['logo_url'] = null;
        }
        // Otherwise keep existing logo unchanged

        $bus->update($data);

        return $this->adminTabRedirect($request)->with('success', 'Coach updated successfully!');
    }

    public function destroy(Request $request, $id)
    {
        Bus::findOrFail($id)->delete();

        return $this->adminTabRedirect($request)->with('success', 'Coach deleted successfully!');
    }
}
