<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Schedule;
use App\Models\Station;
use App\Models\Bus;
use App\Models\Route;
use App\Models\Promotion;
use App\Models\SmsConfig;
use App\Services\SmsGatewayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class AdminWebController extends Controller
{
    public function __construct(protected SmsGatewayService $smsGatewayService)
    {
    }

    /**
     * Update SMS gateway configuration for customer notifications.
     */
    public function updateSmsConfigWeb(Request $request)
    {
        $validated = $request->validate([
            'gateway_name' => 'required|string|max:100',
            'api_url' => 'nullable|url|max:255',
            'api_key' => 'nullable|string|max:255',
            'sender_id' => 'nullable|string|max:50',
            'is_active' => 'nullable|in:0,1',
            'message_template' => 'nullable|string|max:500',
        ]);

        $config = SmsConfig::query()->latest('id')->first() ?? new SmsConfig();
        $config->fill([
            'gateway_name' => trim($validated['gateway_name']),
            'api_url' => $validated['api_url'] ?? null,
            'api_key' => $validated['api_key'] ?? null,
            'sender_id' => $validated['sender_id'] ?? null,
            'is_active' => ($validated['is_active'] ?? '0') === '1',
            'message_template' => $validated['message_template'] ?? null,
        ]);
        $config->save();

        return redirect()->back()->with('success', 'SMS gateway configuration saved successfully!');
    }

    // Manual creation store methods for Blade Web Interface

    public function storeStationWeb(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100|unique:stations,name',
            'district' => 'nullable|string|max:100'
        ]);

        Station::create([
            'name' => strtoupper(trim($request->input('name'))),
            'district' => trim($request->input('district'))
        ]);

        return redirect()->back()->with('success', 'Station terminal created successfully!');
    }

    public function updateStationWeb(Request $request, $id)
    {
        $station = Station::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:100|unique:stations,name,' . $id,
            'district' => 'nullable|string|max:100'
        ]);

        $station->update([
            'name' => strtoupper(trim($request->input('name'))),
            'district' => trim($request->input('district'))
        ]);

        return redirect()->back()->with('success', 'Station terminal updated successfully!');
    }

    public function destroyStationWeb($id)
    {
        $station = Station::findOrFail($id);

        if ($station->departureRoutes()->exists() || $station->arrivalRoutes()->exists()) {
            return redirect()->back()->withErrors(['message' => 'Cannot delete station — it is linked to existing routes.']);
        }

        $station->delete();

        return redirect()->back()->with('success', 'Station terminal deleted successfully!');
    }

    public function storeBusWeb(Request $request)
    {
        $request->validate([
            'operator_name' => 'required|string|max:100',
            'coach_number' => 'required|string|max:50|unique:buses,coach_number',
            'coach_type' => 'required|in:AC,Non AC',
            'total_seats' => 'required|integer|min:10|max:100'
        ]);

        Bus::create($request->only('operator_name', 'coach_number', 'coach_type', 'total_seats'));

        return redirect()->back()->with('success', 'Bus fleet registered successfully!');
    }

    public function updateBusWeb(Request $request, $id)
    {
        $bus = Bus::findOrFail($id);

        $request->validate([
            'operator_name' => 'required|string|max:100',
            'coach_number' => 'required|string|max:50|unique:buses,coach_number,' . $id,
            'coach_type' => 'required|in:AC,Non AC',
            'total_seats' => 'required|integer|min:10|max:100'
        ]);

        $bus->update($request->only('operator_name', 'coach_number', 'coach_type', 'total_seats'));

        return redirect()->back()->with('success', 'Coach updated successfully!');
    }

    public function destroyBusWeb($id)
    {
        Bus::findOrFail($id)->delete();

        return redirect()->back()->with('success', 'Coach deleted successfully!');
    }

    public function storeRouteWeb(Request $request)
    {
        $request->validate([
            'departure_station_id' => 'required|exists:stations,id',
            'arrival_station_id' => 'required|exists:stations,id|different:departure_station_id',
            'distance' => 'nullable|string|max:50',
            'duration' => 'nullable|string|max:50'
        ]);

        $exists = Route::where('departure_station_id', $request->input('departure_station_id'))
            ->where('arrival_station_id', $request->input('arrival_station_id'))
            ->first();

        if ($exists) {
            return redirect()->back()->withInput()->withErrors(['departure_station_id' => 'A route between these stations already exists.']);
        }

        Route::create($request->only('departure_station_id', 'arrival_station_id', 'distance', 'duration'));

        return redirect()->back()->with('success', 'Transport line route connection configured successfully!');
    }

    public function updateRouteWeb(Request $request, $id)
    {
        $route = Route::findOrFail($id);

        $request->validate([
            'departure_station_id' => 'required|exists:stations,id',
            'arrival_station_id' => 'required|exists:stations,id|different:departure_station_id',
            'distance' => 'nullable|string|max:50',
            'duration' => 'nullable|string|max:50'
        ]);

        $exists = Route::where('departure_station_id', $request->input('departure_station_id'))
            ->where('arrival_station_id', $request->input('arrival_station_id'))
            ->where('id', '!=', $id)
            ->first();

        if ($exists) {
            return redirect()->back()->withInput()->withErrors(['departure_station_id' => 'A route between these stations already exists.']);
        }

        $route->update($request->only('departure_station_id', 'arrival_station_id', 'distance', 'duration'));

        return redirect()->back()->with('success', 'Route updated successfully!');
    }

    public function destroyRouteWeb($id)
    {
        Route::findOrFail($id)->delete();

        return redirect()->back()->with('success', 'Route deleted successfully!');
    }

    public function storeScheduleWeb(Request $request)
    {
        $request->validate([
            'bus_id' => 'required|exists:buses,id',
            'route_id' => 'required|exists:routes,id',
            'departure_time' => 'required|date|after_or_equal:today',
            'arrival_time' => 'required|date|after:departure_time',
            'fare' => 'required|numeric|min:0'
        ]);

        Schedule::create($request->only('bus_id', 'route_id', 'departure_time', 'arrival_time', 'fare'));

        return redirect()->back()->with('success', 'Schedule run registered successfully!');
    }

    public function updateScheduleWeb(Request $request, $id)
    {
        $schedule = Schedule::findOrFail($id);

        $request->validate([
            'bus_id' => 'required|exists:buses,id',
            'route_id' => 'required|exists:routes,id',
            'departure_time' => 'required|date',
            'arrival_time' => 'required|date|after:departure_time',
            'fare' => 'required|numeric|min:0'
        ]);

        $schedule->update($request->only('bus_id', 'route_id', 'departure_time', 'arrival_time', 'fare'));

        return redirect()->back()->with('success', 'Schedule updated successfully!');
    }

    public function destroyScheduleWeb($id)
    {
        Schedule::findOrFail($id)->delete();

        return redirect()->back()->with('success', 'Schedule deleted successfully!');
    }

    public function storePromotionWeb(Request $request)
    {
        $request->validate([
            'code' => 'required|string|max:50|unique:promotions,code',
            'discount_amount' => 'required|numeric|min:0',
            'description' => 'required|string|max:255'
        ]);

        Promotion::create([
            'code' => strtoupper(trim($request->input('code'))),
            'discount_amount' => $request->input('discount_amount'),
            'description' => trim($request->input('description'))
        ]);

        return redirect()->back()->with('success', 'Promotion code coupon generated successfully!');
    }

    public function updatePromotionWeb(Request $request, $id)
    {
        $promotion = Promotion::findOrFail($id);

        $request->validate([
            'code' => 'required|string|max:50|unique:promotions,code,' . $id,
            'discount_amount' => 'required|numeric|min:0',
            'description' => 'required|string|max:255'
        ]);

        $promotion->update([
            'code' => strtoupper(trim($request->input('code'))),
            'discount_amount' => $request->input('discount_amount'),
            'description' => trim($request->input('description'))
        ]);

        return redirect()->back()->with('success', 'Coupon updated successfully!');
    }

    public function destroyPromotionWeb($id)
    {
        Promotion::findOrFail($id)->delete();

        return redirect()->back()->with('success', 'Coupon deleted successfully!');
    }

    public function storeBookingWeb(Request $request)
    {
        $request->validate([
            'schedule_id' => 'required|exists:schedules,id',
            'passenger_name' => 'required|string|max:100',
            'passenger_phone' => 'required|string|max:20',
            'passenger_email' => 'required|email|max:100',
            'seat_numbers' => 'required|string|max:255',
            'payment_method' => 'required|string|max:50',
            'total_fare' => 'required|numeric|min:0',
            'status' => 'required|in:PAID,CANCEL_REQUESTED,CANCELLED'
        ]);

        $booking = Booking::create([
            'schedule_id' => $request->input('schedule_id'),
            'passenger_name' => $request->input('passenger_name'),
            'passenger_phone' => $request->input('passenger_phone'),
            'passenger_email' => $request->input('passenger_email'),
            'seat_numbers' => $request->input('seat_numbers'),
            'total_fare' => $request->input('total_fare'),
            'payment_method' => $request->input('payment_method'),
            'status' => $request->input('status'),
        ]);

        if ($request->input('status') === 'PAID') {
            $this->smsGatewayService->sendBookingVerification($booking);
        }

        return redirect()->back()->with('success', 'Booking created successfully!');
    }

    public function updateBookingWeb(Request $request, $id)
    {
        $booking = Booking::findOrFail($id);

        $request->validate([
            'passenger_name' => 'required|string|max:100',
            'passenger_phone' => 'required|string|max:20',
            'passenger_email' => 'required|email|max:100',
            'seat_numbers' => 'required|string|max:255',
            'total_fare' => 'required|numeric|min:0',
            'status' => 'required|in:PAID,CANCEL_REQUESTED,CANCELLED'
        ]);

        $booking->update($request->only(
            'passenger_name',
            'passenger_phone',
            'passenger_email',
            'seat_numbers',
            'total_fare',
            'status'
        ));

        return redirect()->back()->with('success', 'Booking updated successfully!');
    }

    public function destroyBookingWeb($id)
    {
        Booking::findOrFail($id)->delete();

        return redirect()->back()->with('success', 'Booking deleted successfully!');
    }

    /**
     * Cancel booking and release seats from web view.
     */
    public function cancelBookingWeb($id)
    {
        $booking = Booking::find($id);

        if (!$booking) {
            return redirect()->back()->withErrors(['message' => 'Booking not found.']);
        }

        if ($booking->status === 'CANCELLED') {
            return redirect()->back()->withErrors(['message' => 'Ticket is already cancelled.']);
        }

        $booking->update(['status' => 'CANCELLED']);

        return redirect()->back()->with('success', 'Reservation successfully cancelled and seat released!');
    }

    /**
     * Approve a customer cancel request from admin dashboard.
     */
    public function approveCancelRequestWeb($id)
    {
        $booking = Booking::find($id);

        if (!$booking) {
            return redirect()->back()->withErrors(['message' => 'Booking not found.']);
        }

        if ($booking->status !== 'CANCEL_REQUESTED') {
            return redirect()->back()->withErrors(['message' => 'This booking has no pending cancellation request.']);
        }

        $booking->update(['status' => 'CANCELLED']);

        return redirect()->back()->with('success', 'Cancellation request approved successfully. Booking is now cancelled.');
    }

    /**
     * Programmatical database operations via Artisan triggers.
     */
    public function systemMigrate()
    {
        try {
            Artisan::call('migrate', ['--force' => true]);
            $output = Artisan::output();
            return redirect()->back()->with('console_output', $output)->with('success', 'Database migrations executed successfully!');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['system' => 'Failed to migrate: ' . $e->getMessage()]);
        }
    }

    public function systemSeed()
    {
        try {
            Artisan::call('db:seed', ['--force' => true]);
            $output = Artisan::output();
            return redirect()->back()->with('console_output', $output)->with('success', 'Seeder execution completed successfully!');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['system' => 'Failed to seed: ' . $e->getMessage()]);
        }
    }

    public function systemMigrateFreshSeed()
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
