<?php

namespace App\Services;

use App\Models\EwalletTransaction;
use App\Models\Order;
use App\Models\SaveOn;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use App\Models\TSBCommission;
use Illuminate\Support\Facades\Log;

/**
 * Class LeadershipCommission
 * @package App\Services
 */
class TsbCommissionService
{
    private $sheetMappingIndex = -1;

    private $sheetMappings = [
        0 => [
            'paidAt' => 'Paid Date',
            'commission' => 'Commission',
            'cv' => 'CV',
            'bv' => 'BV',
            'qv' => 'QV',
            'tsbAmount' => 'Direct Bonus',
            'sorUserId' => 'Referring User ID',
            'distId' => 'Referring User Contract Number',
            'reservation' => 'Reservation #',
            'memo' => 'Memo'
        ],
        1 => [
            'sorUserId' => 'SOR User ID',
            'distId' => 'Referring User Contract Number',
            'club' => 'VacationClubName',
            'tsbAmount' => 'DIRECT TSB',
            'reservation' => 'ReservationId',
            'cv' => 'CV'
        ],
        2 => [
            'distId' => 'ContractNumber',
            'tsbAmount' => 'Amount',
            'cv' => 'CV',
            'club' => 'VacationClubName',
            'reservation' => 'ReservationId'
        ]
    ];

    const TABLE_NAME = 'tsb_commission';
    const COMMISSION_TYPE = 'TSB';

    const CALCULATED_STATUS = 'calculated';
    const POSTED_STATUS = 'posted';
    const PAID_STATUS = 'paid';

    public function isPostedCommission($startDate, $endDate)
    {
        return TSBCommission::whereDate('paid_date', '>=', $startDate)
                ->whereDate('paid_date', '<=', $endDate)
                ->where('status', TSBCommissionService::POSTED_STATUS)
                ->count() > 0;
    }

    public function isPaidCommission($startDate, $endDate)
    {
        return TSBCommission::whereDate('paid_date', '>=', $startDate)
                ->whereDate('paid_date', '<=', $endDate)
                ->where('status', TSBCommissionService::PAID_STATUS)
                ->count() > 0;
    }

    private function parseTsbCSV($filename)
    {
        Log::debug('Parsing TSB csv...');

        $csv = array_map('str_getcsv', file($filename));

        $headers = $csv[0];

        array_walk($csv, function(&$a) use ($csv, $headers) {
            $a = array_combine($headers, $a);
        });

        array_shift($csv); // remove header row

        Log::debug('Finished parsing TSB csv... Number of rows: ' . count($csv));

        return $csv;
    }

    private function determineSheetType($row)
    {
        // Hard coded unique headers that only exist in individual ones
        // See $this->sheetMappings

        if (isset($row['SOR User ID'])) {
            return 1;
        } else if (isset($row['BV'])) {
            return 0;
        }

        return 2;
    }

    public function calculateCommission($filename, Carbon $endDate)
    {
        $results = $this->parseTsbCSV($filename);

        $this->sheetMappingIndex = $this->determineSheetType($results[0]);
        Log::info('Sheet type detected, type: ' . strval($this->sheetMappingIndex));

        $sheetMappings = $this->sheetMappings[$this->sheetMappingIndex];

        $defaultPaidAt = $endDate->copy()
            ->setDate($endDate->year, $endDate->month, 20)
            ->setTime(12, 0, 0, 0);

        $defaults = [
            'paidAt' => $defaultPaidAt->format('Y-m-d H:i:s'),
            'memo' => $defaultPaidAt->format('F Y') . ' Travel Bookings',
            'qv' => 0,
            'bv' => 0
        ];

        $isBookingType = $this->sheetMappingIndex == 2;

        $numHeaders = count(array_keys($sheetMappings));

        Log::debug('Calculating TSB...');

        $counter = 1;
        $numResults = count($results);

        foreach ($results as $result) {
            Log::info($counter++ . '/' . $numResults);

            $numEmptyFields = 0;

            foreach ($sheetMappings as $header=>$mapping) {
                if (empty($result[$mapping])) {
                    $numEmptyFields++;
                }
            }

            if ($numEmptyFields == $numHeaders) {
                Log::warning('Empty record skipped!');
                continue;
            }

            $parsedResult = $this->parseResult($result, $sheetMappings, $defaults);


            if ($isBookingType) {
                if (stripos($parsedResult['club'], 'i go 4') === false) {
                    Log::info('Skipping non-iGo result (sheet mapping type 2 -- booking orders)', ['parsedResult' => $parsedResult]);
                    continue;
                }

                // Booking sheets only have distId (ContractNumber), so we have to grab the user id and then get a sor user id from that
                $distId = $parsedResult['distId'];
                $user = User::getByDistId($distId);

                if (!$user) {
                    Log::warning('Skipping. User not found with distid: ' . $distId, ['parsedResult' => $parsedResult]);
                    continue;
                }

                $parsedResult['sorUserId'] = SaveOn::getSORUserId($user->id);

                if (!$parsedResult['sorUserId']) {
                    Log::warning('Skipping. User not part of SOR.', ['parsedResult' => $parsedResult]);
                    continue;
                }
            } else {
                if (!SaveOn::userExistsDist($parsedResult['distId'])) {
                    Log::warning('Skipping. User not part of SOR.', ['parsedResult' => $parsedResult]);
                    continue;
                }
            }

            $transactionId = 'SOR#' . $parsedResult['reservation'];

            if (Order::orderWithTransactionIdExists($transactionId)) {
                Log::warning('TSB already done? Order with transaction ID already exists.',
                    ['parsedResult' => $parsedResult, 'transactionId' => $transactionId]);

                continue;
            }

            $this->processCommission($parsedResult, $transactionId, $endDate);
        }
    }

