<?php

namespace App\Http\Controllers\Admin;

use App\Services\LicenseService;
use Illuminate\Http\Request;

/**
 * Admin panel — License management.
 *
 * Accessible only by super admins via the Settings tab.
 */
class LicenseController extends BaseAdminController
{
    public function __construct(protected LicenseService $licenseService) {}

    /**
     * Return license information as JSON for the admin dashboard AJAX call.
     */
    public function info()
    {
        return response()->json($this->licenseService->info());
    }

    /**
     * Re-verify an existing purchase code (e.g. after domain migration).
     */
    public function reVerify(Request $request)
    {
        $validated = $request->validate([
            'purchase_code'  => 'required|string|max:100',
            'personal_token' => 'required|string|max:500',
        ]);

        $result = $this->licenseService->verify(
            $validated['purchase_code'],
            $validated['personal_token']
        );

        if ($result['success']) {
            return redirect()->route('admin.dashboard')
                ->withInput(['admin_tab' => 'license'])
                ->with('success', $result['message']);
        }

        return redirect()->route('admin.dashboard')
            ->withInput(['admin_tab' => 'license'])
            ->withErrors(['purchase_code' => $result['message']]);
    }
}
