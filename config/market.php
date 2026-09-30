<?php

return [

    /*
    | OpenRouteService, used for drive times and for finding a postal code.
    | The key is free; without one the market list still works and only the
    | "Near" filter says drive times are unavailable. Set ORS_API_KEY in .env.
    */
    'openrouteservice' => [
        'key' => env('ORS_API_KEY'),
        'base_url' => env('ORS_BASE_URL', 'https://api.openrouteservice.org'),
    ],

    /*
    | Scales OpenRouteService's drive times, which come from speed limits and run
    | about a fifth longer than Google's for BC highway trips (48 vs 39 minutes,
    | Salmon Arm to Vernon). 1.0 shows its figures unchanged.
    */
    'drive_time_factor' => (float) env('MARKET_DRIVE_TIME_FACTOR', 0.8),

];
