<?php

use Illuminate\Database\Seeder;
use App\Api\V1\Models\MessageGroupSeen;

class MessageGroupSeenTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        factory(MessageGroupSeen::class,50)->create();
    }
}
