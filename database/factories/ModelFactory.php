<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */
/**
 * Notice that some models below were declared but not used, especially the TYPES
 * because their data were explicitly defined in their individual seeding files.
 */
use App\User;
use App\Api\V1\Models\AdminRole;
use App\Api\V1\Models\AdminAuth;
use App\Api\V1\Models\AdminProfile;
use App\Api\V1\Models\BonusWallet;
use App\Api\V1\Models\Exchange;
use App\Api\V1\Models\ExchangeType;
use App\Api\V1\Models\HouseSettings;
use App\Api\V1\Models\Message;
use App\Api\V1\Models\MessageGroupSeen;
use App\Api\V1\Models\MessageRecipients;
use App\Api\V1\Models\Pits;
use App\Api\V1\Models\PitEventLog;
use App\Api\V1\Models\PitEventTypes;
use App\Api\V1\Models\PitRules;
use App\Api\V1\Models\PitSession;
use App\Api\V1\Models\PitTypes;
use App\Api\V1\Models\PlayerAuth;
use App\Api\V1\Models\PlayerProfile;
use Faker\Generator as Faker;

/*
|--------------------------------------------------------------------------
| Model Factories
|--------------------------------------------------------------------------
|
| This directory should contain each of the model factory definitions for
| your application. Factories provide a convenient way to generate new
| model instances for testing / seeding your application's database.
|
*/

$factory->define(User::class, function (Faker $faker) {
    return [
        'name' => $faker->name,
        'email' => $faker->email,
    ];
});

$factory->define(AdminAuth::class, function (Faker $faker) {
    return [
        'username' => $faker->userName,
        'role' => $faker->randomElement($array = array(1, 2, 3, 4, 5, 6, 7)),
        'user_code' => $faker->uuid,
        'password' => $faker->md5,
        'email_veri_code' => $faker->uuid,
        'email_verified' => $faker->randomElement($array = array(1, '0')),
        'phone_veri_code' => $faker->randomNumber(7, true),
        'phone_verified' => $faker->randomElement($array = array(1, '0')),
        'last_login' => $faker->dateTimeThisDecade($max = 'now')->format('Y-m-d H:i:s'),
        'last_action' => $faker->dateTimeThisYear($max = '+1 year')->format('Y-m-d H:i:s'),
        'user_disabled' => $faker->randomElement($array = array(1, '0')),
        'date_disabled' => $faker->dateTimeThisYear($max = '+1 year')->format('Y-m-d H:i:s'),
        'activation_code' => $faker->uuid,
        'activation_code_activated' => $faker->randomElement($array = array(1, '0')),
        'activation_code_expire' => $faker->dateTimeThisYear($max = '+1 year')->format('Y-m-d H:i:s'),
        'deleted_at' => $faker->dateTimeThisYear($max = '+1 year')->format('Y-m-d H:i:s'),
    ];
});

$factory->define(AdminProfile::class, function (Faker $faker) {
    return [
        'surname' => $faker->lastName,
        'firstname' => $faker->firstName($gender = null),
        'phone' => $faker->e164PhoneNumber,
        'email' => $faker->safeEmail,
        'avatar' => $faker->imageUrl($width = 640, $height = 480)
    ];
});


$factory->define(BonusWallet::class, function (Faker $faker) {
    return [
        'wallet_id' => $faker->uuid,
        'bonus_amount' => $faker->numberBetween($min = 1000, $max = 29000),
        'redeemed' => $faker->randomElement($array = array(1, '0')),
        'player_id' => $faker->numberBetween($min = 1, $max = 50),
        'granted_by' => $faker->numberBetween($min = 1, $max = 50),
        'disabled' => $faker->randomElement($array = array(1, '0')),
        'date_disabled' => $faker->dateTimeThisYear($max = '+1 year')->format('Y-m-d H:i:s'),
    ];
});


$factory->define(Exchange::class, function (Faker $faker) {
    return [
        'exchange_type' => $faker->randomElement($array = array(1, 2)),
        'amount' => $faker->numberBetween($min = 1000, $max = 29000),
        'player' => $faker->numberBetween($min = 1, $max = 50),
        'cashier' => $faker->numberBetween($min = 1, $max = 50),
        'supervisor' => $faker->numberBetween($min = 1, $max = 50)
    ];
});


