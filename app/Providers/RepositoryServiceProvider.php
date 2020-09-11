<?php

namespace App\Providers;

use App\Contracts\Repository\IPitRepository;
use App\Contracts\Repository\IUserRepository;
use App\Api\V1\Repositories\Eloquent\PitEloquentRepository;
use App\Api\V1\Repositories\Eloquent\UserEloquentRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //Repositories
        // $this->app->bind(IUserRepository::class, UserEloquentRepository::class);
        $this->app->bind(IPitRepository::class, PitEloquentRepository::class);
        $this->app->bind(IUserRepository::class, UserEloquentRepository::class);
        // $this->app->bind('App\Api\V1\Repositories\Contract\IPitRepository', 'App\Api\V1\Repositories\PitEloquentRepository');
       
    }
}
