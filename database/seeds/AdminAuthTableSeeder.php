<?php

use Illuminate\Database\Seeder;
use App\Api\V1\Models\AdminAuth;

class AdminAuthTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        //
        $user = factory(AdminAuth::class,50)->create();
    }
}
