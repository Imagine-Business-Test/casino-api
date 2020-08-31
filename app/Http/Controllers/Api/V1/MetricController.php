<?php
namespace App\Http\Controllers\Api\V1;

use App\Models\Product;
use App\Models\ProductMetricWeight;
use App\Models\User;
use Illuminate\Http\Request;
use App\Models\Authorization;
use App\Transformers\UserTransformer;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;


class MetricController extends BaseController
{
    /**
     * @api {get} /users user list
     * @apiDescription user list
     * @apiGroup user
     * @apiPermission none
     * @apiVersion 0.1.0
     * @apiSuccessExample {json} Success-Response:
     *     HTTP/1.1 200 OK
     *     {
     *       "data": [
     *         {
     *           "id": 2,
     *           "email": "490554191@qq.com",
     *           "name": "fff",
     *           "created_at": "2015-11-12 10:37:14",
     *           "updated_at": "2015-11-13 02:26:36",
     *           "deleted_at": null
     *         }
     *       ],
     *       "meta": {
     *         "pagination": {
     *           "total": 1,
     *           "count": 1,
     *           "per_page": 15,
     *           "current_page": 1,
     *           "total_pages": 1,
     *           "links": []
     *         }
     *       }
     *     }
     */
    public function category_product(Request $request, $id)
    {
//        return $this->response->array($request->all())->setStatusCode(404);
        $request->id = $id;
//        return $this->response->array(['id', $id ])->setStatusCode(404);
        $validator = \Illuminate\Support\Facades\Validator::make(['id'=> $id ], [
            'id' => 'required|string'
        ]);

        if ($validator->fails()) {
            return $this->errorBadRequest($validator);
        }

        $product_id = $id;
        $product = Product::find($product_id);

        if(!is_null($product) )
        {
            $status = true;
            $msg = 'Product Found!';
            $result['data'] = [
                                'status' => $status,
                                'name' => $product->product_name,
                                'description' => null,
                                'id' => $product->id,
                                'createdAt' => !is_null($product->created_at)  ? $product->created_at->toDateTimeString() : "1970-01-01 00:00:00",
                                'updatedAt' => !is_null($product->updated_at)  ? $product->updated_at->toDateTimeString() : "1970-01-01 00:00:00"

                             ];
            $result['message'] =  $msg;

            return $this->response->array($result)->setStatusCode(200);
        }
        else
        {
            $status = false;
            $msg = 'This product does not exist!';
            $result['data'] = [
                                'status' => $status,
                             ];
            $result['message'] =  $msg;
            return $this->response->array($result)->setStatusCode(404);
        }
    }

    public function feedback(Request $request)
    {
//        return $this->response->array($request->all())->setStatusCode(404);
//        return $this->response->array(['id', $id ])->setStatusCode(404);
        $validator = Validator::make([$request->input()], [
            'id' => 'required|string'
        ]);

        if ($validator->fails()) {
//            return $this->errorBadRequest($validator);
        }
        $product = Product::all()->first();

        if(!is_null($product) )
        {
            $status = true;
            $msg = 'Feedbacks Found!';
            $result['data'] = [
                'status' => $status,
                'userId' => Auth::user()->id,
                'message' =>$msg,
                'latitude' => null,
                'longitude' => null,
                'id' => $product->id,
                'createdAt' => !is_null($product->created_at)  ? $product->created_at->toDateTimeString() : "1970-01-01 00:00:00",
                'updatedAt' => !is_null($product->updated_at)  ? $product->updated_at->toDateTimeString() : "1970-01-01 00:00:00"

            ];

            return $this->response->array($result)->setStatusCode(200);
        }
        else
        {
            $status = false;
            $msg = 'Feedback not found!';
            $result['data'] = [
                                    'status' => $status,
                                    'message' =>$msg,
                              ];
            return $this->response->array($result)->setStatusCode(404);
        }
    }