    private function parseResult($result, $sheetMappings, $defaults)
    {
        $parsedResult = $defaults;

        foreach ($sheetMappings as $header=>$mapping) {
            $parsedResult[$header] = trim($result[$mapping]);

            if (stripos($parsedResult[$header], '$') !== false) {
                $parsedResult[$header] = str_replace('$', '', trim($parsedResult[$header]));
            }
        }

        // Just in case
        $parsedResult['cv'] = ceil(trim($parsedResult['cv']));
        $parsedResult['qv'] = ceil(trim($parsedResult['qv']));
        $parsedResult['bv'] = ceil(trim($parsedResult['bv']));

        return $parsedResult;
    }

    private function processCommission($parsedResult, $transactionId, Carbon $endDate)
    {
        Log::debug('Processing commission...', ['parsedResult' => $parsedResult, 'transactionId' => $transactionId]);

        $user = User::getByDistId($parsedResult['distId']);

        $orderId = \App\Order::addNew(
            $user->id,
            (float)$parsedResult['tsbAmount'],
            (float)$parsedResult['tsbAmount'],
            $parsedResult['bv'],
            $parsedResult['qv'],
            $parsedResult['cv'],
            $transactionId,
            null,
            null,
            null,
            $createdDate = date('Y-m-d H:i:s', strtotime($endDate)),
            $discountCode = '',
            $orderStatus = null,
            $order_refund_ref = null,
            $orderQC = 0,
            $orderAC = 0,
            $isTSBOrder = 1
        );

        DB::table('orderItem')->insert([
            'orderid' => $orderId,
            'productid' => \App\Product::ID_TRAVEL_SAVING_BONUS,
            'quantity' => 1,
            'itemprice' => (float)$parsedResult['tsbAmount'],
            'bv' => $parsedResult['bv'],
            'cv' => $parsedResult['cv'],
            'qv' => $parsedResult['qv'],
            'created_date' => date('Y-m-d', strtotime($endDate)),
            'created_time' => date('h:i:s', strtotime($endDate)),
            'created_dt' => date('Y-m-d h:i:s', strtotime($endDate)),
        ]);

        DB::table('tsb_commission')->insert([
            'order_id' => $orderId,
            'user_id' => $user->id,
            'dist_id' => $user->id,
            'amount' => (float)$parsedResult['tsbAmount'],
            'paid_date' => date('Y-m-d h:i:s', strtotime($parsedResult['paidAt'])),
            'created_at' => date('Y-m-d h:i:s', strtotime($endDate)),
            'status' => TsbCommissionService::CALCULATED_STATUS,
            'memo' => $parsedResult['memo']
        ]);

        Log::debug('Finished Processing commission...', ['parsedResult' => $parsedResult, 'transactionId' => $transactionId]);
    }

    public function payoutCommission($startDate, $endDate)
    {
        $query = DB::table('tsb_commission')
            ->whereDate('paid_date', '>=', $startDate)
            ->whereDate('paid_date', '<=', $endDate)
            ->where('status', TsbCommissionService::POSTED_STATUS);

        if ($query->count() === 0) {
            return ['message' => 'There is no posted commission to payout on (TSB)'];
        }

        $commissions = $query->get();

        $success = 0;
        $failures = 0;

        foreach ($commissions as $commission) {

            $userExists = User::where('id', $commission->user_id)->exists();

            if (!$userExists) {
                $failures++;
                continue;
            }

            $transactionId = EwalletTransaction::addPurchase($commission->user_id, EwalletTransaction::TYPE_TSB_COMMISSION, $commission->amount, 0, $commission->memo);

            if ($transactionId) {
                DB::table('tsb_commission')->where('id', $commission->id)->update(['status' => 'paid']);
                $success++;
            } else {
                DB::table('tsb_commission')->where('id', $commission->id)->update(['status' => 'fail']);
                $failures++;
            }
        }

        return array($success, $failures);
    }
}
