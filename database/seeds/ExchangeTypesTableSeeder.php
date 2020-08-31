<?php

use Illuminate\Database\Seeder;
use App\Api\V1\Models\ExchangeType;

class ExchangeTypesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        /**
         * index 0 : the code_name
         * index 1 : the xchange_desc(descr)
         */
        $data = array(
            ['CHIP2CASH', 'Chip to Cash'],
            ['CASH2CHIP', 'Cash to Chip']
        );

        for ($i = 0; $i < count($data); $i++) {
            ExchangeType::create([
                'code_name' => $data[$i][0],
                'descr' => $data[$i][1],
            ]);
        }
    }
}
