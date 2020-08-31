<?php

use Illuminate\Database\Seeder;
use App\Api\V1\Models\PitTypes;

class PitTypesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        /**
         * index 0 : the name
         * index 1 : the type_desc
         * index 2 : the house_edge
         */
        $data = array(
            ['Blackjack', null, null],
            ['Roulette', null, null],
            ['Poker', null, null],
            ['Craps', null, null]
        );

        for ($i = 0; $i < count($data); $i++) {
            PitTypes::create([
                'name' => $data[$i][0],
                'type_desc' => $data[$i][1],
                'house_edge' => $data[$i][2],
            ]);
        }
    }
}
