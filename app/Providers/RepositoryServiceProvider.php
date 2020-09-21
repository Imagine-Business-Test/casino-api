<?php

namespace App\Providers;

use App\Api\V1\Repositories\Eloquent\BusinessEloquentRepository;
use App\Api\V1\Repositories\Eloquent\ChipHolderEloquentRepository;
use App\Api\V1\Repositories\Eloquent\ChipVaultEloquentRepository;
use App\Api\V1\Repositories\Eloquent\ExchangeVaultEloquentRepository;
use App\Contracts\Repository\IPitRepository;
use App\Contracts\Repository\IUserRepository;
use App\Api\V1\Repositories\Eloquent\PitEloquentRepository;
use App\Api\V1\Repositories\Eloquent\UserEloquentRepository;
use App\Contracts\Repository\IBusinessRepository;
use App\Contracts\Repository\IChipHolderRepository;
use App\Contracts\Repository\IChipVaultRepository;
use App\Contracts\Repository\IExchangeVaultRepository;
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
        $this->app->bind(IBusinessRepository::class, BusinessEloquentRepository::class);
        $this->app->bind(IChipVaultRepository::class, ChipVaultEloquentRepository::class);
        $this->app->bind(IChipHolderRepository::class, ChipHolderEloquentRepository::class);
        $this->app->bind(IExchangeVaultRepository::class, ExchangeVaultEloquentRepository::class);
        // $this->app->bind('App\Api\V1\Repositories\Contract\IPitRepository', 'App\Api\V1\Repositories\PitEloquentRepository');

    }
}
