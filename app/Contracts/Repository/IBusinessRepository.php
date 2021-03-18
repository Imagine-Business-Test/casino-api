<?php

namespace App\Contracts\Repository;


interface IBusinessRepository
{
    public function slugExist(array $details);
}
