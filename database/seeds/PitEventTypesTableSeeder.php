<?php

use Illuminate\Database\Seeder;
use App\Api\V1\Models\PitEventTypes;

class PitEventTypesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        /**
         * index 0 : the event_name
         * index 1 : the event_desc
         */
        $data = array(
            ['busted', 'player lost and is out of chips/cash'],
            ['won', 'Player won the session'],
            ['lost', 'Player lost the session'],
            ['deal', 'Dealer has dealed card/game']
        );

        for ($i = 0; $i < count($data); $i++) {
            PitEventTypes::create([
                'event_name' => $data[$i][0],
                'event_desc' => $data[$i][1],
            ]);
        }
    }
}
