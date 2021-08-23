<?php

namespace App\Http\Controllers\Affiliates;

use App\Http\Controllers\Affiliates\WorldSeriesErrorsController;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Models\WorldSeriesErrors;
use App\Models\WorldSeriesEvents;
use App\Models\WorldSeriesOverviews;
use App\Services\WorldSeriesBonusRunsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Log;
use Validator;

class WorldSeriesEventsController extends Controller
{
    public function create(Request $request) 
    {
        $userId = $request->get('user_id');
        $orderId = $request->get('order_id');
        $event_type = $request->get('event_type');

        return $this->createNewUser($userId, $orderId, $event_type);
    }

    private function createNewUser($userId, $orderId, $event_type){
        # Start Transaction
        DB::beginTransaction();

        # Get User's data
        $user = User::find($userId);
        $sponsor = User::whereDistid($user->sponsorid)->first();

        # Get the Sponsor of the user's sponsor.
        $masterSponsor = User::whereDistid($sponsor->sponsorid)->first();

        # Get order data
        $order = Order::find($orderId);

        # Get Order Details
        $details = \DB::table('orderItem')
            ->where('orderid', '=' , $orderId)
            ->get();

        # Set season last day and month
        $seasonPeriod = \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $order->created_dt)->endOfMonth()->toDateTimeString();
        $seasonMonth = \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $order->created_dt)->endOfMonth()->month;

        # When is upgrade package and player is on any base.
        if ($event_type == "upgrade") {
            $position = $this->isPlayingOnBasePosition($userId, $sponsor->id) + 1;
        } else {
            $position = 0;

            # Get products from OrderItem to sum points
            foreach ($details as $detail) {
                # Ignore the standby class and upgrades here
                $position += $detail->productid > 1 ? $this->getPosition($detail->productid) : 0;            
            }
        }

        # Add position to request 
        $update_data = [
            'user_id'     => $userId,
            'order_id'    => $orderId,
            'event_type'  => $event_type,
            'position'    => $position, 
            'active'      => true,
            'sponsor_id'  => $sponsor->id,
            'description' => $this->getDescription($event_type),
            'created_at'  => $order->created_dt 
        ];

        try {

            # Set the current position false when pack is an upgrade
            if ($event_type == "upgrade") {
                $update = WorldSeriesEvents::where('active', true)
                    ->where('user_id', $userId)
                    ->where('sponsor_id', $sponsor->id)
                    ->where('position', $this->isPlayingOnBasePosition($userId, $sponsor->id))
                    ->update(['active' => false]);
            }

            # Create a first step when event is enrollment, or add a new step if is upgrade.
            $event = WorldSeriesEvents::create($update_data);

            # Save the first step for the new user in the master sponsor
            if (!is_null($masterSponsor->id) && $event_type != "upgrade") {
                $update_data['sponsor_id'] = $masterSponsor->id;
                $masterEvent = WorldSeriesEvents::create($update_data);
            }

            # Create Or Update the overview by Sponsor when type is enrollment
            if ($event_type != "upgrade") {
                $overview = $this->saveOverview($sponsor->id, $masterSponsor->id, $seasonPeriod, $seasonMonth);
            }

            $totalRuns = 0;

            # Need when is upgrade, just move the user's team and your sponsor
            $masterSponsor->id = ($event_type == "upgrade") ? $userId : $masterSponsor->id;

            # Move just who's on the first, second or third base.
            $this->moveRunners($position, $userId, $sponsor->id, $masterSponsor->id, $event_type, $seasonMonth);
            
            # Increment runs with new signup + moveRunners
            $runs = ($position == 4) ? 1 : 0;

            # Increment hits if package isn't StandbyClass or Upgrades
            $hits = ($position > 0 && $event_type == "enrollment") ? 1 : 0;

            # Update Runners and Totals
            $overview = $this->updateRunnersOverview($sponsor->id, $masterSponsor->id, $runs, $hits, $seasonPeriod, $seasonMonth);

            DB::commit();

        } catch (\Exception $e) {
            dd($e->getMessage());
            DB::rollback();
        }

        # Update and return overview
        return $overview;
    }

    public function isPlayingOnBasePosition($userId, $sponsorId) 
    {
        $currentPosition = WorldSeriesEvents::whereUserId($userId)
            ->whereSponsorId($sponsorId)
            ->whereIn('position', [1,2,3])
            ->whereActive(1)
            ->select('position')
            ->first();
    
        return ($currentPosition) ? $currentPosition->position : 0;
    }

    public function updateRunnersOverview($sponsorId, $masterSponsorId, $runs, $hits, $seasonPeriod, $seasonMonth) 
    {

        if (!is_null($masterSponsorId)) {
            $master_first_base_user_id  = WorldSeriesEvents::select('user_id')->whereMonth('created_at', $seasonMonth)
                ->whereActive(1)->wherePosition(1)->whereSponsorId($masterSponsorId)->latest('created_at')->first();

            $master_second_base_user_id = WorldSeriesEvents::select('user_id')->whereMonth('created_at', $seasonMonth)
                ->whereActive(1)->wherePosition(2)->whereSponsorId($masterSponsorId)->latest('created_at')->first();

            $master_third_base_user_id  = WorldSeriesEvents::select('user_id')->whereMonth('created_at', $seasonMonth)
                ->whereActive(1)->wherePosition(3)->whereSponsorId($masterSponsorId)->latest('created_at')->first();
            
            # Get all runners from position 4 to sum on runs total
            $masterTotalRuns = WorldSeriesEvents::whereSponsorId($masterSponsorId)->whereMonth('created_at', $seasonMonth)->wherePosition(4)->count();
            $masterOverview = WorldSeriesOverviews::whereSponsorId($masterSponsorId)->whereMonth('season_period', $seasonMonth)->first();

            # Update the overview just when exists
            if (!is_null($masterOverview)) {

                $masterResult = WorldSeriesOverviews::updateOrCreate(
                    ['sponsor_id' => $masterSponsorId, 'season_period' => $seasonPeriod],
                    [
                        'first_base_user_id' => !is_null($master_first_base_user_id)  ? $master_first_base_user_id->user_id : null,
                        'second_base_user_id'=> !is_null($master_second_base_user_id) ? $master_second_base_user_id->user_id : null,
                        'third_base_user_id' => !is_null($master_third_base_user_id)  ? $master_third_base_user_id->user_id : null,
                        'runs'  => $masterTotalRuns,
                        'hits'  => $masterOverview->hits + $hits,
                        'total' => $masterTotalRuns
                    ]);
            }
        }

        # Update data from Overview by direct sponsor of the player. 
        $first_base_user_id  = WorldSeriesEvents::select('user_id')->whereMonth('created_at', $seasonMonth)
            ->whereActive(1)->wherePosition(1)->whereSponsorId($sponsorId)->latest('created_at')->first();
        $second_base_user_id = WorldSeriesEvents::select('user_id')->whereMonth('created_at', $seasonMonth)
            ->whereActive(1)->wherePosition(2)->whereSponsorId($sponsorId)->latest('created_at')->first();
        $third_base_user_id  = WorldSeriesEvents::select('user_id')->whereMonth('created_at', $seasonMonth)
            ->whereActive(1)->wherePosition(3)->whereSponsorId($sponsorId)->latest('created_at')->first();
        
        # Get all runners from position 4 to sum on runs total    
        $sponsorTotalRuns = WorldSeriesEvents::whereSponsorId($sponsorId)->whereMonth('created_at', $seasonMonth)->wherePosition(4)->count();
        $sponsorTotalHits = WorldSeriesOverviews::whereSponsorId($sponsorId)->whereMonth('season_period', $seasonMonth)->first();

        # Update Overview
        $overview = WorldSeriesOverviews::updateOrCreate(
            ['sponsor_id' => $sponsorId, 'season_period' => $seasonPeriod],
            [
                'first_base_user_id' => !is_null($first_base_user_id)  ? $first_base_user_id->user_id : null,
                'second_base_user_id'=> !is_null($second_base_user_id) ? $second_base_user_id->user_id : null,
                'third_base_user_id' => !is_null($third_base_user_id)  ? $third_base_user_id->user_id : null,
                'runs'  => $sponsorTotalRuns,
                'hits'  => !is_null($sponsorTotalHits) ? $sponsorTotalHits->hits + $hits : $hits,
                'total' => $sponsorTotalRuns,
                'season_name' => $this->getSeasonName($seasonMonth)
            ]
        );

        return $overview;
    }

    public function moveRunners($position, $userId, $sponsorId, $masterSponsorId, $event_type, $seasonMonth)
    {
        $runners = WorldSeriesEvents::whereIn('sponsor_id', [$sponsorId, $masterSponsorId])
            ->whereIn('position', [1,2,3])
            ->where('active', '=', 1)
            ->where('user_id', '<>', $userId)
            ->whereMonth('created_at', $seasonMonth)
            ->get(); 

        # To move runners, position 0 (standby) needs be 1
        $position = ($position == 0 || $event_type == 'upgrade') ? 1 : $position;

        if ($runners->count() > 0) {
            foreach ($runners as $runner) {
                
                # Get a clone for the last step
                $newEvent = $runner->replicate();

                # Set the last step as historical
                $runner->active = false;
                $runner->save();

                # Get the new position
                $newPosition = ($newEvent->position + $position) >= 4 ? 4 : ($newEvent->position + $position);  

                $newEvent->active = true;
                $newEvent->created_at = $runner->created_at;
                $newEvent->position = $newPosition;
                $newEvent->moved_by_user_id = $userId;
                $newEvent->description = $this->getDescription('moved_by_new_signup');
                
                $newEvent->save();
            }
        }

        return true;
    }

    public function saveOverview($sponsorId, $masterSponsorId = null, $seasonPeriod, $seasonMonth) 
    {
        # Refactoring... older code.
        #$seasonPeriod = \Carbon\Carbon::now()->endOfMonth()->toDateString();
        #$checkOverview = WorldSeriesOverviews::whereSponsorId($sponsorId)->first();

        $seasonName = $this->getSeasonName($seasonMonth);
       
        $data = [
            'sponsor_id'    => $sponsorId,  
            'season_name'   => $seasonName,
            'season_period' => $seasonPeriod
        ];

        if (!is_null($masterSponsorId)) {
            # $checkSponsorOverview = WorldSeriesOverviews::whereSponsorId($masterSponsorId)->first();
           
            $dataMasterSponsor = [
                'sponsor_id'    => $masterSponsorId,  
                'season_name'   => $seasonName,
                'season_period' => $seasonPeriod
            ];

            # Update or create the Overview to Master Sponsor
            $masterSponsor = WorldSeriesOverviews::updateOrCreate(
                ['sponsor_id' => $masterSponsorId, 'season_period' => $seasonPeriod],
                $dataMasterSponsor
            );
        }

        # Update or create the user Overview
        return WorldSeriesOverviews::updateOrCreate(
            ['sponsor_id' => $sponsorId, 'season_period' => $seasonPeriod],
            $data
        );
    }

    public function getSeasonName($month) 
    {
        switch ($month) {
            case 6:
                return 'The Season';
                break;

            case 7:
                return 'Playoffs';
                break;
            
            case 8:
                return 'The World Series';
                break;

            default:
                return 'No Season found';
                break;
        }
    }

    public function getDescription($eventType)
    {

        switch (strtolower($eventType)) {
            case 'enrollment':
                $message = 'New Signup';
                break;
            
            case 'moved_by_new_signup':
                $message = 'Moved By New Signup';
                break;

            case 'moved_by_new_upgrade':
                $message = 'Moved By Some Upgrade';
                break;

            case 'upgrade':
                $message = 'Upgraded';
                break;

            default:
                $message = '';
                break;
        }

        return $message;
    }

    public function getPosition($productId) 
    {
        switch ($productId) {
            # Standby Class Or Upgrade Packages
            case 1:
            case 5:
            case 6:
            case 7:
            case 8:
            case 9:
            case 10:
                return 0;
                break;
            # Coach Class
            case 2:
                return 1;
                break;
            # Bussiness Class
            case 3:
                return 2;
                break;
            # First Class
            case 4:
                return 3;
                break;
            # Ticket
            case 38:
                return 1;
                break;

            default:
                # code...
                break;
        }

    }

    # Retrieving the top teams by the Owner
    public function calculate(Request $request) 
    {

        set_time_limit(0);

        Log::info("script started");
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date|date_format:Y-m-d',
            'end_date' => 'required|date|date_format:Y-m-d',
            'truncate' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            $messages = $validator->messages();
            foreach ($messages->all() as $m) {
                $msg =  $m ;
            }
            $this->setMessage($msg);
            $this->setResponseCode(400);
            return $this->showResponse();
            exit();
        }
        $data = $request->all();

        if ((bool) $data['truncate']){
            WorldSeriesOverviews::truncate();
            WorldSeriesEvents::truncate();
            WorldSeriesErrors::truncate();
        }
        
        $orders = Order::getWorldSeriesOrdersByUser($data['start_date'], $data['end_date']);

        foreach($orders as $order){
            $type = 'enrollment';
            $valid = false;
            $orderItems = OrderItem::getOrderItem($order->order_id);

            foreach($orderItems as $orderItem){
                if($orderItem->prod_id > 4 && $orderItem->prod_id <11){
                    $type = 'upgrade';
                    $valid = true;
                } else if ($orderItem->prod_id > 0 && $orderItem->prod_id <5){
                    $type = 'enrollment';
                    $valid = true;
                }
            }
            
            // Log::info("User ID ".$order->user_id. " -- Order ID: ".$order->order_id." -- Dist ID ".$order->distid." -- Order Type ".$type);
    
            try {
                if($valid){
                    $this->createNewUser($order->user_id, $order->order_id, $type);
                }
            } catch (Exception $e) {
                // Log::info("Failed to create user");
            }
        }

        # Call the calc for errors/refunds/totals
        $errors = new WorldSeriesErrorsController;
        $total = $errors->create($request);

        Log::info("script finished");
        $this->setMessage('Calculation is complete');
        $this->setResponseCode(200);

        return $this->showResponse();
    }

