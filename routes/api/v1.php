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


//\Dusterio\LumenPassport\LumenPassport::routes($this->app);


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

        $api->post('users', [
            'as' => 'authorization.register',
            'uses' => 'AdminAuthController@store',
        ]);

        $api->post('login', [
            'as' => 'authorization.login',
            'uses' => 'AdminAuthController@login',
        ]);


        // // register
        // $api->post('register', [
        //     'as' => 'authorizations.register',
        //     'uses' => 'AuthController@register',
        // ]);


        // // login
        // $api->post('login', [
        //     'as' => 'authorizations.login',
        //     'uses' => 'AuthController@login',
        // ]);

        // // init_forgot_password
        // $api->post('init_forgot_password', [
        //     'as' => 'init_forgot_password',
        //     'uses' => 'AuthController@init_forgot_password',
        // ]);

        // // Activate Change password for user/phone number
        // $api->post('change_password', [
        //     'as' => 'change_password',
        //     'uses' => 'AuthController@change_password',
        // ]);


        // // need Authentication Routes
        // $api->group(['middleware' => 'auth:api'], function ($api) {

        //     // Show user dashboard - show user profile
        //     $api->get('user', [
        //         'as' => 'user.show',
        //         'uses' => 'UserController@userShow',
        //     ]);

        //     $api->post('user/update', [
        //         'as' => 'user.update',
        //         'uses' => 'UserController@updateUser',
        //     ]);
        // });
    }
);
