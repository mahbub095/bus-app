<?php

namespace App\Http\Controllers\Admin;

use App\Services\GatewaySettingsService;
use Illuminate\Http\Request;

class GatewaySettingsController extends BaseAdminController
{
    public function __construct(protected GatewaySettingsService $gatewaySettingsService)
    {
    }

    public function updateSms(Request $request)
    {
        $this->gatewaySettingsService->updateSms($request);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['success' => true, 'message' => 'SMS gateway settings updated successfully.']);
        }

        return $this->redirectToGatewaysTab('SMS gateway settings updated successfully.');
    }

    public function updateMail(Request $request)
    {
        $this->gatewaySettingsService->updateMail($request);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['success' => true, 'message' => 'Mail SMTP configurations updated successfully.']);
        }

        return $this->redirectToGatewaysTab('Mail SMTP configurations updated successfully.');
    }

    public function updateZinipay(Request $request)
    {
        $this->gatewaySettingsService->updateZinipay($request);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['success' => true, 'message' => 'ZiniPay gateway configurations updated successfully.']);
        }

        return $this->redirectToGatewaysTab('ZiniPay gateway configurations updated successfully.');
    }

    public function testSms(Request $request)
    {
        $result = $this->gatewaySettingsService->sendTestSms($request);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json($result, $result['success'] ? 200 : 422);
        }

        if ($result['success']) {
            return $this->redirectToGatewaysTab('Test SMS sent successfully: ' . $result['message']);
        }

        return redirect()->route('admin.dashboard')
            ->withErrors(['sms_test' => 'Failed to send test SMS: ' . $result['message']])
            ->withInput(['admin_tab' => 'gateways']);
    }

    public function testEmail(Request $request)
    {
        $result = $this->gatewaySettingsService->sendTestEmail($request);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json($result, $result['success'] ? 200 : 422);
        }

        if ($result['success']) {
            return $this->redirectToGatewaysTab($result['message']);
        }

        return redirect()->route('admin.dashboard')
            ->withErrors(['email_test' => $result['message']])
            ->withInput(['admin_tab' => 'gateways']);
    }

    private function redirectToGatewaysTab(string $successMessage)
    {
        return redirect()->route('admin.dashboard')
            ->with('success', $successMessage)
            ->withInput(['admin_tab' => 'gateways']);
    }
}
