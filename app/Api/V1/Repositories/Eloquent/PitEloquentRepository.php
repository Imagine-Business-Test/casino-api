<?php

namespace App\Api\V1\Repositories\Eloquent;

use App\Api\V1\Models\PitRules;
use App\Api\V1\Models\Pits;
use App\Contracts\Repository\IPitRepository;
use App\Api\V1\Repositories\EloquentRepository;

class PitEloquentRepository extends  EloquentRepository implements IPitRepository
{

    public function model()
    {
        return Pits::class;
    }

    public function add($detail)
    {
        $newPit = new Pits();
        $newPit->name = $detail['name'];
        $newPit->pit_boss = $detail['pit_boss'];
        $newPit->pit_float = $detail['pit_float'];
        $newPit->pit_game_type = $detail['pit_game_type'];
        $detail['in_service'] !== null ? $newPit->in_service = $detail['in_service'] : null; //conditional/optional field
        $newPit->opening_amount = $detail['opening_amount'];
        $newPit->created_by = $detail['created_by'];
        $newPit->business_id = $detail['business_id'];
        $newPit->save();


        $newPitRules = new PitRules();
        $newPitRules->pit_id = $newPit->id;
        $detail['bet_max'] !== null ? $newPitRules->bet_max = $detail['bet_max'] : null; //conditional/optional field
        $detail['bet_min'] !== null ? $newPitRules->bet_min = $detail['bet_min'] : null; //conditional/optional field
        $newPitRules->created_by = $detail['created_by'];
        $newPitRules->business_id = $detail['business_id'];
        $newPitRules->save();

        return ['pit_id' => $newPit->id, 'rule_id' => $newPitRules->id];
    }
}
