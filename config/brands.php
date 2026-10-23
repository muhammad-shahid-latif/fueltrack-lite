<?php

/*
| Fuel networks the app can be themed for. Each dealer picks their network
| once; the whole app then uses that network's logo and colours.
|
| Logos are not bundled. Put each one in public/images/brands/ named after
| its key (pso.png, parco-gunvor.svg, ...). Until then a lettered badge in
| the network's colours is shown.
|
| Colours:
|   surface-*  sidebar, headers and dark panels, darkest to lightest
|   accent     main buttons and the active menu item; on-accent is its text
|   led        glowing figures on dark panels (prices, totals)
|   ground     page background
|   stripe     the signature colour band along the top of every page
*/

return [

    'pso' => [
        'name' => 'PSO',
        'full_name' => 'Pakistan State Oil',
        'monogram' => 'PSO',
        'colors' => [
            'surface-900' => '#01301a',
            'surface-800' => '#014a26',
            'surface-700' => '#00753a',
            'surface-500' => '#0b9a4c',
            'accent' => '#ffd100',
            'on-accent' => '#0b2614',
            'led' => '#ffd100',
            'ground' => '#eef5f1',
        ],
        'stripe' => ['#00843d', '#0055a4', '#ffd100'],
    ],

    'parco-gunvor' => [
        'name' => 'PARCO Gunvor',
        'full_name' => 'PARCO Gunvor Limited',
        'monogram' => 'PGL',
        'colors' => [
            'surface-900' => '#0d2a30',
            'surface-800' => '#153f47',
            'surface-700' => '#1e5a63',
            'surface-500' => '#2c7c87',
            'accent' => '#d7261e',
            'on-accent' => '#ffffff',
            'led' => '#f7931e',
            'ground' => '#f6f2ef',
        ],
        'stripe' => ['#e8702a', '#d7261e', '#1e5a63'],
    ],

    'shell' => [
        'name' => 'Shell',
        'full_name' => 'Shell Pakistan',
        'monogram' => 'Shell',
        'colors' => [
            'surface-900' => '#5c080b',
            'surface-800' => '#8f0f13',
            'surface-700' => '#bd151a',
            'surface-500' => '#dd1d21',
            'accent' => '#fbce07',
            'on-accent' => '#3a1d00',
            'led' => '#fbce07',
            'ground' => '#f7f6f1',
        ],
        'stripe' => ['#dd1d21', '#fbce07'],
    ],

    'go' => [
        'name' => 'GO',
        'full_name' => 'Gas & Oil Pakistan',
        'monogram' => 'GO',
        'colors' => [
            'surface-900' => '#0a3a1b',
            'surface-800' => '#0f5227',
            'surface-700' => '#177836',
            'surface-500' => '#239d47',
            'accent' => '#e2231a',
            'on-accent' => '#ffffff',
            'led' => '#f7c600',
            'ground' => '#f1f6f2',
        ],
        'stripe' => ['#239d47', '#f7c600', '#e2231a'],
    ],

    'attock' => [
        'name' => 'Attock',
        'full_name' => 'Attock Petroleum Limited',
        'monogram' => 'APL',
        'colors' => [
            'surface-900' => '#111310',
            'surface-800' => '#1b1f18',
            'surface-700' => '#2b3224',
            'surface-500' => '#56682f',
            'accent' => '#a6ce39',
            'on-accent' => '#141a06',
            'led' => '#a6ce39',
            'ground' => '#f2f4ee',
        ],
        'stripe' => ['#a6ce39', '#e31e24'],
    ],

    'askar' => [
        'name' => 'Askar',
        'full_name' => 'Askar Oil Services',
        'monogram' => 'ASK',
        // From askaroil.com.pk: deep navy header, logo blue and orange.
        'colors' => [
            'surface-900' => '#10132b',
            'surface-800' => '#181b39',
            'surface-700' => '#232a5c',
            'surface-500' => '#0060b4',
            'accent' => '#eb6e12',
            'on-accent' => '#ffffff',
            'led' => '#ff9a3c',
            'ground' => '#f1f4f9',
        ],
        'stripe' => ['#0060b4', '#eb6e12', '#181b39'],
    ],

];
