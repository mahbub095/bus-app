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

        return $this->redirectToGatewaysTab('SMS gateway settings updated successfully.');
    }

    public function updateMail(Request $request)
    {
        $this->gatewaySettingsService->updateMail($request);

        return $this->redirectToGatewaysTab('Mail SMTP configurations updated successfully.');
    }

    public function updateZinipay(Request $request)
    {
        $this->gatewaySettingsService->updateZinipay($request);

        return $this->redirectToGatewaysTab('ZiniPay gateway configurations updated successfully.');
    }

    public function testSms(Request $request)
    {
        $result = $this->gatewaySettingsService->sendTestSms($request);

        if ($result['success']) {
            return $this->redirectToGatewaysTab('Test SMS sent successfully: '.$result['message']);
        }

        return redirect()->route('admin.dashboard')
            ->withErrors(['sms_test' => 'Failed to send test SMS: '.$result['message']])
            ->withInput(['admin_tab' => 'gateways']);
    }

    public function testEmail(Request $request)
    {
        $result = $this->gatewaySettingsService->sendTestEmail($request);

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