    public function get_feedback(Request $request)
    {
//        return $this->response->array($request->all())->setStatusCode(404);
//        return $this->response->array(['id', $id ])->setStatusCode(404);
        $validator = Validator::make([$request->input()], [
            'id' => 'required|string'
        ]);

        if ($validator->fails()) {
//            return $this->errorBadRequest($validator);
        }
        $product = Product::all()->first();

        if(!is_null($product) )
        {
            $status = true;
            $msg = 'Feedbacks Found!';
            $result['data'] = [
                'status' => $status,
                'userId' => Auth::user()->id,
                'message' =>$msg,
                'latitude' => null,
                'longitude' => null,
                'id' => $product->id,
                'createdAt' => !is_null($product->created_at)  ? $product->created_at->toDateTimeString() : "1970-01-01 00:00:00",
                'updatedAt' => !is_null($product->updated_at)  ? $product->updated_at->toDateTimeString() : "1970-01-01 00:00:00"

            ];

            return $this->response->array($result)->setStatusCode(200);
        }
        else
        {
            $status = false;
            $msg = 'Feedback not found!';
            $result['data'] = [
                'status' => $status,
                'message' =>$msg,
            ];
            return $this->response->array($result)->setStatusCode(404);
        }
    }

    public function index(User $user)
    {
        if ($this->user()->role == 'admin') {
            $users = User::whereIn('role', ['user', 'admin'])->paginate();
        }
        else if ($this->user()->role == 'superadmin') {
            $users = User::paginate();
        }
        else {
            $users = User::paginate();
            //return $this->response->errorUnauthorized();
        }

        return $this->response->paginator($users, new UserTransformer());
    }

    public function converter(Request $request)
    {
//        return $this->response->array($request->all())->setStatusCode(200);
        $validator = Validator::make($request->input(), [
            'productId' => 'required',
            'metric' => 'required',
        ]);

        if ($validator->fails()) {
            return $this->errorBadRequest($validator);
        }

        $product_id = $request->productId;
        $metric_id = $request->metric;
        $variety = array_key_exists('variety', $request->all() ) ? $request->variety : null;

        $product_metric_weight =  ProductMetricWeight::with(['metric', 'product'])->where('product_id',  '=' , $product_id )->where('metric_id',  '=' ,  $metric_id );


            if(!is_null($variety)) {
                $product_metric_weight = $product_metric_weight
                    ->whereIn('variety_id', [0, $variety]);
            }

        $product_metric_weight = $product_metric_weight->get();

//        return $this->response->array($product_metric_weight)->setStatusCode(200);

        if( count($product_metric_weight) > 0 )
        {
            $weight = $product_metric_weight->first()->weight;

            $status = true;
            $msg = 'Weight Computed!';
            $result['data'] = [
                                'status' => $status,
                                'weight' => $weight
                              ];
            $result['message'] =  $msg;

            return $this->response->array($result)->setStatusCode(200);
        }
        else
        {
            $status = false;
            $msg = 'This weight can not be computed!';
            $result['data'] = [
                                     'status' => $status,
                                ];
            $result['message'] =  $msg;
            return $this->response->array($result)->setStatusCode(404);

        }
    }

    public function metric(Request $request)
    {
//        return $this->response->array($request->all())->setStatusCode(200);
        $validator = Validator::make($request->input(), [
            'productId' => 'required',
            'metric' => 'required',
            'weight' => 'required'
        ]);

        if ($validator->fails()) {
            return $this->errorBadRequest($validator);
        }

        $product_id = $request->productId;
        $metric_id = $request->metric;
        $weight = $request->weight;

        $product_metric_weight =  ProductMetricWeight::with(['metric', 'product'])->where('product_id',  '=' , $product_id )
                                                                                    ->where('metric_id',  '=' ,  $metric_id )->get();
        if(  count($product_metric_weight) > 0 )
        {

            $product_metric_weight->first()->weight = $weight;
            $product_metric_weight->first()->save();
            $msg = "Weight was updated";
            $result['data'] = [
                'product_metric_weight' => $product_metric_weight->first(),
            ];
            $result['message'] =  $msg;
            return $this->response->array($result)->setStatusCode(200);



        }
        else
        {
            $new_product_weight = new ProductMetricWeight;
            $new_product_weight->metric_id = $metric_id  ;
            $new_product_weight->product_id = $product_id;
            $new_product_weight->weight = $weight;

            $new_product_weight->save();

            $msg = "Weight was created";
            $result['data'] = [
                'product_metric_weight' => $new_product_weight,
            ];
            $result['message'] =  $msg;
            return $this->response->array($result)->setStatusCode(200);

        }
    }

