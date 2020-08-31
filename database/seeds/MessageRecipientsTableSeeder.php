<?php

use Illuminate\Database\Seeder;
use App\Api\V1\Models\MessageRecipients;

class MessageRecipientsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        factory(MessageRecipients::class,50)->create();
    }
}
