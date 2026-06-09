# SMS Gateway Integration - SMS.NET.BD Setup

## Overview
The booking system automatically sends SMS notifications to passengers when their booking status becomes **PAID**. The SMS gateway is now configured to use **SMS.NET.BD** service.

## Architecture

### Components
1. **SmsGatewayService** (`backend/app/Services/SmsGatewayService.php`)
   - Handles all SMS sending logic
   - Supports multiple gateway drivers (BulkSMSBD, SMS.NET.BD, Custom)
   - Validates phone numbers in Bangladesh format
   - Builds dynamic SMS messages using templates

2. **SendBookingSmsNotification Job** (`backend/app/Jobs/SendBookingSmsNotification.php`)
   - Queued job for async SMS sending
   - Prevents blocking booking API responses
   - Logs success/failure for monitoring

3. **SmsConfig Model** (`backend/app/Models/SmsConfig.php`)
   - Stores gateway configuration in database
   - Allows runtime configuration changes from admin panel

4. **Database Table** (`sms_configs`)
   - `gateway_name`: Display name of the SMS provider
   - `gateway_driver`: Driver type (smsnetbd, bulksmsbd, custom, get_query)
   - `api_url`: Gateway API endpoint
   - `api_key`: Authentication key
   - `sender_id`: Optional sender identifier (not used for SMS.NET.BD)
   - `is_active`: Enable/disable SMS service
   - `message_template`: Customizable SMS template

## SMS.NET.BD Integration

### Gateway Details
- **Provider**: SMS.NET.BD
- **API Endpoint**: `https://api.sms.net.bd/sendsms`
- **Method**: POST
- **API Key**: `R754CfVe5x8oIbVBiErJaOEx1x7u5AjVXxeZUeDH`

### Request Format
```php
POST https://api.sms.net.bd/sendsms
Content-Type: application/x-www-form-urlencoded

Parameters:
- api_key: R754CfVe5x8oIbVBiErJaOEx1x7u5AjVXxeZUeDH
- msg: Your SMS message text
- to: Recipient phone number (880 format, e.g., 8801712345678)
```

### Phone Number Formats (Auto-converted)
All formats are normalized to 880 format:
- ✅ `01712345678` → `8801712345678`
- ✅ `8801712345678` → `8801712345678`
- ✅ `1712345678` → `8801712345678`
- ❌ Invalid: `123456789` (too short)

### Message Template
Default template with replaceable variables:
```
SonyaBus ticket confirmed. PNR {PNR}, Seats {SEATS}, Fare BDT {FARE}. Status: {STATUS}
```

**Available placeholders:**
- `{PNR}`: Booking reference (SE00001)
- `{SEATS}`: Comma-separated seat numbers (A1,A2)
- `{FARE}`: Total fare amount (500.00)
- `{STATUS}`: Booking status (PAID, CANCELLED, etc.)

## Setup Instructions

### 1. Add API Key to .env
```bash
# In backend/.env
SMS_NETBD_API_KEY=R754CfVe5x8oIbVBiErJaOEx1x7u5AjVXxeZUeDH
```

### 2. Run Database Seeder
```bash
cd backend
php artisan db:seed --class=SmsConfigSeeder
# OR seed everything
php artisan db:seed
```

This creates the SMS.NET.BD configuration in the database:
```sql
INSERT INTO sms_configs (
    gateway_name, 
    gateway_driver, 
    api_url, 
    api_key, 
    sender_id, 
    is_active, 
    message_template
) VALUES (
    'SMS.NET.BD',
    'smsnetbd',
    'https://api.sms.net.bd/sendsms',
    'R754CfVe5x8oIbVBiErJaOEx1x7u5AjVXxeZUeDH',
    NULL,
    true,
    'SonyaBus ticket confirmed. PNR {PNR}, Seats {SEATS}, Fare BDT {FARE}. Status: {STATUS}'
);
```

### 3. Verify Configuration
- Admin Panel → SMS Gateway Configuration
- Check: Provider = "SMS.NET.BD"
- Check: Service Status = "Active"
- Click "Send Test SMS" to verify connectivity

## How SMS is Triggered

### Booking Flow
```
1. User creates booking via API
   ↓
2. Booking created with status = PAID
   ↓
3. SmsGatewayService.sendBookingVerification() called
   ↓
4. Phone number validated to Bangladesh format
   ↓
5. SMS message built from template
   ↓
6. HTTP POST to SMS.NET.BD API
   ↓
7. Response logged (success/failure)
```

### Code Integration
In `BookingController::store()`:
```php
$booking = Booking::create([...]);

// Send SMS immediately after booking
$smsResult = $this->smsGatewayService->sendBookingVerification($booking);

return response()->json([
    'message' => 'Booking successfully created!',
    'booking' => $booking,
    'sms' => $smsResult,  // { success: true/false, message: string }
], 201);
```

## Admin Dashboard

### SMS Gateway Tab
Located in Admin Panel → "SMS Gateway" sidebar item

**Features:**
- View/Edit gateway configuration
- Change SMS provider driver
- Update API credentials and sender ID
- Customize message template
- Enable/disable SMS service
- Test SMS connectivity with "Send Test SMS"