    /**
     * @api {get} /users/{id} user's info
     * @apiDescription user's info
     * @apiGroup user
     * @apiPermission none
     * @apiVersion 0.1.0
     * @apiSuccessExample {json} Success-Response:
     *     HTTP/1.1 200 OK
     *     {
     *       "data": {
     *         "id": 2,
     *         "email": "490554191@qq.com",
     *         "name": "fff",
     *         "created_at": "2015-11-12 10:37:14",
     *         "updated_at": "2015-11-13 02:26:36",
     *         "deleted_at": null
     *       }
     *     }
     */
    public function show($id)
    {
        $user = User::findOrFail($id);
        return $this->response->item($user, new UserTransformer());
    }
    /**
     * @api {get} /user current user info
     * @apiDescription current user info
     * @apiGroup user
     * @apiPermission JWT
     * @apiVersion 0.1.0
     * @apiSuccessExample {json} Success-Response:
     *     HTTP/1.1 200 OK
     *     {
     *       "data": {
     *         "id": 2,
     *         "email": 'user@gmail.com',
     *         "name": "foobar",
     *         "created_at": "2015-09-08 09:13:57",
     *         "updated_at": "2015-09-08 09:13:57",
     *         "deleted_at": null
     *       }
     *     }
     */
    public function userShow()
    {
        return $this->response->item($this->user(), new UserTransformer());
    }
    
    /**
     * @api {post} create a user
     * @apiDescription create a user
     * @apiGroup user
     * @apiPermission none
     * @apiVersion 0.1.0
     * @apiParam {Email}  email   email[unique]
     * @apiParam {String} password   password
     * @apiParam {String} name      name
     * @apiParam {Date}  birthdate  birthdate
     * @apiParam {String} role   role
     * @apiParam {String} active      active
     * @apiSuccessExample {json} Success-Response:
     *     HTTP/1.1 200 OK
     *     {
     *         token: eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJzdWIiOjEsImlzcyI6Imh0dHA6XC9cL21vYmlsZS5kZWZhcmEuY29tXC9hdXRoXC90b2tlbiIsImlhdCI6IjE0NDU0MjY0MTAiLCJleHAiOiIxNDQ1NjQyNDIxIiwibmJmIjoiMTQ0NTQyNjQyMSIsImp0aSI6Ijk3OTRjMTljYTk1NTdkNDQyYzBiMzk0ZjI2N2QzMTMxIn0.9UPMTxo3_PudxTWldsf4ag0PHq1rK8yO9e5vqdwRZLY
     *     }
     * @apiErrorExample {json} Error-Response:
     *     HTTP/1.1 400 Bad Request
     *     {
     *         "email": [
     *             "Email has been registered by others"
     *         ],
     *     }
     */
    public function store(Request $request)
    {
        // forbidden
        if ($this->user()->role == 'user') {
            return $this->response->errorForbidden();
        }

        $validator = Validator::make($request->all(), [
            'email'       => 'required|email|unique:users',
            'name'        => 'required|min:3',
            'password'    => 'required|confirmed|min:3',
            'birthdate'   => 'nullable|date',
            'role'        => 'required|string',
            'active'      => 'required'
        ]);

        if ($validator->fails()) {
            return $this->errorBadRequest($validator);
        }

        $active = (int)($request->active === 'true');

        $attributes = [
            'email' => $request->get('email'),
            'name' => $request->get('name'),
            'password' => app('hash')->make($request->get('password')),
            'created_at' => \Carbon\Carbon::now('Asia/Jakarta'),
            'updated_at' => \Carbon\Carbon::now('Asia/Jakarta'),
            'birthdate' => $request->birthdate,
            'role' => $request->role,
            'active' => $active
        ];
        $user = User::create($attributes);

        return $this->response->item($user, new UserTransformer());
    }

