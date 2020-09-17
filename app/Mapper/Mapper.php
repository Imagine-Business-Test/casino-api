<?php

namespace  App\Utils;

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
            'slug' => SELF::propExist($data, 'business_slug')
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
        return [
            'business_id' => SELF::propExist($data, 'business_id')
        ];
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
        $transID = SELF::propExist($data, 'trans_id');

        foreach ($data->detail as $chip) {
            $chip = (object) $chip;
            $res[] = [
                'business_id' => $businessID,
                'trans_id' => $transID,
                'chip_id' => SELF::propExist($chip, 'id'),
                'qty' => SELF::propExist($chip, 'qty'),
                'chip_value' => SELF::propExist($chip, 'value'),
                'total_chip_value' => SELF::propExist($chip, 'total_value'),
                'created_by' => $userID,
            ];
        }


        return $res;
    }

    public static function updateChipVault($data)
    {
        $data = (object) $data;
        return [
            'total_amount' => DB::raw("total_amount + {$data->total_value}"),
        ];
    }

    public static function toExchangeStore($data)
    {
        $data = (object) $data;
        return [
            'business_id' => SELF::propExist($data, 'business_id')
        ];
    }
}
