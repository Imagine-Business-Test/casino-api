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



        $api->group(['middleware' => 'auth:api'], function ($api) {
            $api->get('users', [
                'as' => 'authorization.user',
                'uses' => 'AdminAuthController@index',
            ]);

            $api->get('users/{id}', [
                'as' => 'authorization.show',
                'uses' => 'AdminAuthController@show',
            ]);
        });


        $api->post('login', [
            'as' => 'authorization.login',
            'uses' => 'UserController@login',
        ]);

        $api->group(['middleware' => ['auth:api', 'scopes:cashier']], function ($api) {
            $api->get('pits', [
                'as' => 'authorization.login',
                'uses' => 'PitsController@getAll',
            ]);
        });

        // register
        $api->post('register', [
            'as' => 'authorizations.register',
            'uses' => 'UserController@register',
        ]);


    }
);