$factory->define(Message::class, function (Faker $faker) {
    return [
        'title' => $faker->realText($maxNbChars = 100, $indexSize = 2),
        'body' => $faker->realText($maxNbChars = 500, $indexSize = 2),
        'meant_for' => $faker->randomElement($array = array(1, 2)),
        'display_type' => $faker->randomElement($array = array(1, '0')),
        'visibility' => $faker->randomElement($array = array(1, '0'))
    ];
});

$factory->define(MessageGroupSeen::class, function (Faker $faker) {
    return [
        'msg_id' => $faker->numberBetween($min = 1, $max = 50),
        'recipient' => $faker->numberBetween($min = 1, $max = 50)
    ];
});

$factory->define(MessageRecipients::class, function (Faker $faker) {
    return [
        'msg_id' => $faker->numberBetween($min = 1, $max = 50),
        'recipient' => $faker->numberBetween($min = 1, $max = 50),
        'seen' => $faker->randomElement($array = array(1, '0')),
        'date_seen' => $faker->dateTimeThisYear($max = '+1 year')->format('Y-m-d H:i:s'),
    ];
});

$factory->define(Pits::class, function (Faker $faker) {
    return [
        'name' => $faker->word,
        'label' => $faker->word,
        'dealer' => $faker->numberBetween($min = 1, $max = 50),
        'pit_boss' => $faker->numberBetween($min = 1, $max = 50),
        'operator' => $faker->numberBetween($min = 1, $max = 50),
        'in_service' => $faker->randomElement($array = array(1, '0')),
        'pit_game_type' => $faker->randomElement($array = array(1, 2, 3, 4))
    ];
});


$factory->define(PitEventLog::class, function (Faker $faker) {
    return [
        'event_type' => $faker->numberBetween($min = 1, $max = 4),
        'session_id' => $faker->numberBetween($min = 1, $max = 50),
        'player_id' => $faker->numberBetween($min = 1, $max = 50),
        'amount' => $faker->numberBetween($min = 1000, $max = 300000)
    ];
});

$factory->define(PitRules::class, function (Faker $faker) {
    return [
        'pit_id' => $faker->numberBetween($min = 1, $max = 50),
        'bet_min' => $faker->numberBetween($min = 1000, $max = 2000),
        'bet_max' => $faker->numberBetween($min = 200000, $max = 300000)
    ];
});

$factory->define(PitSession::class, function (Faker $faker) {
    return [
        'pit_id' => $faker->numberBetween($min = 1, $max = 50),
        'start_time' => $faker->dateTimeThisYear($max = '+1 year')->format('Y-m-d H:i:s'),
        'end_time' => $faker->dateTimeThisYear($max = '+1 year')->format('Y-m-d H:i:s'),
        'status' => $faker->randomElement($array = array(1, '0'))
    ];
});


$factory->define(PlayerAuth::class, function (Faker $faker) {
    return [
        'username' => $faker->userName,
        'user_code' => $faker->uuid,
        'password' => $faker->md5,
        'email_veri_code' => $faker->uuid,
        'email_verified' => $faker->randomElement($array = array(1, '0')),
        'phone_veri_code' => $faker->randomNumber(7, true),
        'phone_verified' => $faker->randomElement($array = array(1, '0')),
        'last_login' => $faker->dateTimeThisDecade($max = 'now')->format('Y-m-d H:i:s'),
        'last_action' => $faker->dateTimeThisYear($max = '+1 year')->format('Y-m-d H:i:s'),
        'user_disabled' => $faker->randomElement($array = array(1, '0')),
        'date_disabled' => $faker->dateTimeThisYear($max = '+1 year')->format('Y-m-d H:i:s'),
        'user_banned' => $faker->randomElement($array = array(1, '0')),
        'date_banned' => $faker->dateTimeThisYear($max = '+1 year')->format('Y-m-d H:i:s'),
        'activation_code' => $faker->uuid,
        'activation_code_activated' => $faker->randomElement($array = array(1, '0')),
        'activation_code_expire' => $faker->dateTimeThisYear($max = '+1 year')->format('Y-m-d H:i:s'),
    ];
});

$factory->define(PlayerProfile::class, function (Faker $faker) {
    return [
        'surname' => $faker->lastName,
        'firstname' => $faker->firstName($gender = null),
        'phone' => $faker->e164PhoneNumber,
        'email' => $faker->safeEmail,
        'avatar' => $faker->imageUrl($width = 640, $height = 480)
    ];
});
