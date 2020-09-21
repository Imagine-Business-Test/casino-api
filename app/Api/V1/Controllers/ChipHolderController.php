<?php


namespace App\Api\V1\Controllers;

use App\Contracts\Repository\IChipHolderRepository;
use App\Utils\ChipHolderMapper;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;


class ChipHolderController extends BaseController
{

    private $chipHolderRepo;

    public function __construct(IChipHolderRepository $chipHolderRepo)
    {
        $this->chipHolderRepo = $chipHolderRepo;
    }

    public function findAll()
    {
        $result = $this->chipHolderRepo->findAll();
        Log::info("aaaadd");
        Log::info($result);
        $result = ChipHolderMapper::pruneChipHolder($result);
        $response_message = $this->customHttpResponse(200, 'Success.', $result);
        return response()->json($response_message);
    }
}
