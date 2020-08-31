<?php

use Illuminate\Database\Seeder;
use App\Api\V1\Models\AdminProfile;

class AdminProfileTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        //
        $user = factory(AdminProfile::class,50)->create();
    }
}
