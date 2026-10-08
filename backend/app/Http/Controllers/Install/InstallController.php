<?php

namespace App\Http\Controllers\Install;

use App\Http\Controllers\Controller;
use App\Services\LicenseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use App\Models\User;

/**
 * Handles the step-by-step installation wizard.
 *
 * Steps:
 *   1. License verification (Envato purchase code)
 *   2. Database connection test
 *   3. Admin account creation
 *   4. Final setup (migrations + seeding)
 */
class InstallController extends Controller
{
    public function __construct(protected LicenseService $licenseService) {}

    // -------------------------------------------------------------------------
    // Step 1 — License
    // -------------------------------------------------------------------------

    /**
     * Show the installation welcome / license entry page.
     */
    public function showLicense()
    {
        if ($this->licenseService->isActivated()) {
            return redirect('/admin');
        }

        return view('install.license');
    }

    /**
     * Verify the submitted purchase code against the Envato API.
     */
    public function verifyLicense(Request $request)
    {
        $request->validate([
            'purchase_code'  => 'required|string|max:100',
            'personal_token' => 'required|string|max:500',
        ]);

        $result = $this->licenseService->verify(
            $request->input('purchase_code'),
            $request->input('personal_token')
        );

        if (! $result['success']) {
            return back()->withErrors(['purchase_code' => $result['message']])->withInput();
        }

        return redirect('/install/database')->with('success', $result['message']);
    }

    // -------------------------------------------------------------------------
    // Step 2 — Database
    // -------------------------------------------------------------------------

    public function showDatabase()
    {
        if (! $this->licenseService->isActivated()) {
            return redirect('/install');
        }

        return view('install.database');
    }

    public function saveDatabase(Request $request)
    {
        $request->validate([
            'db_host'     => 'required|string|max:255',
            'db_port'     => 'required|integer|min:1|max:65535',
            'db_database' => 'required|string|max:255',
            'db_username' => 'required|string|max:255',
            'db_password' => 'nullable|string|max:255',
        ]);

        // Test connection before writing
        try {
            $testPdo = new \PDO(
                "mysql:host={$request->db_host};port={$request->db_port};dbname={$request->db_database}",
                $request->db_username,
                $request->db_password ?? ''
            );
            $testPdo = null;
        } catch (\PDOException $e) {
            return back()
                ->withErrors(['db_host' => 'Database connection failed: ' . $e->getMessage()])
                ->withInput();
        }

        // Write database credentials to .env
        app(\App\Services\EnvFileWriter::class)->set([
            'DB_HOST'     => $request->db_host,
            'DB_PORT'     => (string) $request->db_port,
            'DB_DATABASE' => $request->db_database,
            'DB_USERNAME' => $request->db_username,
            'DB_PASSWORD' => $request->db_password ?? '',
        ]);

        return redirect('/install/admin')->with('success', 'Database connection verified.');
    }

    // -------------------------------------------------------------------------
    // Step 3 — Admin account
    // -------------------------------------------------------------------------

    public function showAdmin()
    {
        if (! $this->licenseService->isActivated()) {
            return redirect('/install');
        }

        return view('install.admin');
    }

    public function saveAdmin(Request $request)
    {
        $request->validate([
            'name'     => 'required|string|max:100',
            'email'    => 'required|email|max:100',
            'password' => 'required|string|min:8|confirmed',
        ]);

        return redirect('/install/finalize')->with([
            'admin_name'     => $request->name,
            'admin_email'    => $request->email,
            'admin_password' => $request->password,
        ]);
    }

    // -------------------------------------------------------------------------
    // Step 4 — Finalize
    // -------------------------------------------------------------------------

    public function finalize(Request $request)
    {
        if (! $this->licenseService->isActivated()) {
            return redirect('/install');
        }

        if (! $request->session()->has('admin_email')) {
            return redirect('/install/admin')->withErrors(['message' => 'Please complete the admin setup step first.']);
        }

        try {
            // Run migrations
            Artisan::call('migrate', ['--force' => true]);

            // Create super admin
            User::updateOrCreate(
                ['email' => $request->session()->get('admin_email')],
                [
                    'name'     => $request->session()->get('admin_name'),
                    'password' => Hash::make($request->session()->get('admin_password')),
                    'role'     => 'super_admin',
                ]
            );

            // Clear all caches
            Artisan::call('config:clear');
            Artisan::call('cache:clear');

            $request->session()->forget(['admin_name', 'admin_email', 'admin_password']);

            return view('install.complete');

        } catch (\Throwable $e) {
            return back()->withErrors(['message' => 'Installation failed: ' . $e->getMessage()]);
        }
    }
}
