# SMS Gateway Setup - Quick Start

## ✅ What's Configured

SMS notifications are now **fully integrated** with your SonyaBus booking system using **SMS.NET.BD** gateway.

### Gateway Credentials
- **API URL**: `https://api.sms.net.bd/sendsms`
- **API Key**: `R754CfVe5x8oIbVBiErJaOEx1x7u5AjVXxeZUeDH`
- **Driver**: `smsnetbd` (optimized for SMS.NET.BD API)

## 🚀 Setup Steps

### 1. Update .env File
```bash
# In backend/.env (add this line)
SMS_NETBD_API_KEY=R754CfVe5x8oIbVBiErJaOEx1x7u5AjVXxeZUeDH
```

### 2. Run Database Seeder
```bash
cd backend

# Option A: Seed only SMS config
php artisan db:seed --class=SmsConfigSeeder

# Option B: Seed entire database (includes SMS config)
php artisan db:seed
```

This creates the SMS gateway configuration in the `sms_configs` table with all required settings.

## 📱 How It Works

When a user books a seat and payment is successful:

1. **Booking Created** → Status set to `PAID`
2. **SMS Triggered** → `SmsGatewayService::sendBookingVerification()` called
3. **Phone Normalized** → Converts local format to international (880...)
4. **Message Built** → Uses template with booking details
5. **SMS Sent** → POST request to SMS.NET.BD API
6. **Logged** → Success/failure recorded in `storage/logs/laravel.log`

### Example SMS Message
```
SonyaBus ticket confirmed. PNR SE00042, Seats A1,A2, Fare BDT 1500.00. Status: PAID
```

## ✏️ Customizing SMS Message

Edit SMS template in Admin Panel:
- Go to **Admin Dashboard → SMS Gateway**
- Update **Message Template** field
- Use placeholders: `{PNR}`, `{SEATS}`, `{FARE}`, `{STATUS}`

## 🧪 Test SMS Delivery

### Via Admin Panel
1. Admin Dashboard → SMS Gateway
2. Scroll to "Send Test SMS"
3. Enter phone number: `01712345678`
4. Click "Send Test SMS"
5. Check if SMS arrives

### Via Terminal (Tinker)
```bash
php artisan tinker
$sms = app(\App\Services\SmsGatewayService::class);
$result = $sms->sendTestMessage('01712345678', 'Test message');
dd($result);
```

## 📋 Phone Number Support

Automatically converts all formats to international:
- ✅ `01712345678` 
- ✅ `8801712345678`
- ✅ `1712345678`

## 🔍 Monitor SMS Status

### Check Logs
```bash
tail -f storage/logs/laravel.log | grep SMS
```

### Database Query
```sql
-- See last 5 SMS configs
SELECT * FROM sms_configs ORDER BY updated_at DESC LIMIT 5;

-- See recent bookings
SELECT id, passenger_phone, status, created_at FROM bookings 
WHERE status = 'PAID' ORDER BY created_at DESC LIMIT 10;
```

## 🐛 Troubleshooting

| Issue | Solution |
|-------|----------|
| SMS not sending | Check Admin Panel → SMS Gateway → Service Status = "Active" |
| Invalid API key | Run seeder again: `php artisan db:seed --class=SmsConfigSeeder` |
| Invalid phone number | Ensure Bangladesh mobile number format (01X or 880...) |
| Check logs | `tail -f storage/logs/laravel.log` |
| Test API | Admin Panel → "Send Test SMS" with valid number |

## 📚 Files Modified

- ✅ `backend/app/Services/SmsGatewayService.php` - Added SMS.NET.BD driver
- ✅ `backend/database/seeders/SmsConfigSeeder.php` - Created SMS config seeder
- ✅ `backend/database/seeders/DatabaseSeeder.php` - Added SMS config to seeding chain
- ✅ `backend/.env.example` - Added SMS API key config
- ✅ `backend/resources/views/admin/partials/sms-config.blade.php` - Added SMS.NET.BD option to admin panel
- ✅ `SMS_GATEWAY_SETUP.md` - Complete technical documentation

## ✨ Features

✅ Automatic SMS on PAID bookings  
✅ Phone number validation & normalization  
✅ Customizable message template  
✅ Multiple SMS driver support  
✅ Admin panel configuration  
✅ Test SMS capability  
✅ Comprehensive logging  
✅ Error handling & retry logic  

## Next Steps

1. **Run seeder** to initialize SMS config
2. **Test SMS** from admin panel
3. **Monitor logs** for any issues
4. **Customize template** if needed (optional)

For full technical details, see [SMS_GATEWAY_SETUP.md](SMS_GATEWAY_SETUP.md)
