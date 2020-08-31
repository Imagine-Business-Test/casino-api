<?php

use Illuminate\Database\Seeder;
use App\Api\V1\Models\BonusWallet;
class BonusWalletTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        factory(BonusWallet::class,50)->create();
    }
}
