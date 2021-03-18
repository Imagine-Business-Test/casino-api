<?php

namespace App\Contracts;

use App\Exceptions\RepositoryException;
use Illuminate\Database\Eloquent\Collection;

interface IRepository
{
    /**
     * Retrieves a single entity.
     * @param string $id The unique ID of the entity to be retrieved
     * @return mixed
     * @throws RepositoryException
     */
    public function find(string $id);

    /**
     * Retrieves all entities.
     * @return mixed
     * @throws RepositoryException
     */
    public function findAll(): Collection;

    /**
     * Creates a entity.
     * @param array $attributes Array of the details to be persisted.
     * @return mixed Last record inserted.
     * @throws RepositoryException
     */
    public function create(array $attributes);

    /**
     * Update an entity details.
     * @param string $id ID of the entity to update.
     * @param array $attributes Array of the details to be updated.
     * @return mixed Last record inserted.
     * @throws RepositoryException
     */
    public function Update(string $id, array $attributes);

    /**
     * Delete an entity.
     * @param string $id ID of the entity to be deleted.
     * @return bool
     * @throws RepositoryException
     */
    public function delete(string $id): bool;
}
