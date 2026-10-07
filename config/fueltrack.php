<?php

return [

    /*
    | Fallback station details. The Settings module will override these
    | from the database once it is built.
    */
    'station' => [
        'name' => env('STATION_NAME', 'Al-Noor Filling Station'),
        'city' => env('STATION_CITY', 'Bahawalpur, Punjab'),
    ],

    'currency' => 'PKR',

    // Lock the app to one fuel network (a key from config/brands.php) for a
    // real installation. Empty keeps the network picker for demos.
    'brand' => env('FUELTRACK_BRAND'),

    // Shows the demo login hint on the login page. Keep false in production.
    'show_demo_credentials' => (bool) env('SHOW_DEMO_CREDENTIALS', false),

];
