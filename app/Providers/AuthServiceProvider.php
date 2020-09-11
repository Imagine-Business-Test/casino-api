<?php

namespace App\Providers;

use App\User;
use Dusterio\LumenPassport\LumenPassport;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;
use Dingo\Api\Auth\Provider\Basic;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
        // app('Dingo\Api\Auth\Auth')->extend('basic', function ($app) {
        //     return new Basic($app['auth'], 'email');
        // });
    }

    /**
     * Boot the authentication services for the application.
     *
     * @return void
     */
    public function boot()
    {
        // Here you may define how you wish users to be authenticated for your Lumen
        // application. The callback which receives the incoming request instance
        // should return either a User instance or null. You're free to obtain
        // the User instance via an API token or any other method necessary.

        // $this->app['auth']->viaRequest('api', function ($request) {
        //     if ($request->input('api_token')) {
        //         return User::where('api_token', $request->input('api_token'))->first();
        //     }
        // });



        // Passport::personalAccessClientId(env("PASSPORT_PERSONAL_ACCESS_CLIENT_ID", "36"));

        // Passport::personalAccessClientSecret(env("PASSPORT_PERSONAL_ACCESS_CLIENT_SECRET", "uM0zTjimhfYt6fwCfxCGFwgFTTFURwhBNgalOQ5lu"));
        // // LumenPassport::routes($this->app,['prefix'=>'api']);
        Passport::tokensCan([
            'admin' => "The super admin",
            'cashier' => "Cashier's only  scope",
            'player' => "player's/Patrons only  scope",
            'pit_boss' => "",
            'super_admin' => "",
            'manager' => "",
            'operator' => "",
        ]);

        LumenPassport::routes($this->app);
    }
}
