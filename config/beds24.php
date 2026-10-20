<?php

return [

    'base_url' => env('BEDS24_BASE_URL', 'https://beds24.com/api/v2'),

    // Long-life refresh token generated from an invite code (GET /authentication/setup).
    // Scopes required: bookings, bookings-personal
    'refresh_token' => env('BEDS24_REFRESH_TOKEN'),

    'http_timeout' => (int) env('BEDS24_HTTP_TIMEOUT', 15),

    // Beds24 -> Settings -> Properties -> Access -> Booking Webhook supports a custom header
    // ("header-name: header-value"). We use it as a shared secret.
    'webhook' => [
        'header' => env('BEDS24_WEBHOOK_HEADER', 'X-Katrium-Webhook-Secret'),
        'secret' => env('BEDS24_WEBHOOK_SECRET'),
    ],

    'queues' => [
        'webhooks' => env('BEDS24_QUEUE_WEBHOOKS', 'webhooks'),
        'outbound' => env('BEDS24_QUEUE_OUTBOUND', 'beds24-outbound'),
    ],

    // Written to Beds24 `apiReference` for bookings created in KatriuM (e.g. KM-1042).
    'api_reference_prefix' => 'KM-',

    // Used when a property has no check_in_time / check_out_time, or when a client sends date-only values.
    'default_check_in_time' => '14:00:00',
    'default_check_out_time' => '11:00:00',

    // Local numbers starting with 0 are normalised to +<code> for guest matching.
    'default_phone_country_code' => '63',

    // Statuses that no longer hold inventory. MUST match the WHERE clause of the GIST constraint.
    'non_blocking_statuses' => ['canceled', 'noshow'],

    // Values accepted by StoreBookingRequest. Inbound channels are stored as received.
    'manual_ota_sources' => [
        'direct', 'airbnb', 'booking', 'agoda', 'expedia', 'trip',
        'traveloka', 'vrbo', 'tiket', 'hometogo', 'ota',
    ],

    'channel_aliases' => [
        'bookingpage' => 'direct',
        'airbnbical' => 'airbnb',
        'vrboical' => 'vrbo',
    ],

    // Beds24 status -> bookings.status
    'inbound_status_map' => [
        'confirmed' => 'confirmed',
        'new' => 'confirmed',
        'request' => 'pending',
        'inquiry' => 'pending',
        'cancelled' => 'canceled',
        'black' => 'confirmed', // calendar block; stored with ota_source = 'block'
    ],

    // bookings.status -> Beds24 status
    'outbound_status_map' => [
        'confirmed' => 'confirmed',
        'pending' => 'request',
        'canceled' => 'cancelled',
        'noshow' => 'cancelled',
        'altered' => 'confirmed',
    ],
];