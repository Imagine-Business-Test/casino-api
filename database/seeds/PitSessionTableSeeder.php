<?php

use Illuminate\Database\Seeder;
use App\Api\V1\Models\PitSession;

class PitSessionTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        factory(PitSession::class,50)->create();
    }
}
