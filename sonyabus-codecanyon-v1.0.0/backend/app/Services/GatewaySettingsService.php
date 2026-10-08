<?php

namespace App\Services;

use App\Mail\SystemTestMail;
use App\Models\SiteSetting;
use App\Models\SmsConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

/**
 * SMS, mail, and payment gateway settings for the admin panel.
 */
class GatewaySettingsService
{
    private const DEFAULT_TEST_SMS = 'SonyaBus SMS Test: Gateway settings are working correctly.';

    public function __construct(
        protected EnvFileWriter $envFileWriter,
        protected SmsGatewayService $smsGatewayService,
    ) {
    }

    public function updateSms(Request $request): void
    {
        $validated = $request->validate([
            'gateway_name' => 'required|string|max:255',
            'gateway_driver' => 'required|string|max:50',
            'api_url' => 'required|url',
            'api_key' => 'required|string|max:500',
            'sender_id' => 'nullable|string|max:100',
            'message_template' => 'nullable|string',
            'is_active' => 'nullable|string',
        ]);

        $isActive = $request->has('is_active');

        SmsConfig::updateOrCreate([], [
            'gateway_name' => $validated['gateway_name'],
            'gateway_driver' => $validated['gateway_driver'],
            'api_url' => $validated['api_url'],
            'api_key' => $validated['api_key'],
            'sender_id' => $validated['sender_id'] ?? null,
            'message_template' => $validated['message_template'] ?? null,
            'is_active' => $isActive,
        ]);

        $this->envFileWriter->set([
            'SMS_GATEWAY_NAME' => $validated['gateway_name'],
            'SMS_GATEWAY_DRIVER' => $validated['gateway_driver'],
            'SMS_GATEWAY_API_URL' => $validated['api_url'],
            'SMS_GATEWAY_API_KEY' => $validated['api_key'],
            'SMS_GATEWAY_SENDER_ID' => $validated['sender_id'] ?? '',
            'SMS_GATEWAY_ACTIVE' => $isActive ? 'true' : 'false',
            'SMS_GATEWAY_MESSAGE_TEMPLATE' => $validated['message_template'] ?? '',
        ]);
    }

    public function updateMail(Request $request): void
    {
        $validated = $request->validate([
            'mail_mailer' => 'required|string|in:smtp,log',
            'mail_host' => 'required_if:mail_mailer,smtp|nullable|string|max:255',
            'mail_port' => 'required_if:mail_mailer,smtp|nullable|integer|min:1|max:65535',
            'mail_username' => 'nullable|string|max:255',
            'mail_password' => 'nullable|string|max:255',
            'mail_encryption' => 'nullable|string|in:tls,ssl,none',
            'mail_from_address' => 'required|email|max:255',
            'mail_from_name' => 'required|string|max:255',
        ]);

        $encryption = $this->normalizeMailEncryption($validated['mail_encryption'] ?? null);

        SiteSetting::setMany([
            'mail_mailer' => $validated['mail_mailer'],
            'mail_host' => $validated['mail_host'] ?? null,
            'mail_port' => $validated['mail_port'] ?? null,
            'mail_username' => $validated['mail_username'] ?? null,
            'mail_password' => $validated['mail_password'] ?? null,
            'mail_encryption' => $encryption,
            'mail_from_address' => $validated['mail_from_address'],
            'mail_from_name' => $validated['mail_from_name'],
        ]);

        $this->envFileWriter->set([
            'MAIL_MAILER' => $validated['mail_mailer'],
            'MAIL_HOST' => $validated['mail_host'] ?? '',
            'MAIL_PORT' => (string) ($validated['mail_port'] ?? ''),
            'MAIL_USERNAME' => $validated['mail_username'] ?? '',
            'MAIL_PASSWORD' => $validated['mail_password'] ?? '',
            'MAIL_ENCRYPTION' => $encryption ?? '',
            'MAIL_FROM_ADDRESS' => $validated['mail_from_address'],
            'MAIL_FROM_NAME' => $validated['mail_from_name'],
        ]);
    }

    public function updateZinipay(Request $request): void
    {
        $validated = $request->validate([
            'zinipay_api_key' => 'required|string|max:255',
            'zinipay_base_url' => 'required|url',
        ]);

        SiteSetting::setMany([
            'zinipay_api_key' => $validated['zinipay_api_key'],
            'zinipay_base_url' => $validated['zinipay_base_url'],
        ]);

        $this->envFileWriter->set([
            'ZINIPAY_API_KEY' => $validated['zinipay_api_key'],
            'ZINIPAY_BASE_URL' => $validated['zinipay_base_url'],
        ]);
    }

    /** @return array{success: bool, message: string} */
    public function sendTestSms(Request $request): array
    {
        $validated = $request->validate([
            'test_phone' => 'required|string|max:20',
            'test_message' => 'nullable|string|max:500',
        ]);

        $message = $validated['test_message'] ?: self::DEFAULT_TEST_SMS;

        return $this->smsGatewayService->sendTestMessage($validated['test_phone'], $message);
    }

    /** @return array{success: bool, message: string} */
    public function sendTestEmail(Request $request): array
    {
        $validated = $request->validate([
            'test_email' => 'required|email|max:255',
        ]);

        try {
            Mail::to($validated['test_email'])->send(new SystemTestMail());

            return [
                'success' => true,
                'message' => 'Test email dispatched successfully to '.$validated['test_email'].'. Please check your inbox.',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'SMTP mail delivery failed: '.$e->getMessage(),
            ];
        }
    }

    private function normalizeMailEncryption(?string $encryption): ?string
    {
        return $encryption === 'none' ? null : $encryption;
    }
}
