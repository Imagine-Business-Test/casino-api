<?php

use Illuminate\Database\Seeder;
use App\Api\V1\Models\Exchange;

class ExchangeTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        factory(Exchange::class,50)->create();
    }
}
