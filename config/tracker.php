<?php

return [
    // When the scheduled AI jobs run (server time). Run `php artisan schedule:work` or a cron entry.
    'scan_time' => env('TRACKER_SCAN_TIME', '06:00'),
    'venues_day' => (int) env('TRACKER_VENUES_DAY', 1),     // 0 = Sunday … 6 = Saturday
    'venues_time' => env('TRACKER_VENUES_TIME', '07:00'),
    'citations_day' => (int) env('TRACKER_CITATIONS_DAY', 3),
    'citations_time' => env('TRACKER_CITATIONS_TIME', '07:00'),

    // The first account created by `php artisan migrate --seed`.
    'admin' => [
        'name' => env('TRACKER_ADMIN_NAME', 'Admin'),
        'email' => env('TRACKER_ADMIN_EMAIL', 'admin@gmail.com'),
        'password' => env('TRACKER_ADMIN_PASSWORD', '123456'),
    ],

    // Account the local MCP server (php artisan mcp:start tracker) acts for. Defaults to the first account.
    'mcp_user' => env('TRACKER_MCP_USER'),

    // Site identity, editable in Settings → General.
    'tagline' => env('TRACKER_TAGLINE', 'from draft to decision'),
    'description' => env('TRACKER_DESCRIPTION', 'A self-hosted tracker for academic papers: submissions, review clocks, venues, calls for papers and citations.'),
    'contact_email' => env('TRACKER_CONTACT_EMAIL'),
    'registration' => (bool) env('TRACKER_REGISTRATION', true),

    // Footer credit.
    'credit' => [
        'name' => env('TRACKER_CREDIT_NAME', 'Naim Bin Hasan'),
        'url' => env('TRACKER_CREDIT_URL', 'https://naimbh.com/dev'),
    ],
];
