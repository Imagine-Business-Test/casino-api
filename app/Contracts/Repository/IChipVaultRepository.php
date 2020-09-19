<?php

namespace App\Contracts\Repository;


interface IChipVaultRepository
{
    public function receive(array $details);

    public function dispatchControlled(array $details);

    public function dispatchAuto(array $details);
    
}
