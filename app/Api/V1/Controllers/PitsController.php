<?php


namespace App\Api\V1\Controllers;

use App\Api\V1\Models\AdminRole;
use App\Http\Controllers\Api\V1\BaseController;
// use App\oAuthClient;
// use App\Libraries\Encryption;
// use GuzzleHttp\Client;
use Illuminate\Http\Request;
// use App\Transformers\AuthorizationTransformer;
// use App\Jobs\SendRegisterEmail;
// use Illuminate\Support\Facades\Auth;
// use Illuminate\Support\Facades\DB;
// use Illuminate\Support\Facades\Hash;
// use Illuminate\Support\Facades\Log;
// use Dingo\Api\Routing\Helpers;
use App\Http\Controllers\Controller;
// use Dingo\Api\Exception\ValidationHttpException;
// use Illuminate\Support\Facades\Validator;

class PitsController extends BaseController
{

    protected $admin;

    public function __construct($admin)
    {
        $this->admin = $admin;
    }

    public function getUserId()
    {
        return "The pit details with user " . $this->admin;
    }
}
