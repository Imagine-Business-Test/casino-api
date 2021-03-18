<?php

namespace App\Contracts\Repository;

use App\Exceptions\RepositoryException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use phpDocumentor\Reflection\Types\Boolean;

interface IUserRepository
{
    /**
     * Retrieves all admin users.
     * @param string $username the username of the user
     * @return mixed
     * @throws RepositoryException
     */
    public function showByUsername(string $username);

    
}
