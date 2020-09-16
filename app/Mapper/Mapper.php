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
}
