<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class SystemController extends Controller
{
    public function migrate(Request $request)
    {
        try {
            Artisan::call('migrate', ['--force' => true]);
            $output = Artisan::output();
            return $this->adminTabRedirect($request)->with('console_output', $output)->with('success', 'Database migrations executed successfully!');
        } catch (\Exception $e) {
            return $this->adminTabRedirect($request)->withErrors(['system' => 'Failed to migrate: ' . $e->getMessage()]);
        }
    }

    public function seed(Request $request)
    {
        try {
            Artisan::call('db:seed', ['--force' => true]);
            $output = Artisan::output();
            return $this->adminTabRedirect($request)->with('console_output', $output)->with('success', 'Seeder execution completed successfully!');
        } catch (\Exception $e) {
            return $this->adminTabRedirect($request)->withErrors(['system' => 'Failed to seed: ' . $e->getMessage()]);
        }
    }

    public function migrateFreshSeed(Request $request)
    {
        try {
            Artisan::call('migrate:fresh', ['--seed' => true, '--force' => true]);
            $output = Artisan::output();
            return $this->adminTabRedirect($request)->with('console_output', $output)->with('success', 'Fresh migration and seeding completed successfully!');
        } catch (\Exception $e) {
            return $this->adminTabRedirect($request)->withErrors(['system' => 'Failed to fresh migrate & seed: ' . $e->getMessage()]);
        }
    }
}
