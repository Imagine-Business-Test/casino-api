<?php

use Illuminate\Database\Seeder;
use App\Api\V1\Models\PitEventLog;

class PitEventLogTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        factory(PitEventLog::class,50)->create();
    }
}
