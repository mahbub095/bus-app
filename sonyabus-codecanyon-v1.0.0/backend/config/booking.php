<?php

return [
    'service_charge' => (float) env('BOOKING_SERVICE_CHARGE', 20),
    'gateway_charge' => (float) env('BOOKING_GATEWAY_CHARGE', 16),
    'service_charge_discount' => (float) env('BOOKING_SC_DISCOUNT', 20),
    'gateway_charge_discount' => (float) env('BOOKING_GC_DISCOUNT', 16),
    'default_seat_class' => env('BOOKING_DEFAULT_SEAT_CLASS', 'E-Class'),
    'seat_rows' => ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I'],
];
