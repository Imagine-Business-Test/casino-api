<?php

use Illuminate\Database\Seeder;
use App\Api\V1\Models\PitRules;

class PitRulesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        factory(PitRules::class,50)->create();
    }
}
