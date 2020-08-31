<?php

use Illuminate\Database\Seeder;
use App\Api\V1\Models\PlayerProfile;

class PlayerProfileTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        factory(PlayerProfile::class,50)->create();
    }
}
