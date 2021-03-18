<?php

namespace  App\Utils;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Mapper
{

    public static function propExist($object, $prop)
    {
        return property_exists($object, $prop) ? $object->{$prop} : null;
    }



    public static function toUserDBAuth($data)
    {
        $data = (object) $data;

        return [
            'username' => $data->username,
            'password' => $data->password,
            'role' => (int) $data->role,
            'business_id' => SELF::propExist($data, 'business_id'),
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
            'business_id' => SELF::propExist($data, 'business_id'),
        ];
    }

    public static function toBusinessDB($data)
    {
        $data = (object) $data;

        return [
            'name' => SELF::propExist($data, 'name'),
            'slug' => SELF::propExist($data, 'business_slug'),
            'licence_key' => SELF::propExist($data, 'licence_key')
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

    public static function toChipVault($data)
    {
        $data = (object) $data;
        $res = [];
        $businessID = SELF::propExist($data, 'business_id');

        foreach ($data->details as $chip) {

            $chip = (object) $chip;
            $res[] = [
                'business_id' => $businessID,
                'chip_id' => $chip->id,
                'chip_value' => $chip->value,
            ];
        }


        return $res;
    }

    public static function toChipVaultIncoming($data)
    {
        $data = (object) $data;
        return [
            'business_id' => SELF::propExist($data, 'business_id'),
            'chip_vault_id' => SELF::propExist($data, 'vault_id'),
            'qty' => SELF::propExist($data, 'total_qty'),
            'amount' => SELF::propExist($data, 'total_value'),
            'descr' => SELF::propExist($data, 'descr'),
            'created_by' => SELF::propExist($data, 'user_id'),
        ];
    }

    public static function toChipVaultIncomingLog($data)
    {
        $data = (object) $data;
        $res = [];
        $businessID = SELF::propExist($data, 'business_id');
        $userID = SELF::propExist($data, 'user_id');

        foreach ($data->detail as $chip) {
            $chip = (object) $chip;
            $res[] = [
                'business_id' => $businessID,
                'chip_vault_id' => SELF::propExist($chip, 'vault_id'),
                'qty' => SELF::propExist($chip, 'qty'),
                'chip_value' => SELF::propExist($chip, 'value'),
                'total_chip_value' => SELF::propExist($chip, 'total_value'),
                'created_by' => $userID,
            ];
        }


        return $res;
    }

    public static function toChipVaultOutgoingLog($data)
    {
        $data = (object) $data;
        $res = [];
        $businessID = SELF::propExist($data, 'business_id');
        $userID = SELF::propExist($data, 'user_id');

        foreach ($data->vaults as $vault) {
            $vault = (object) $vault;
            $res[] = [
                'business_id' => $businessID,
                'chip_vault_id' => SELF::propExist($vault, 'vault_id'),
                'qty' => SELF::propExist($vault, 'qty'),
                'amount' => SELF::propExist($vault, 'amount'),
                'holder_type' => $data->holder_type,
                'holder_id' => SELF::propExist($vault, 'vault_id'),
                'created_by' => $userID,
            ];
        }


        return $res;
    }

    // public static function updateChipVault($data)
    // {
    //     $data = (object) $data;
    //     return [
    //         'total_amount' => DB::raw("total_amount + {$data->total_value}"),
    //     ];
    // }

    public static function toExchangeStore($data)
    {
        $data = (object) $data;
        return [
            'business_id' => SELF::propExist($data, 'business_id')
        ];
    }

    public static function debitChipVault($details)
    {
        $details = (object) $details;

        $businessID = $details->business_id;

        $cases1 = ["qty=CASE"];
        $cases3 = ["total_amount=CASE"];
        $ids = [];

        foreach ($details->vaults as $vault) {
            $vault = (object) $vault;
            $vaultID = $vault->vault_id;
            $qty = $vault->qty;
            $amount = $vault->amount;


            $cases1[] = "WHEN id = {$vaultID} and business_id = {$businessID} THEN qty - {$qty}";
            $cases3[] = "WHEN id = {$vaultID} and business_id = {$businessID} THEN total_amount - {$amount}";

            $ids[] = $vaultID;
        }
        $cases1[] = "ELSE qty END";
        $cases3[] = "ELSE total_amount END";

        $ids = implode(',', $ids);

        $finalCases = [];

        $finalCases[] = implode(' ', $cases1);
        $finalCases[] = implode(' ', $cases3);

        $finalCases = implode(',', $finalCases);
        $currentTime = Carbon::now();

        return "UPDATE chip_vault SET {$finalCases}, `updated_at` = '{$currentTime}' WHERE `id` in ({$ids})";
    }

    public static function updateChipVault($details)
    {
        $details = (object) $details;

        $businessID = $details->business_id;

        $cases1 = ["qty=CASE"];
        $cases3 = ["total_amount=CASE"];
        $ids = [];

        foreach ($details->detail as $vault) {
            $vault = (object) $vault;
            $vaultID = $vault->vault_id;
            $qty = $vault->qty;
            $value = $vault->value;
            $totalAmount = $value * $qty;


            $cases1[] = "WHEN id = {$vaultID} and business_id = {$businessID} THEN qty + {$qty}";
            $cases3[] = "WHEN id = {$vaultID} and business_id = {$businessID} THEN total_amount + {$totalAmount}";

            $ids[] = $vaultID;
        }
        $cases1[] = "ELSE qty END";
        $cases3[] = "ELSE total_amount END";

        $ids = implode(',', $ids);

        $finalCases = [];

        $finalCases[] = implode(' ', $cases1);
        $finalCases[] = implode(' ', $cases3);

        $finalCases = implode(',', $finalCases);
        $currentTime = Carbon::now();

        return "UPDATE chip_vault SET {$finalCases}, `updated_at` = '{$currentTime}' WHERE `id` in ({$ids})";
    }

    public static function creditExchangeVault($details)
    {
        $details = (object) $details;

        $businessID = $details->business_id;

        $cases1 = ["qty=CASE"];
        $cases3 = ["total_amount=CASE"];
        $ids = [];

        foreach ($details->vaults as $vault) {
            $vault = (object) $vault;
            $vaultID = $vault->vault_id;
            $qty = $vault->qty;
            $value = $vault->vault_value;
            $totalAmount = $value * $qty;


            $cases1[] = "WHEN id = {$vaultID} and business_id = {$businessID} THEN qty + {$qty}";
            $cases3[] = "WHEN id = {$vaultID} and business_id = {$businessID} THEN total_amount + {$totalAmount}";

            $ids[] = $vaultID;
        }
        $cases1[] = "ELSE qty END";
        $cases3[] = "ELSE total_amount END";

        $ids = implode(',', $ids);

        $finalCases = [];

        $finalCases[] = implode(' ', $cases1);
        $finalCases[] = implode(' ', $cases3);

        $finalCases = implode(',', $finalCases);
        $currentTime = Carbon::now();

        return "UPDATE exchange_vault SET {$finalCases}, `updated_at` = '{$currentTime}' WHERE `id` in ({$ids})";
    }

    public static function toExchangeVaultIncomingLog($data)
    {
        $data = (object) $data;
        $res = [];
        $businessID = SELF::propExist($data, 'business_id');
        $userID = SELF::propExist($data, 'user_id');


        foreach ($data->vaults as $chip) {
            $chip = (object) $chip;
            $qty = SELF::propExist($chip, 'qty');
            $value = SELF::propExist($chip, 'vault_value');
            $totalValue = $qty * $value;

            $res[] = [
                'business_id' => $businessID,
                'chip_vault_id' => SELF::propExist($chip, 'vault_id'),
                'qty' => $qty,
                'chip_value' => $value,
                'holder_type' => $data->holder_type,
                'holder_id' => SELF::propExist($chip, 'vault_id'),
                'total_chip_value' => $totalValue,
                'created_by' => $userID,
            ];
        }

        return $res;
    }
}
