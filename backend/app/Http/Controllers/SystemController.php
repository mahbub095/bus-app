<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Artisan;

class SystemController extends Controller
{
    public function migrate()
    {
        try {
            Artisan::call('migrate', ['--force' => true]);
            $output = Artisan::output();
            return redirect()->back()->with('console_output', $output)->with('success', 'Database migrations executed successfully!');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['system' => 'Failed to migrate: ' . $e->getMessage()]);
        }
    }

    public function seed()
    {
        try {
            Artisan::call('db:seed', ['--force' => true]);
            $output = Artisan::output();
            return redirect()->back()->with('console_output', $output)->with('success', 'Seeder execution completed successfully!');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['system' => 'Failed to seed: ' . $e->getMessage()]);
        }
    }

    public function migrateFreshSeed()
    {
        try {
            Artisan::call('migrate:fresh', ['--seed' => true, '--force' => true]);
            $output = Artisan::output();
            return redirect()->back()->with('console_output', $output)->with('success', 'Fresh migration and seeding completed successfully!');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['system' => 'Failed to fresh migrate & seed: ' . $e->getMessage()]);
        }
    }
}
