<?php

use Illuminate\Database\Seeder;
use App\Api\V1\Models\HouseSettings;

class HouseSettingsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        HouseSettings::create([
            'business_name' => 'Casino X',
            'business_title' => 'Home of Games',
            'business_desc' => null,
            'business_email' => null,
            'session_expires' => 3600,
            'bet_min' => 1000,
            'bet_max' => 1000000,
            'slogan' => 'Home of Games',
            'can_login' => 1,
        ]);
    }
}
