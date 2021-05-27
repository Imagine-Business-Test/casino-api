<?php

namespace App\Providers;

use App\Api\V1\Repositories\Eloquent\BusinessEloquentRepository;
use App\Api\V1\Repositories\Eloquent\ChipHolderEloquentRepository;
use App\Api\V1\Repositories\Eloquent\ChipsEloquentRepository;
use App\Api\V1\Repositories\Eloquent\ChipVaultEloquentRepository;
use App\Api\V1\Repositories\Eloquent\ExchangeVaultEloquentRepository;
use App\Api\V1\Repositories\Eloquent\ExpensesEloquentRepository;
use App\Contracts\Repository\IPitRepository;
use App\Contracts\Repository\IUserRepository;
use App\Api\V1\Repositories\Eloquent\PitEloquentRepository;
use App\Api\V1\Repositories\Eloquent\PitEventLogRepository;
use App\Api\V1\Repositories\Eloquent\PitTypesEloquentRepository;
use App\Api\V1\Repositories\Eloquent\UserEloquentRepository;
use App\Contracts\Repository\IBusinessRepository;
use App\Contracts\Repository\IChipHolderRepository;
use App\Contracts\Repository\IChipRepository;
use App\Contracts\Repository\IChipVaultRepository;
use App\Contracts\Repository\IExchangeVaultRepository;
use App\Contracts\Repository\IExpenses;
use App\Contracts\Repository\IPitEventLog;
use App\Contracts\Repository\IPitTypesRepository;
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
        $this->app->bind(IExpenses::class, ExpensesEloquentRepository::class);
        $this->app->bind(IPitEventLog::class, PitEventLogRepository::class);
        $this->app->bind(IPitTypesRepository::class, PitTypesEloquentRepository::class);
        $this->app->bind(IPitRepository::class, PitEloquentRepository::class);
        $this->app->bind(IChipRepository::class, ChipsEloquentRepository::class);
        $this->app->bind(IUserRepository::class, UserEloquentRepository::class);
        $this->app->bind(IBusinessRepository::class, BusinessEloquentRepository::class);
        $this->app->bind(IChipVaultRepository::class, ChipVaultEloquentRepository::class);
        $this->app->bind(IChipHolderRepository::class, ChipHolderEloquentRepository::class);
        $this->app->bind(IExchangeVaultRepository::class, ExchangeVaultEloquentRepository::class);
    }
}
