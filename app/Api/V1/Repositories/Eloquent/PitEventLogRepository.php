<?php

namespace App\Api\V1\Repositories\Eloquent;

use App\Api\V1\Models\ChipVaultIncomingLog;
use App\Api\V1\Models\PitEventLog;
use App\Api\V1\Repositories\EloquentRepository;
use App\Contracts\Repository\IPitEventLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PitEventLogRepository extends  EloquentRepository implements IPitEventLog
{
    private $pitEventLog;

    public function __construct(PitEventLog $pitEventLog)
    {
        parent::__construct();

        $this->pitEventLog = $pitEventLog;


        $this->userInfo = Auth::guard('api')->user();
    }

    public function model()
    {
        return PitEventLog::class;
    }


    //Note: Event type id for Sales(aka stakings) = 1
    public function getAllReport()
    {

        $query = "
                    SUM(CASE WHEN a.event_type = 1  THEN a.amount ELSE 0 END) as games_played,
                    SUM(CASE WHEN a.event_type = 2  THEN a.amount ELSE 0 END) as total_bet_amount,
                    SUM(CASE WHEN a.event_type = 3  THEN a.amount ELSE 0 END) as winning,
                    SUM(CASE WHEN a.event_type = 4  THEN a.amount ELSE 0 END) as profit
            ";

        return $this->pitEventLog
            ->from('pit_event_log as a')
            ->select(DB::raw($query))
            ->where('a.business_id', '=', 1)
            ->groupBy(DB::raw('DATE(a.created_at)'))
            ->get();
    }

    //Note: Event type id for Sales(aka stakings) = 1
    public function getSalesVolume($header, $start = '1990/01/01', $end = null)
    {
        $end = is_null($end) ? "DATE(CURRENT_DATE)" : "DATE('{$end}')";
        $query = ['DATE(a.created_at) as date'];
        foreach ($header as $head) {
            $query[] = "SUM(CASE WHEN a.event_type = 1 AND a.pit_game_type = {$head['id']} THEN a.amount ELSE 0 END) as {$head['name']}";
        }
        $query = implode(",", $query);
        return $this->pitEventLog
            ->from('pit_event_log as a')
            ->select(DB::raw($query))
            ->where('a.business_id', '=', 1)
            ->groupBy(DB::raw('DATE(a.created_at)'))
            ->havingRaw("DATE(date) BETWEEN DATE('{$start}') AND $end")
            ->get();
    }


    //Note: Event type id for Wins = 2
    public function getGameWins($header, $start = '1990/01/01', $end = null)
    {
        $end = is_null($end) ? "DATE(CURRENT_DATE)" : "DATE('{$end}')";
        $query = ['DATE(a.created_at) as date'];
        foreach ($header as $head) {
            $query[] = "SUM(CASE WHEN a.event_type = 2 AND a.pit_game_type = {$head['id']} THEN a.amount ELSE 0 END) as {$head['name']}";
        }
        $query = implode(",", $query);
        return $this->pitEventLog
            ->from('pit_event_log as a')
            ->select(DB::raw($query))
            ->where('a.business_id', '=', 1)
            ->groupBy(DB::raw('DATE(a.created_at)'))
            ->havingRaw("DATE(date) BETWEEN DATE('{$start}') AND $end")
            ->get();
    }
}