    /**
     * @api {put} /user/id update user
     * @apiDescription update user
     * @apiGroup user
     * @apiPermission JWT
     * @apiVersion 0.1.0
     * @apiParam {String} old_password          
     * @apiParam {String} password              
     * @apiParam {String} password_confirmation 
     * @apiSuccessExample {json} Success-Response:
     *     HTTP/1.1 204 No Content
     * @apiErrorExample {json} Error-Response:
     *     HTTP/1.1 400 Bad Request
     *     {
     *         "password": [
     *             "The password entered twice is inconsistent",
     *             "Old and new passwords can not be the same"
     *         ],
     *         "password_confirmation": [
     *             "The password entered twice is inconsistent"
     *         ],
     *         "old_password": [
     *             "wrong password"
     *         ]
     *     }
     */
    public function update($id, Request $request)
    {
        // forbidden
        if ($this->user()->role == 'user') {
            return $this->response->errorForbidden();
        }
        $user = User::find($id);
        if (! $user) {
            return $this->response->errorNotFound();
        }
        if ($request->password != "") {
          $validator = \Validator::make($request->input(), [
              'email'                 => 'required|min:3|email|unique:users,email,'. $id,
              'name'                  => 'required|min:3|max:100',
              'password'              => 'required|confirmed|min:3',
              'role'                  => 'required|string',
              'birthdate'             => 'nullable|date',
              'active'                => 'required'
          ]);
        } 
        else {
          $validator = \Validator::make($request->all(), [
              'email'                 => 'required|min:3|email|unique:users,email,'. $id,
              'name'                  => 'required|min:3|max:100',
              'role'                  => 'required|string',
              'birthdate'             => 'nullable|date',
              'active'                => 'required'
          ]);
        }
        if ($validator->fails()) {
            return $this->errorBadRequest($validator);
        }

        $active = (int)($request->active === 'true');

        $user->name = $request->name;
        $user->email = $request->email;
        $user->role = $request->role;
        $user->birthdate = $request->birthdate;
        $user->active = $active;
        
        $user->updated_at = \Carbon\Carbon::now('Asia/Jakarta');
        if ($request->password != "") {
            $user->password = app('hash')->make($request->password);
        }
        $user->save();
        return $this->response->item($user, new UserTransformer());
    }

    /**
     * @api {put} /user/password update password
     * @apiDescription update password
     * @apiGroup user
     * @apiPermission JWT
     * @apiVersion 0.1.0
     * @apiParam {String} old_password          
     * @apiParam {String} password              
     * @apiParam {String} password_confirmation 
     * @apiSuccessExample {json} Success-Response:
     *     HTTP/1.1 204 No Content
     * @apiErrorExample {json} Error-Response:
     *     HTTP/1.1 400 Bad Request
     *     {
     *         "password": [
     *             "The password entered twice is inconsistent",
     *             "Old and new passwords can not be the same"
     *         ],
     *         "password_confirmation": [
     *             "The password entered twice is inconsistent"
     *         ],
     *         "old_password": [
     *             "wrong password"
     *         ]
     *     }
     */
    public function updatePassword(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'old_password' => 'required',
            //'password' => 'required|confirmed|different:old_password',
            'password' => 'required|confirmed',
            'password_confirmation' => 'required|same:password',
        ]);
        if ($validator->fails()) {
            return $this->errorBadRequest($validator);
        }
        $user = $this->user();

        $auth = \Auth::once([
            'email' => $user->email,
            'password' => $request->get('old_password'),
        ]);
        
        if (! $auth) {
            return $this->response->errorUnauthorized();
        }
        
        $password = app('hash')->make($request->get('password'));
        $user->update(['password' => $password]);
        return $this->response->noContent();
    }

    /**
     * @api {put} /user/password update profile
     * @apiDescription update profile
     * @apiGroup user
     * @apiPermission JWT
     * @apiVersion 0.1.0
     * @apiParam {String} name          
     * @apiParam {Date} birthdate              
     * @apiSuccessExample {json} Success-Response:
     *     HTTP/1.1 204 No Content
     * @apiErrorExample {json} Error-Response:
     *     HTTP/1.1 400 Bad Request
     *     {
     *         "name": [
     *             "Name is not valid"
     *         ],
     *         "birthdate": [
     *             "Birthdate is not valid"
     *         ]
     *     }
     */
    public function updateProfile(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'name' => 'required|string|min:3|max:50',
            'birthdate'   => 'nullable|date'
        ]);

        if ($validator->fails()) {
            return $this->errorBadRequest($validator);
        }

        $user = $this->user();
        $user->updated_at = \Carbon\Carbon::now('Asia/Jakarta');
        $user->name = $request->name;
        $user->birthdate = $request->birthdate;
        $user->save();

        return $this->response->item($user, new UserTransformer());
    }

    public function destroy($id)
    {
        // forbidden
        if ($this->user()->role == 'user') {
            return $this->response->errorForbidden();
        }
        $user = User::find($id);
        if (! $user) {
            return $this->response->errorNotFound();
        }
        // $user->delete();
        $user->forceDelete();
        return $this->response->item($user, new UserTransformer());
    }
    
}