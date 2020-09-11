<?php

namespace  App\Utils;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Mapper
{

    public static function propExist($object, $prop)
    {

        // Log::info("adaaa");
        // Log::info($prop);
        // Log::info(json_encode($object));
        // Log::info(json_encode(property_exists($object, $prop)));
        return property_exists($object, $prop) ? $object->{$prop} : null;
    }

    public static function toBankDetailDB($data)
    {
        $data = (object) $data;

        return [
            'user_id' => SELF::propExist($data, 'user'),
            'acc_name' => SELF::propExist($data, 'bank_account_name'),
            'acc_no' => SELF::propExist($data, 'bank_account_no'),
            'bank_id' => SELF::propExist($data, 'bank_id'),
        ];
    }

    public static function toUserDBAuth($data)
    {
        $data = (object) $data;

        return [
            'username' => $data->username,
            'password' => $data->password,
            'role' => (int) $data->role
        ];
    }

    public static function toUserDBProfile($data)
    {
        $data = (object) $data;

        return [
            'id' => SELF::propExist($data, 'id'),
            'firstname' => SELF::propExist($data, 'firstname'),
            'surname' => SELF::propExist($data, 'surname'),
            'email' => SELF::propExist($data, 'email'),
            'phone' => SELF::propExist($data, 'phone'),
            // 'business' => SELF::propExist($data, 'business'),
        ];
    }

    public static function toPromoUsageDB($data)
    {
        $data = (object) $data;

        return [
            'code_id' => SELF::propExist($data, 'code_id'),
            'user' => SELF::propExist($data, 'user'),
            'usage_type' => SELF::propExist($data, 'usage_type'),
            'business' => SELF::propExist($data, 'business')
        ];
    }

    public static function updatePromoWithdrawAll($data)
    {
        $data = (object) $data;

        return [
            'amount' => 0,
            'last_debit_amt' => SELF::propExist($data, 'amount'),
            'withdrawal_status' => 1 //1 = pending.
        ];
    }

    public static function updatePromoWinning($data)
    {
        $data = (object) $data;

        return [
            'amount' =>  DB::raw("amount + {$data->amount}"),
            'last_credit_amt' => SELF::propExist($data, 'amount'),
        ];
    }

    public static function updateUser($data)
    {
        $data = (object) $data;

        return [
            'surname' => SELF::propExist($data, 'surname'),
            'firstname' => SELF::propExist($data, 'firstname'),
            'phone' => SELF::propExist($data, 'phone'),
            'username' => SELF::propExist($data, 'username'),
            'avatar' => SELF::propExist($data, 'photo'),
        ];
    }

    public static function toPromoWinning($data)
    {
        $data = (object) $data;

        return [
            'wallet_id' => uniqid("WPW", true),
            'user' => SELF::propExist($data, 'user'),
            'code_id' => SELF::propExist($data, 'promo_code_id'),
            'questionnaire_id' => SELF::propExist($data, 'questionnaire_id'),
            'amount' => SELF::propExist($data, 'amount'),
            'last_credit_amt' => SELF::propExist($data, 'last_credit_amt'),
        ];
    }

    public static function toPromoWinningCreditLog($data)
    {
        $data = (object) $data;

        return [
            'uuid' => uniqid("WPWL", true),
            'user' => SELF::propExist($data, 'user'),
            'amount' => SELF::propExist($data, 'amount'),
            'wallet_id' => SELF::propExist($data, 'wallet_id'),
        ];
    }

    public static function toGameOutcomeRewardLog($data)
    {
        $data = (object) $data;

        return [
            'uuid' => uniqid("GORL", true),
            'user_id' => SELF::propExist($data, 'user'),
            'questionnaire_id' => SELF::propExist($data, 'questionnaire_id'),
            'amount' => SELF::propExist($data, 'amount'),
            'reward_type_id' => SELF::propExist($data, 'reward_type_id'),
            'game_outcome_id' => SELF::propExist($data, 'game_outcome_id'),
            'processed' => SELF::propExist($data, 'processed'),
        ];
    }
}
