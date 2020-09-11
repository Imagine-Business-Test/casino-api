<?php

namespace  App\Helper;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UserScope
{
    private $role;

    public static function get($role)
    {
        //$role comes from the user_role db. Refere for the values used here.
        switch ($role) {
            case 'cashier':
                $res = ['cashier'];
                break;
            case 'operator':
                $res = ['operator'];
                break;

            case 'pit_boss':
                $res = ['pit_boss'];
                break;

            case 'dealer':
                $res = ['dealer'];
                break;

            case 'manager':
                $res = ['manager'];
                break;

            case 'player':
                $res = ['player'];
                break;

            case 'super_admin':
                $res = ['*'];
                break;

            case 'admin':
                $res = ['*'];
                break;

            default:
                $res = ['player'];
                break;
        }

        return $res;
    }
}
