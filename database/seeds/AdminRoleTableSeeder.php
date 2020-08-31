<?php

use Illuminate\Database\Seeder;

class AdminRoleTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        /**
         * index 0 : the role
         * index 1 : the role_desc
         */
        $roles = array(
            ['cashier', 'the counter/ cashier'],
            ['operator', 'the table assistant that observes the game and operates the app according to events.'],
            ['pit boss', 'a tables pit boss'],
            ['dealer', 'a tables game dealer'],
            ['manager', ''],
            ['super admin', 'Has all privilages'],
            ['cashier supervisor', 'supervises the cashier and makes sure big payouts are properly checked'],
        );


        $faker = Faker\Factory::create();

        for ($i = 0; $i < count($roles); $i++) {
            App\Api\V1\Models\AdminRole::create([
                'role' => $roles[$i][0],
                'role_desc' => $roles[$i][1],
            ]);
        }
    }
}
