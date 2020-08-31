<?php

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // $this->call('UsersTableSeeder');
        $this->call('AdminRoleTableSeeder');

        $this->call('AdminAuthTableSeeder');

        $this->call('AdminProfileTableSeeder');

        $this->call('PlayerAuthTableSeeder');

        $this->call('PlayerProfileTableSeeder');

        $this->call('BonusWalletTableSeeder');

        $this->call('ExchangeTypesTableSeeder');

        $this->call('ExchangeTableSeeder');

        $this->call('PitTypesTableSeeder');
        
        $this->call('PitEventTypesTableSeeder');
        
        $this->call('PitsTableSeeder');
        
        $this->call('PitRulesTableSeeder');
        
        $this->call('PitSessionTableSeeder');
        
        $this->call('PitEventLogTableSeeder');
        
        $this->call('MessageTableSeeder');
        
        $this->call('MessageGroupSeenTableSeeder');
        
        $this->call('MessageRecipientsTableSeeder');
        
        $this->call('HouseSettingsTableSeeder');
    }
}
