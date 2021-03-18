<?php
namespace App\Models;

//use DB;
use Illuminate\Support\Facades\DB;

class Wallet
{
    public $phone;

    //the money gotten by topping up via paystack
    public $topup = 0;

    //the money used up via purchase
    public $purchase = 0;

    //the money sent to users bank account
    public $withdrawal = 0;

    //get the wallet balance
    public $balance = 0;

    //get the money rewarded so far
    public $reward = 0;

    //total income
    public $income=0;

    //total expenditure
    public $expenditure=0;

    public function init($phone)
    {
        $this->phone = $phone;

        $this->calc();
    }

    //calculate the funds in the wallet
    public function calc()
    {
        //get topup as income
        $this->topup = DB::table("paystack")
            ->where(['phone' => $this->phone, 'status' => 1])
            ->sum('amount')
        ;

        //reward
        $this->reward = DB::table("reward")
            ->where(['phone' => $this->phone])
            ->sum('amount')
        ;

        //get purchase as expenditure
        $this->purchase = DB::table("purchase")
            ->where(['phone' => $this->phone])
            ->sum('amount')
        ;
        //get withdrawal as money is being sent away from the user wallet
        $this->withdrawal = DB::table("withdrawals")
            ->where(['phone' => $this->phone])
            ->sum('amount')
        ;

        //evaluate the total income
        $this->income = $this->topup + $this->reward;

        //evaluate the total expenditure
        $this->expenditure = $this->purchase + $this->withdrawal ;

        //get the balance
        $this->balance = $this->income - $this->expenditure;


//        return $this->balance;
    }

    /**
     * To buy stuffs via the wallet
     *
     * @data is an array of phone, amount, info
     *
     * return array
     */
    public function buy($data)
    {
        $this->init($data['phone']);

        if ($this->balance < $data['amount']) {
            //insufficient balance
            $data['status'] = 201;
            $data['balance'] = $this->balance;
            $data['message'] = "Insufficient funds";
            $data['response'] = false;
        } else {
            //do actual purchase
            $purchase_model = Purchase::create($data);
            $this->calc();
            $data['balance'] = $this->balance;
            $data['status'] = 200;
            $data['message'] = "Purchase was successful";
            $data['response'] = true;
        }

        return $data;
    }

    /**
     * To buy stuffs via the wallet
     *
     * @data is an array of phone, amount, info
     *
     * return array
     */
    public function transaction_history()
    {
        // Get all payment history for this user using this phone number

        $payment_history = DB::table("paystack")
            ->where(['phone' => $this->phone])
            ->get()->each(function ($item, $key)
            {
                $item->tag  = "deposit";

            })->toArray();

        $withdraw_history = DB::table("withdrawals")
            ->where(['phone' => $this->phone])
            ->get()->each(function ($item, $key)
            {
                $item->tag  = "withdraw";
                $item->status  = null;

            })->toArray();

        $financial_transaction =  array_merge($payment_history, $withdraw_history );

        return $financial_transaction;
    }





}
