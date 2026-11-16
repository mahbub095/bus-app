<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // ── Super Admin ───────────────────────────────────────────────────────
        // Has all permissions automatically (menu_permissions ignored)
        User::updateOrCreate(
            ['email' => 'superadmin@journeygo.com'],
            [
                'name'             => 'JourneyGo Super Admin',
                'password'         => bcrypt('password123'),
                'role'             => 'super_admin',
                'menu_permissions' => null,
                'email_verified_at'=> now(),
            ]
        );

        // ── Admin ─────────────────────────────────────────────────────────────
        // menu_permissions = null → all menus accessible
        User::updateOrCreate(
            ['email' => 'admin@journeygo.com'],
            [
                'name'             => 'JourneyGo Admin',
                'password'         => bcrypt('password123'),
                'role'             => 'admin',
                'menu_permissions' => null,
                'email_verified_at'=> now(),
            ]
        );

        // ── Customer / User ───────────────────────────────────────────────────
        // role = 'user' → no admin access at all
        User::updateOrCreate(
            ['email' => 'user@journeygo.com'],
            [
                'name'             => 'Demo Customer',
                'password'         => bcrypt('password123'),
                'role'             => 'user',
                'menu_permissions' => null,
                'email_verified_at'=> now(),
            ]
        );
    }
}
