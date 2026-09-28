<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        Setting::putMany([
            'station_name' => 'Al-Noor Filling Station',
            'station_address' => 'Bahawalpur, Punjab, Pakistan',
            'station_phone' => '0300-0000000',
            'currency' => 'PKR',
            'default_opening_cash' => '100000',
        ]);
    }
}
