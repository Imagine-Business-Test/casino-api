<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/



$router->get('/', function () use ($router) {
    return $router->app->version();
});

$api = app('Dingo\Api\Routing\Router');
$api->version(
    'v1',
    [
        'namespace' => 'App\Api\V1\Controllers'
    ],
    function ($api) {

        /**
         * Users Route
         */

        $api->group(['middleware' => ['auth:api', 'scopes:cashier']], function ($api) {
            $api->post('user', [
                'as' => 'authorizations.register',
                'uses' => 'UserController@register',
            ]);
            $api->get('user', [
                'as' => 'authorization.user',
                'uses' => 'UserController@findAll',
            ]);

            $api->get('user/{id}', [
                'as' => 'authorization.show',
                'uses' => 'UserController@find',
            ]);
        });


        /**
         * Pits Routes
         */

        $api->group(['middleware' => ['auth:api', 'scopes:cashier']], function ($api) {
            $api->get('pits', [
                'as' => 'authorization.login',
                'uses' => 'PitsController@getAll',
            ]);
        });



        /**
         * Business Routes
         */

        $api->post('business', [
            'as' => 'business.register',
            'uses' => 'BusinessController@register',
        ]);

        $api->get('business', [
            'as' => 'business.register',
            'uses' => 'BusinessController@findAll',
        ]);

        $api->get('business/{id}', [
            'as' => 'business.register',
            'uses' => 'BusinessController@find',
        ]);


        /**
         * Auth route
         */
        $api->post('login', [
            'as' => 'authorization.login',
            'uses' => 'UserController@login',
        ]);


         /**
         * Chip Holder Routes
         */

        $api->get('chip_holder', [
            'as' => 'vault.chip_holder',
            'uses' => 'ChipHolderController@findAll',
        ]);


         /**
         * Vault Routes
         */
        $api->get('vault/incoming', [
            'as' => 'vault.find_all_incoming',
            'uses' => 'VaultController@findAllIncoming',
        ]);

        $api->get('vault/outgoing', [
            'as' => 'vault.find_all_outgoing',
            'uses' => 'VaultController@findAllOutgoing',
        ]);

        $api->get('vault/all_by_business', [
            'as' => 'vault.all_by_business',
            'uses' => 'VaultController@findAllByBusiness',
        ]);

        $api->post('vault/receive', [
            'as' => 'vault.receive',
            'uses' => 'VaultController@receive',
        ]);

        $api->post('vault/dispatch', [
            'as' => 'vault.dispatch',
            'uses' => 'VaultController@disburse',
        ]);

         /**
         * Exchange Routes
         */

        $api->post('exchange/receive', [
            'as' => 'exchange.receive',
            'uses' => 'ExchangeController@receive',
        ]);

        $api->post('exchange/dispatch', [
            'as' => 'exchange.dispatch',
            'uses' => 'ExchangeController@disburse',
        ]);

    
    }
);
