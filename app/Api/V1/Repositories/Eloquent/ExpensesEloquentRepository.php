<?php

namespace App\Api\V1\Repositories\Eloquent;

use App\Api\V1\Models\Expenses;
use App\Api\V1\Models\Pits;
use App\Api\V1\Repositories\EloquentRepository;
use App\Contracts\Repository\IExpenses;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExpensesEloquentRepository extends  EloquentRepository implements IExpenses
{

    private $expenses;

    public function __construct(Expenses $expenses)
    {
        parent::__construct();

        $this->expenses = $expenses;
    }

    public function model()
    {
        return Expenses::class;
    }

    public function add($details)
    {
        foreach ($details as $detail) {
            $newEntity = new Expenses();
            $newEntity->name = $detail['name'];
            $newEntity->amount = $detail['amount'];
            $newEntity->group_id = $detail['group_id'];
            $newEntity->business_id = $detail['business_id'];
            $newEntity->created_by = $detail['created_by'];
            $detail['descr'] !== null ? $newEntity->descr = $detail['descr'] : null;
            $newEntity->save();
        }

        return ['success' => true];
    }

    public function getForCurrentMonth($BusinessID)
    {
        return $this->expenses
            ->from('expenses')
            ->select(DB::raw("name,sum(amount) as amount,MONTH(created_at) as current_month"))
            ->where('business_id', '=', $BusinessID)
            ->groupBy(DB::raw('name,current_month'))
            ->havingRaw("current_month = MONTH(CURRENT_TIMESTAMP)")
            ->get();
    }
}
