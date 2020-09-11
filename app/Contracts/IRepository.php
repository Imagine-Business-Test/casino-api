<?php

namespace App\Contracts;

use App\Exceptions\RepositoryException;
use Illuminate\Database\Eloquent\Collection;

interface IRepository
{
    /**
     * Retrieves a single user.
     * @param string $id The unique ID of the user to be retrieved
     * @return mixed
     * @throws RepositoryException
     */
    public function find(string $id);

    /**
     * Retrieves all users.
     * @return mixed
     * @throws RepositoryException
     */
    public function findAll(): Collection;

    /**
     * Creates a user.
     * @param array $attributes Array of the details to be persisted.
     * @return mixed Last record inserted.
     * @throws RepositoryException
     */
    public function create(array $attributes);

    /**
     * Update a user details.
     * @param string $id ID of the user to update.
     * @param array $attributes Array of the details to be updated.
     * @return mixed Last record inserted.
     * @throws RepositoryException
     */
    public function Update(string $id, array $attributes);

    /**
     * Delete a user.
     * @param string $id ID of the user to be deleted.
     * @return bool
     * @throws RepositoryException
     */
    public function delete(string $id): bool;
}
