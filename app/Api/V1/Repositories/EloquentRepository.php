<?php

namespace App\Api\V1\Repositories;

use App\Api\V1\Traits\HttpStatusResponse;
use App\Contracts\IRepository;
use Dingo\Api\Routing\Helpers;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;


abstract class EloquentRepository implements IRepository
{
    use Helpers, HttpStatusResponse;
    protected $model;

    public function __construct()
    {
        $this->setModel();
    }

    public function getUser()
    {
        return $this->user();
    }

    /**
     * A method that any class(repository) that extends this class MUST have.
     * @return string The namespace of the Model which would be resolve later.
     */
    abstract public function model();


    private function setModel()
    {
        $this->model = app()->make($this->model()); //resolve to model.
    }

    /** {@inheritdoc}*/
    public function find(string $id): Collection
    {
        return $this->model->where('id', '=', $id)->get();
    }

    /** {@inheritdoc}*/
    public function findAll(): Collection
    {
        return $this->model->all();
    }

    /** {@inheritdoc}*/
    public function create(array $attributes)
    {
    }

    /** {@inheritdoc}*/
    public function Update(string $id, array $attributes)
    {
    }

    /** {@inheritdoc}*/
    public function delete(string $id): bool
    {
        return false;
    }
}
