<?php

namespace App\Http\Controllers\API\Admin\Concerns;

use App\Models\Bus;
use App\Models\Promotion;
use App\Models\Route;
use App\Models\Schedule;
use App\Models\Station;
use App\Models\User;

/**
 * Canonical JSON shapes for admin API responses, so the dashboard payload and
 * the per-module endpoints never drift apart.
 */
trait FormatsAdminResources
{
    /** @return array<string, mixed> */
    protected function formatStation(Station $station): array
    {
        return [
            'id' => $station->id,
            'name' => $station->name,
        ];
    }

    /** @return array<string, mixed> */
    protected function formatBus(Bus $bus): array
    {
        return [
            'id' => $bus->id,
            'operator_name' => $bus->operator_name,
            'coach_number' => $bus->coach_number,
            'coach_type' => $bus->coach_type,
            'total_seats' => (int) $bus->total_seats,
            'seat_layout' => $bus->seat_layout,
            'seat_layout_grid' => $bus->seat_layout_grid,
            'logo_url' => $bus->logo_url,
        ];
    }

    /** @return array<string, mixed> */
    protected function formatRoute(Route $route): array
    {
        return [
            'id' => $route->id,
            'departure_station_id' => $route->departure_station_id,
            'arrival_station_id' => $route->arrival_station_id,
            'from' => $route->departureStation?->name,
            'to' => $route->arrivalStation?->name,
            'distance' => $route->distance,
            'duration' => $route->duration,
            'boarding_points' => $route->boarding_points ?? [],
            'dropping_points' => $route->dropping_points ?? [],
        ];
    }

    /** @return array<string, mixed> */
    protected function formatSchedule(Schedule $schedule): array
    {
        return [
            'id' => $schedule->id,
            'bus_id' => $schedule->bus_id,
            'route_id' => $schedule->route_id,
            'departure_time' => optional($schedule->departure_time)->toIso8601String(),
            'arrival_time' => optional($schedule->arrival_time)->toIso8601String(),
            'fare' => (float) $schedule->fare,
            'blocked_seats' => $this->parseSeatList($schedule->blocked_seats),
            'bus' => $schedule->relationLoaded('bus') && $schedule->bus
                ? $this->formatBus($schedule->bus)
                : null,
            'route' => $schedule->relationLoaded('route') && $schedule->route
                ? [
                    'id' => $schedule->route->id,
                    'from' => $schedule->route->departureStation?->name,
                    'to' => $schedule->route->arrivalStation?->name,
                    'distance' => $schedule->route->distance,
                    'duration' => $schedule->route->duration,
                ]
                : null,
        ];
    }

    /** @return array<string, mixed> */
    protected function formatPromotion(Promotion $promotion): array
    {
        return [
            'id' => $promotion->id,
            'code' => $promotion->code,
            'discount_amount' => (float) $promotion->discount_amount,
            'description' => $promotion->description,
        ];
    }

    /** @return array<string, mixed> */
    protected function formatUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'is_admin' => $user->isAdmin(),
            'is_super_admin' => $user->isSuperAdmin(),
            'menu_permissions' => $user->menu_permissions,
            // Super admins and admins with a NULL list can reach every menu.
            'has_all_permissions' => $user->isSuperAdmin() || ($user->isAdmin() && is_null($user->menu_permissions)),
            'created_at' => optional($user->created_at)->toIso8601String(),
        ];
    }

    /**
     * blocked_seats is a comma separated string in the database.
     *
     * @return list<string>
     */
    protected function parseSeatList(?string $value): array
    {
        $value = trim((string) $value);

        if ($value === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $value)), fn ($seat) => $seat !== ''));
    }
}
