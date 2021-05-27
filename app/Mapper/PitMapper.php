<?php

namespace  App\Utils;

use Carbon\Carbon;
use Countable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PitMapper
{

    public static function propExist($object, $prop)
    {
        return property_exists($object, $prop) ? $object->{$prop} : null;
    }


    public static function prune($data)
    {
        if (!$data instanceof Countable) {
            unset(
                $data['password'],
                $data['role'],
            );
            return $data;
        }

        foreach ($data as $entity) {
            unset(
                $entity['password'],
                $entity['role'],
            );
        }
        return $data;
    }

    public static function toPit($data)
    {
        $data = (object) $data;
        return [
            'name' => SELF::propExist($data, 'name'),
            'pit_boss' => SELF::propExist($data, 'pit_boss_id'),
            'pit_float' => SELF::propExist($data, 'float'),
            'pit_game_type' => SELF::propExist($data, 'game_type'),
            'in_service' => SELF::propExist($data, 'in_service'),
            'opening_amount' => SELF::propExist($data, 'opening_amount'),
            'bet_max' => SELF::propExist($data, 'max_bet'),
            'bet_min' => SELF::propExist($data, 'min_bet'),
            'created_by' => SELF::propExist($data, 'user_id'),
            'business_id' => SELF::propExist($data, 'business_id'),
        ];
    }
}
