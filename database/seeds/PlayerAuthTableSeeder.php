<?php

use Illuminate\Database\Seeder;
use App\Api\V1\Models\PlayerAuth;

class PlayerAuthTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        factory(PlayerAuth::class,50)->create();
    }
}