### Configuration Options
1. **Gateway Name**: Label for identification
2. **Provider / API Format**: Choose driver (smsnetbd, bulksmsbd, custom, get_query)
3. **Gateway API URL**: The endpoint to send requests to
4. **API Key**: Authentication credentials
5. **Sender ID**: Optional identifier (required for some providers, not for SMS.NET.BD)
6. **Message Template**: Customize booking SMS message
7. **Service Status**: Active/Inactive toggle

## Logging

All SMS events are logged to `storage/logs/laravel.log`:

### Success Log
```
[INFO] Booking SMS sent successfully.
- phone: 8801712345678
- driver: smsnetbd
- status: 200
- booking_id: 42
```

### Failure Log
```
[WARNING] SMS gateway rejected the request.
- phone: 8801712345678
- driver: smsnetbd
- http_status: 401
- response: Invalid API key
```

### Error Log
```
[ERROR] SMS gateway request failed.
- phone: 8801712345678
- driver: smsnetbd
- error: cURL error: Connection timeout
```

## Testing

### Manual Test via Admin Panel
1. Go to Admin Panel → SMS Gateway
2. Configure SMS.NET.BD credentials
3. Click "Send Test SMS"
4. Enter test phone number (e.g., 01712345678)
5. Verify SMS delivery

### Programmatic Test
```php
// From tinker or test
php artisan tinker
$smsService = app(\App\Services\SmsGatewayService::class);
$result = $smsService->sendTestMessage('01712345678', 'Test message from SonyaBus');
dd($result);
```

### Verify in Database
```sql
-- Check SMS config
SELECT * FROM sms_configs WHERE is_active = true;

-- Check booking with SMS details
SELECT id, passenger_phone, status, created_at FROM bookings 
WHERE status = 'PAID' ORDER BY created_at DESC LIMIT 5;
```

## Troubleshooting

### SMS Not Sending

1. **Check SMS service is active**
   - Admin Panel → SMS Gateway → Service Status should be "Active"

2. **Verify API key is correct**
   ```php
   SELECT api_key FROM sms_configs WHERE is_active = true;
   ```

3. **Check phone number format**
   - Must be valid Bangladesh mobile number
   - Example valid: 01712345678, 8801712345678

4. **Review logs**
   ```bash
   tail -f storage/logs/laravel.log | grep SMS
   ```

5. **Test gateway connectivity**
   - Admin Panel → Send Test SMS
   - Or run: `php artisan tinker`
   ```php
   $sms = app(\App\Services\SmsGatewayService::class);
   $result = $sms->sendTestMessage('01712345678');
   dd($result);
   ```

### Common Errors

| Error | Cause | Fix |
|-------|-------|-----|
| `Gateway is not configured or inactive` | SMS config not found or disabled | Run seeder: `php artisan db:seed --class=SmsConfigSeeder` |
| `API key is required` | api_key field is empty | Update SMS config with API key |
| `Sender ID is required` | For drivers that need it | Update sender_id (not needed for SMS.NET.BD) |
| `Invalid phone number` | Bad Bangladesh format | Check phone number validation |
| `HTTP 401 Unauthorized` | Wrong API key | Verify API key with SMS.NET.BD provider |
| `HTTP 429 Too Many Requests` | Rate limit exceeded | Reduce SMS sending frequency |
| `Connection timeout` | Network issue | Check firewall/proxy, verify API URL |

## Driver Comparison

| Feature | SMS.NET.BD | BulkSMSBD | Custom |
|---------|-----------|-----------|--------|
| **Driver Name** | smsnetbd | bulksmsbd | custom |
| **API Method** | POST form | POST form | POST form |
| **api_key** | ✅ Required | ✅ Required | ✅ Required |
| **sender_id** | ❌ Not used | ✅ As senderid | ✅ Required |
| **Phone param** | `to` | `number` | `mobile` |
| **Message param** | `msg` | `message` | `message` |
| **Query String** | No | No | No |

## Environment Variables

Add to `.env`:
```bash
# SMS Gateway API Key (optional - defaults to seeded value)
SMS_NETBD_API_KEY=R754CfVe5x8oIbVBiErJaOEx1x7u5AjVXxeZUeDH
```

If not set in `.env`, the seeder uses the hardcoded API key.

## Related Files
- Service: `backend/app/Services/SmsGatewayService.php`
- Job: `backend/app/Jobs/SendBookingSmsNotification.php`
- Model: `backend/app/Models/SmsConfig.php`
- Migration: `backend/database/migrations/2026_06_02_090000_create_sms_configs_table.php`
- Seeder: `backend/database/seeders/SmsConfigSeeder.php`
- Views: `backend/resources/views/admin/partials/sms-config.blade.php`
- Controller: `backend/app/Http/Controllers/API/BookingController.php`

## Support
For issues with SMS delivery:
1. Check logs: `storage/logs/laravel.log`
2. Verify API credentials with SMS.NET.BD
3. Test gateway connectivity via admin panel
4. Ensure queue worker is running (if using async jobs)
