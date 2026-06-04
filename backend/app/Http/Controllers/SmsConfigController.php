<?php

namespace App\Http\Controllers;

use App\Models\SmsConfig;
use App\Services\SmsGatewayService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SmsConfigController extends Controller
{
    public function __construct(protected SmsGatewayService $smsGatewayService)
    {
    }

    public function update(Request $request)
    {
        $isActive = ($request->input('is_active', '0')) === '1';

        $validated = $request->validate([
            'gateway_name' => 'required|string|max:100',
            'gateway_driver' => ['required', Rule::in(['bulksmsbd', 'custom', 'get_query'])],
            'api_url' => [$isActive ? 'required' : 'nullable', 'url', 'max:255'],
            'api_key' => [$isActive ? 'required' : 'nullable', 'string', 'max:255'],
            'sender_id' => [$isActive ? 'required' : 'nullable', 'string', 'max:50'],
            'is_active' => 'nullable|in:0,1',
            'message_template' => 'nullable|string|max:500',
        ]);

        $config = SmsConfig::query()->latest('id')->first() ?? new SmsConfig();
        $config->fill([
            'gateway_name' => trim($validated['gateway_name']),
            'gateway_driver' => $validated['gateway_driver'],
            'api_url' => $validated['api_url'] ?? null,
            'api_key' => $validated['api_key'] ?? null,
            'sender_id' => $validated['sender_id'] ?? null,
            'is_active' => $isActive,
            'message_template' => $validated['message_template'] ?? null,
        ]);
        $config->save();

        return $this->adminTabRedirect($request)->with('success', 'SMS gateway configuration saved successfully!');
    }

    public function testSend(Request $request)
    {
        $validated = $request->validate([
            'test_phone' => 'required|string|max:20',
        ]);

        $result = $this->smsGatewayService->sendTestMessage($validated['test_phone']);

        if ($result['success']) {
            return $this->adminTabRedirect($request)->with('success', $result['message']);
        }

        return $this->adminTabRedirect($request)->withErrors([
            'sms_test' => $result['message'],
        ]);
    }
}
