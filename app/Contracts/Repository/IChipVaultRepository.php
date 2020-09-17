<?php

namespace App\Contracts\Repository;


interface IChipVaultRepository
{
    public function receive(array $details);

    public function dispatch(array $details);
    
    public function setActiveVault();
}
