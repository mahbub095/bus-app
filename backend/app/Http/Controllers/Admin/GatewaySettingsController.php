<?php

namespace App\Http\Controllers\Admin;

use App\Models\SmsConfig;
use App\Models\SiteSetting;
use App\Services\SmsGatewayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class GatewaySettingsController extends BaseAdminController
{
    public function __construct(protected SmsGatewayService $smsGatewayService)
    {
    }

    public function updateSms(Request $request)
    {
        $request->validate([
            'gateway_name' => 'required|string|max:255',
            'gateway_driver' => 'required|string|in:smsnetbd,bulksmsbd,custom,get_query',
            'api_url' => 'required|url',
            'api_key' => 'required|string|max:500',
            'sender_id' => 'nullable|string|max:100',
            'message_template' => 'nullable|string',
            'is_active' => 'nullable|string',
        ]);

        SmsConfig::updateOrCreate(
            [], // Update first config, or create new if empty
            [
                'gateway_name' => $request->input('gateway_name'),
                'gateway_driver' => $request->input('gateway_driver'),
                'api_url' => $request->input('api_url'),
                'api_key' => $request->input('api_key'),
                'sender_id' => $request->input('sender_id'),
                'message_template' => $request->input('message_template'),
                'is_active' => $request->has('is_active') ? true : false,
            ]
        );

        $this->updateEnvFile([
            'SMS_GATEWAY_NAME' => $request->input('gateway_name'),
            'SMS_GATEWAY_DRIVER' => $request->input('gateway_driver'),
            'SMS_GATEWAY_API_URL' => $request->input('api_url'),
            'SMS_GATEWAY_API_KEY' => $request->input('api_key'),
            'SMS_GATEWAY_SENDER_ID' => $request->input('sender_id') ?? '',
            'SMS_GATEWAY_ACTIVE' => $request->has('is_active') ? 'true' : 'false',
            'SMS_GATEWAY_MESSAGE_TEMPLATE' => $request->input('message_template') ?? '',
        ]);

        return redirect()->route('admin.dashboard')
            ->with('success', 'SMS gateway settings updated successfully.')
            ->withInput(['admin_tab' => 'gateways']);
    }

    public function updateMail(Request $request)
    {
        $request->validate([
            'mail_mailer' => 'required|string|in:smtp,log',
            'mail_host' => 'required_if:mail_mailer,smtp|nullable|string|max:255',
            'mail_port' => 'required_if:mail_mailer,smtp|nullable|integer|min:1|max:65535',
            'mail_username' => 'nullable|string|max:255',
            'mail_password' => 'nullable|string|max:255',
            'mail_encryption' => 'nullable|string|in:tls,ssl,none',
            'mail_from_address' => 'required|email|max:255',
            'mail_from_name' => 'required|string|max:255',
        ]);

        SiteSetting::setMany([
            'mail_mailer' => $request->input('mail_mailer'),
            'mail_host' => $request->input('mail_host'),
            'mail_port' => $request->input('mail_port'),
            'mail_username' => $request->input('mail_username'),
            'mail_password' => $request->input('mail_password'),
            'mail_encryption' => $request->input('mail_encryption') === 'none' ? null : $request->input('mail_encryption'),
            'mail_from_address' => $request->input('mail_from_address'),
            'mail_from_name' => $request->input('mail_from_name'),
        ]);

        $this->updateEnvFile([
            'MAIL_MAILER' => $request->input('mail_mailer'),
            'MAIL_HOST' => $request->input('mail_host') ?? '',
            'MAIL_PORT' => $request->input('mail_port') ?? '',
            'MAIL_USERNAME' => $request->input('mail_username') ?? '',
            'MAIL_PASSWORD' => $request->input('mail_password') ?? '',
            'MAIL_ENCRYPTION' => $request->input('mail_encryption') === 'none' ? '' : ($request->input('mail_encryption') ?? ''),
            'MAIL_FROM_ADDRESS' => $request->input('mail_from_address'),
            'MAIL_FROM_NAME' => $request->input('mail_from_name'),
        ]);

        return redirect()->route('admin.dashboard')
            ->with('success', 'Mail SMTP configurations updated successfully.')
            ->withInput(['admin_tab' => 'gateways']);
    }

    public function updateZinipay(Request $request)
    {
        $request->validate([
            'zinipay_api_key' => 'required|string|max:255',
            'zinipay_base_url' => 'required|url',
        ]);

        SiteSetting::setMany([
            'zinipay_api_key' => $request->input('zinipay_api_key'),
            'zinipay_base_url' => $request->input('zinipay_base_url'),
        ]);

        $this->updateEnvFile([
            'ZINIPAY_API_KEY' => $request->input('zinipay_api_key'),
            'ZINIPAY_BASE_URL' => $request->input('zinipay_base_url'),
        ]);

        return redirect()->route('admin.dashboard')
            ->with('success', 'ZiniPay gateway configurations updated successfully.')
            ->withInput(['admin_tab' => 'gateways']);
    }

    public function testSms(Request $request)
    {
        $request->validate([
            'test_phone' => 'required|string|max:20',
            'test_message' => 'nullable|string|max:500',
        ]);

        $phone = $request->input('test_phone');
        $message = $request->input('test_message') ?: 'SonyaBus SMS Test: Gateway settings are working correctly.';

        $result = $this->smsGatewayService->sendTestMessage($phone, $message);

        if ($result['success']) {
            return redirect()->route('admin.dashboard')
                ->with('success', 'Test SMS sent successfully: ' . $result['message'])
                ->withInput(['admin_tab' => 'gateways']);
        }

        return redirect()->route('admin.dashboard')
            ->withErrors(['sms_test' => 'Failed to send test SMS: ' . $result['message']])
            ->withInput(['admin_tab' => 'gateways']);
    }

    public function testEmail(Request $request)
    {
        $request->validate([
            'test_email' => 'required|email|max:255',
        ]);

        $recipient = $request->input('test_email');

        try {
            Mail::to($recipient)->send(new \App\Mail\SystemTestMail());

            return redirect()->route('admin.dashboard')
                ->with('success', 'Test email dispatched successfully to ' . $recipient . '. Please check your inbox.')
                ->withInput(['admin_tab' => 'gateways']);
        } catch (\Exception $e) {
            return redirect()->route('admin.dashboard')
                ->withErrors(['email_test' => 'SMTP mail delivery failed: ' . $e->getMessage()])
                ->withInput(['admin_tab' => 'gateways']);
        }
    }

    protected function updateEnvFile(array $data)
    {
        $envPath = base_path('.env');
        if (!file_exists($envPath)) {
            return;
        }

        $content = file_get_contents($envPath);

        foreach ($data as $key => $value) {
            $escapedValue = str_replace('"', '\\"', $value);
            
            if (preg_match('/\s/i', $value) || str_contains($value, '{') || str_contains($value, '}')) {
                $formattedValue = '"' . $escapedValue . '"';
            } else {
                $formattedValue = $value;
            }

            $keyPattern = "/^" . preg_quote($key, '/') . "=(.*)$/m";

            if (preg_match($keyPattern, $content)) {
                $content = preg_replace($keyPattern, $key . '=' . $formattedValue, $content);
            } else {
                $content .= "\n" . $key . '=' . $formattedValue;
            }
        }

        file_put_contents($envPath, $content);
    }
}
