<?php

use Illuminate\Database\Seeder;
use App\Api\V1\Models\Pits;

class PitsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        factory(Pits::class,50)->create();
    }
}
