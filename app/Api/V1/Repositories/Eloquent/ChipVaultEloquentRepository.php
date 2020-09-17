<?php

namespace App\Api\V1\Repositories\Eloquent;

use App\Api\V1\Models\ChipVault;
use App\Api\V1\Models\ChipVaultIncoming;
use App\Api\V1\Models\ChipVaultIncomingLog;
use App\Api\V1\Repositories\EloquentRepository;
use App\Contracts\Repository\IChipVaultRepository;
use App\Utils\Mapper;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ChipVaultEloquentRepository extends  EloquentRepository implements IChipVaultRepository
{

    private $chipVault;
    private $chipVI;
    private $chipVIL;
    private $userInfo;
    private $activeVault;
    public function __construct(ChipVault $chipVault, ChipVaultIncoming $chipVI, ChipVaultIncomingLog $chipVIL)
    {
        parent::__construct();
        // $this->userInfo = $auth->getUser();

        $this->chipVault = $chipVault;
        $this->chipVI = $chipVI;
        $this->chipVIL = $chipVIL;
        $this->userInfo = Auth::guard('api')->user();

        $this->activeVault = $this->setActiveVault();

        Log::info("getting auth user = " . json_encode($this->userInfo));
        Log::info("getting auth user = " . json_encode($this->activeVault));
    }

    public function model()
    {
        return ChipVault::class;
    }
    // ->where(function ($query) {
    //     $query->where('locked','<>','1') //has expired
    //         ->orWhere('locked', '<>', '1'); //has been completed.
    // });
    public function setActiveVault()
    {
        return $this->chipVault
            ->where('business_id', '=', $this->userInfo->business_id)
            ->where('locked', '<>', '1')
            ->first();
    }

    public function dispatch($details)
    {
    }

    public function receive($details)
    {

        try {
            DB::beginTransaction();

            $details['vault_id'] = $this->activeVault->id;
            $details['business_id'] = $this->userInfo->business_id;
            $details['user_id'] = $this->userInfo->id;

            //create VI
            $dbDataVI = Mapper::toChipVaultIncoming($details);
            $vi = $this->chipVI->create($dbDataVI);

            $details['trans_id'] = $vi->id;

            //create VIL
            $dbDataVIL = Mapper::toChipVaultIncomingLog($details);
            $biz = $this->chipVIL->insert($dbDataVIL);


            //update vault
            $dbDataCV = Mapper::updateChipVault($details);
            $auth2 = $this->chipVault->where('id', '=', $this->activeVault->id)->update($dbDataCV);


            DB::commit();
            //send nicer data to the user

            $response_message = $this->customHttpResponse(200, 'Chips added successful.');
            return response()->json($response_message);
        } catch (\Throwable $th) {

            DB::rollBack();

            //Log neccessary status detail(s) for debugging purpose.
            Log::info("One of the DB statements failed. Error: " . $th);

            //send nicer data to the user
            $response_message = $this->customHttpResponse(500, 'Transaction Error.');
            return response()->json($response_message);
        }
    }
}
