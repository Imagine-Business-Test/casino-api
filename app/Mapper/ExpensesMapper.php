<?php

namespace  App\Utils;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExpensesMapper
{

    public static function propExist($object, $prop)
    {
        return property_exists($object, $prop) ? $object->{$prop} : null;
    }



    public static function toExpensesDB($data)
    {
        $data = (object) $data;
        $res = [];
        $businessID = SELF::propExist($data, 'business_id');
        $userID = SELF::propExist($data, 'user_id');

        foreach ($data->expenses as $expense) {
            $expense = (object) $expense;

            $res[] = [
                'business_id' => $businessID,
                'name' => SELF::propExist($expense, 'name'),
                'amount' => SELF::propExist($expense, 'amount'),
                'group_id' => $data->group_id,
                'descr' => SELF::propExist($expense, 'comment'),
                'created_by' => $userID,
            ];
        }

        return $res;
    }
}