/*  Just run from the queue
    public function calculateBonusRuns($month) 
    {
  
        $fromDate = \Carbon\Carbon::now()->startOfMonth()->toDateString();
        $toDate = \Carbon\Carbon::now()->toDateString();

        $results = WorldSeriesBonusRunsService::calculate($fromDate, $toDate);

        foreach ($results as $result) {
 
            $bonus_runs = 0;

            for ($i = 1; $i <= $result->bonus_run; $i++) {
                $expectRuns = $i * 4;

                if ($result->runs >= $expectRuns) {
                    $bonus_runs++; 
                } else {
                    break;
                }
            }

            # Update bonus runs to sponsor and your sponsor
            $sponsor = WorldSeriesOverviews::whereSponsorId($result->sponsor_id)->whereMonth('season_period', $month)->first();

            if ($sponsor) {
                $sponsor->bonus_runs = $sponsor->bonus_runs + $bonus_runs;
                $sponsor->total = $sponsor->total + $bonus_runs;
                $sponsor->save();
            }

            $master_sponsor = WorldSeriesOverviews::whereSponsorId($result->master_sponsor_id)->whereMonth('season_period', $month)->first();
            
            if ($master_sponsor) {
                $master_sponsor->bonus_runs = $master_sponsor->bonus_runs + $bonus_runs;
                $master_sponsor->total = $master_sponsor->total + $bonus_runs;
                $master_sponsor->save();
            }
        }
    }

*/

}
