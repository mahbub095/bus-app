<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Default value for the admin-configurable session expiry
 * (Site Settings → Session & Security).
 */
return new class extends Migration
{
    private const KEY = 'admin_session_lifetime';

    private const DEFAULT_MINUTES = '120';

    public function up(): void
    {
        if (! Schema::hasTable('site_settings')) {
            return;
        }

        $exists = DB::table('site_settings')->where('key', self::KEY)->exists();

        if (! $exists) {
            DB::table('site_settings')->insert([
                'key'        => self::KEY,
                'value'      => self::DEFAULT_MINUTES,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('site_settings')) {
            return;
        }

        DB::table('site_settings')->where('key', self::KEY)->delete();
    }
};
