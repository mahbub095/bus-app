<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\SmsConfig;
use Illuminate\Http\Request;

class SmsConfigController extends Controller
{
    public function update(Request $request)
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
}
