<?php

namespace App\Contracts\Repository;


interface IExchangeVaultRepository
{
    public function receive(array $details);

    public function dispatchControlled(array $details);

    public function dispatchAuto(array $details);
    
}
