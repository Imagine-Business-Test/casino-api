<?php

use Illuminate\Database\Seeder;
use App\Api\V1\Models\Message;

class MessageTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        factory(Message::class,50)->create();
    }
}
