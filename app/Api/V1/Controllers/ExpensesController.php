<?php


namespace App\Api\V1\Controllers;

use App\Contracts\Repository\IPitRepository;
use App\Api\V1\Controllers\BaseController;
use App\Contracts\Repository\IExpenses;
use App\Contracts\Repository\IPitTypesRepository;
use Illuminate\Http\Request;

use App\Http\Controllers\Controller;
use App\Utils\ExpensesMapper;
use App\Utils\PitMapper;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Dingo\Api\Routing\Helpers;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Passport\Passport;

class ExpensesController extends BaseController
{
    use Helpers;

    protected $expensesRepo;

    public function __construct(IExpenses $expensesRepo)
    {
        $this->expensesRepo = $expensesRepo;
    }



    public function findByMonth(Request $request)
    {
        $user = $request->user('api');

        $result = $this->expensesRepo->getForCurrentMonth($user->business_id);
        $response_message = $this->customHttpResponse(200, 'Success.', $result);
        return response()->json($response_message);
    }

    public function create(Request $request)
    {
        $validator = Validator::make(
            $request->input(),
            [
                'expenses' => 'required|array',
                'expenses.*.name' => 'required',
                'expenses.*.amount' => 'required',
            ]
        );

        $detail = $request->input();
        $user = $request->user('api');

        $detail['user_id'] = $user->id;
        $detail['business_id'] = $user->business_id;
        $detail['group_id'] = time() + ($user->id * 3759); //to serve as a basic unique id for group of expenses


        if ($validator->fails()) {
            $errors = $validator->errors();

            $response_message = $this->customHttpResponse(401, 'Check details. Some fields are required', $errors);
            return response()->json($response_message);
        }
        try {

            DB::beginTransaction();

            $dataToDB = ExpensesMapper::toExpensesDB($detail);
            $newExpense = $this->expensesRepo->add($dataToDB);
            DB::commit();
            $response_message = $this->customHttpResponse(200, 'Successful.', $newExpense);
            return response()->json($response_message);
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::info($th);
            $response_message = $this->customHttpResponse(500, 'DB error.');
            return response()->json($response_message);
        }
    }
}
