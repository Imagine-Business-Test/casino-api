<?php

namespace  App\Utils;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ChipHolderMapper
{

    public static function propExist($object, $prop)
    {
        return property_exists($object, $prop) ? $object->{$prop} : null;
    }


    public static function pruneChipHolder($data)
    {
        $data = (object) $data;

        $holders = [];
        foreach ($data as $holder) {
            $holders[]  = [
                'id' => $holder->id,
                'name' => $holder->name,
                'slug' => $holder->slug,
                'desc' => $holder->descr,
            ];
        }
        return $holders;
    }
}
