<?php

namespace App\Api\V1\Repositories\Eloquent;

use App\Api\V1\Models\ChipVault;
use App\Api\V1\Models\ChipVaultIncoming;
use App\Api\V1\Models\ChipVaultIncomingLog;
use App\Api\V1\Models\ChipVaultOutgoingLog;
use App\Api\V1\Models\ExchangeVault;
use App\Api\V1\Models\ExchangeVaultIncomingLog;
use App\Api\V1\Models\ExchangeVaultOutgoingLog;
use App\Api\V1\Repositories\EloquentRepository;
use App\Contracts\Repository\IChipVaultRepository;
use App\Utils\Mapper;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ChipVaultEloquentRepository extends  EloquentRepository implements IChipVaultRepository
{

    private $chipVault;
    private $chipVOL;
    private $chipVIL;

    private $exchVault;
    private $exchVIL;
    private $exchVOL;

    private $userInfo;
    public function __construct(
        ChipVault $chipVault,
        ChipVaultIncomingLog $chipVIL,
        ChipVaultOutgoingLog $chipVOL,
        ExchangeVault $exchVault,
        ExchangeVaultIncomingLog $exchVIL,
        ExchangeVaultOutgoingLog $exchVOL
    ) {
        parent::__construct();

        $this->chipVault = $chipVault;
        $this->chipVIL = $chipVIL;
        $this->chipVOL = $chipVOL;

        $this->exchVault = $exchVault;
        $this->exchVIL = $exchVIL;
        $this->exchVOL = $exchVOL;

        $this->userInfo = Auth::guard('api')->user();
    }

    public function model()
    {
        return ChipVault::class;
    }
    public function getAllByBusiness()
    {
        return $this->chipVault
            ->select('id', 'chip_value')
            ->where('business_id', '=', $this->userInfo->business_id)
            ->where('locked', '=', '0')
            ->orWhereNull('locked')
            ->get();
    }
    public function getVaultChipDispensable($vaultDenomination, $incomingAmount)
    {

        if ($incomingAmount < $vaultDenomination || is_null($vaultDenomination) || $vaultDenomination == 0) {
            /** 
             * This vault is not for the incoming amount(Not it's mate :).
             * Its simple, a single chip's value in this vault is greaeter than the incoming amount
             * It should check lower denomination vaults instead.
             */

            return null;
        }
        $dispensableQTY = floor($incomingAmount / $vaultDenomination);
        $dispensableAmount = $dispensableQTY * $vaultDenomination;
        $expectedBalance = $incomingAmount - $dispensableAmount;

        return ['qty' => $dispensableQTY, 'amount' => $dispensableAmount, 'balance' => $expectedBalance];
    }
    public function dispatchAuto($details)
    {

        $businessID = $this->userInfo->business_id;
        $amount = $details['total_amount'];
        $remaining = $amount;
        $dbAmount = 0;
        $operatedVaults = [];
        try {
            DB::beginTransaction();

            //get all the accessible vaults
            $vaults = $this->chipVault
                ->where('business_id', '=',  $businessID)
                ->where('locked', '=', '0')
                ->orWhereNull('locked')
                ->lockForUpdate()
                ->get();

            //sum all the funds in the different available vaults.
            foreach ($vaults as $vault) {
                $dbAmount += $vault->total_amount;
            }

            //check if the intended amount is higher than what is available.
            if ($amount > $dbAmount) {
                $response_message = $this->customHttpResponse(405, 'Insufficient Vault Funds. Adjust the individual vault [Amount] or [Qty] fields and try again.');
                return response()->json($response_message);
            }

            foreach ($vaults as $vault) {
                $vault = (object) $vault;
                $dispensable = $this->getVaultChipDispensable($vault->chip_value, $remaining);

                if ($vault->total_amount >= $amount && !is_null($dispensable) && $vault->qty >= $dispensable['qty']) {
                    $dispensable = (object) $dispensable;
                    $vaultInfo = [
                        'vault_id' => $vault->id,
                        'vault_value' => $vault->chip_value,
                        'qty' => $dispensable->qty,
                        'amount' => $dispensable->amount,
                    ];
                    $operatedVaults[] = $vaultInfo;
                    $remaining = $dispensable->balance;
                } else { //check another vault
                    continue;
                }
            }

            DB::commit(); //release lockForUpdate

            // Log::info("operated vault");
            // Log::info(json_encode($operatedVaults));
            // Log::info(json_encode($remaining));

            if ($remaining > 0) {
                $response_message = $this->customHttpResponse(
                    201,
                    'Partial dispense. Amount supplied will not be completely dispensed. 
                     Reason: either a missing smaller denomination vault or an empty smaller denomination vault.
                     Confirm the details and send back to this endpoint.',
                    $operatedVaults
                );
                return response()->json($response_message);
            } else {
                $response_message = $this->customHttpResponse(
                    202,
                    'Complete dispense. All amount supplied will be dispensed.
                Confirm the details and send back to this endpoint.',
                    $operatedVaults
                );
                return response()->json($response_message);
            }
        } catch (\Throwable $th) {

            DB::rollBack();

            //Log neccessary status detail(s) for debugging purpose.
            Log::info("One of the DB statements failed. Error: " . $th);

            //send nicer data to the user
            $response_message = $this->customHttpResponse(500, 'Transaction Error.');
            return response()->json($response_message);
        }
    }
    public function dispatchControlled($details)
    {
        try {
            DB::beginTransaction();

            foreach ($details['vaults'] as $vault) {
                $vault = (object) $vault;

                $result = $this->chipVault
                    ->where('id', '=', $vault->vault_id)
                    ->where('qty', '>=', $vault->qty)
                    ->where('total_amount', '>=', $vault->amount)
                    ->where(function ($query) {
                        $query->where('locked', '=', '0')
                            ->orWhereNull('locked');
                    })
                    ->lockForUpdate()
                    ->first();


                if (is_null($result)) {

                    DB::rollBack(); //release lockForUpdate

                    $response_message = $this->customHttpResponse(
                        405,
                        'Insufficient Vault Funds. Atleast one of the vaults specified does not have the given amount.
                     Please adjust the individual vault [Amount] or [Qty] fields. Alternatively, you can ignore the
                     [vaults] field and the system would intelligently iterate through the different vaults that has sufficient funds
                      and get your chip from there.
                    '
                    );
                    return response()->json($response_message);
                }
            }

            $details['business_id'] = $this->userInfo->business_id;
            $details['user_id'] = $this->userInfo->id;




            //update vault
            $dbDataCV = Mapper::debitChipVault($details);
            DB::statement($dbDataCV);


            //create VOL
            $holderType = 2; //where , 2 = exchange vault
            $details['holder_type'] = $holderType;

            $dbDataVOL = Mapper::toChipVaultOutgoingLog($details);
            $biz = $this->chipVOL->insert($dbDataVOL);

            //////////////////////
            /////   Exchange
            //////////////////////
            //todo: get vault value since thats what links different vaults together
            //update exchange

            $dbDataCV = Mapper::creditExchangeVault($details);
            DB::statement($dbDataCV);

            //create exhange EIL
            $holderType = 1; //where , 1 = main vault
            $details['holder_type'] = $holderType;
            $dbDataEIL = Mapper::toExchangeVaultIncomingLog($details);
            $biz = $this->exchVIL->insert($dbDataEIL);

            // Log::info("info");
            // Log::info($dbDataCV);
            // Log::info($dbDataEIL);

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

    public function receive($details)
    {

        try {
            DB::beginTransaction();

            $details['business_id'] = $this->userInfo->business_id;
            $details['user_id'] = $this->userInfo->id;




            //update vault
            $dbDataCV = Mapper::updateChipVault($details);
            // Log::info("vault multi = ");
            // Log::info(json_encode($dbDataCV));

            $auth2 = DB::statement($dbDataCV);


            //create VIL
            $dbDataVIL = Mapper::toChipVaultIncomingLog($details);
            $biz = $this->chipVIL->insert($dbDataVIL);

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
