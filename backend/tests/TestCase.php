<?php

namespace Tests;

use App\Models\License;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;

abstract class TestCase extends BaseTestCase
{
    /**
     * Admin routes (web + API writes) sit behind EnsureLicenseIsActivated, so
     * any feature test that touches them needs a verified license row.
     */
    protected function activateLicense(string $purchaseCode = 'test-license'): void
    {
        License::create([
            'purchase_code' => $purchaseCode,
            'license_type' => 'Regular License',
            'is_verified' => true,
            'verified_at' => now(),
        ]);

        Cache::forget('license_verified');
    }
}
