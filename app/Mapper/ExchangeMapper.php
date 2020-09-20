<?php

namespace  App\Utils;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExchangeMapper
{

    public static function propExist($object, $prop)
    {
        return property_exists($object, $prop) ? $object->{$prop} : null;
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
            $value = SELF::propExist($chip, 'value');
            $totalValue = $qty * $value;

            $res[] = [
                'business_id' => $businessID,
                'chip_vault_id' => SELF::propExist($chip, 'vault_id'),
                'qty' => $qty,
                'chip_value' => $value,
                'holder_type' => $data->holder_type,
                'holder_id' => $data->holder_id,
                'total_chip_value' => $totalValue,
                'created_by' => $userID,
            ];
        }

        return $res;
    }
}
