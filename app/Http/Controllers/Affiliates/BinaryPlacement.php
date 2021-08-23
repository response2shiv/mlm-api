<?php

namespace App\Http\Controllers\Affiliates;

use App\Facades\BinaryPlanManager;
use App\Helpers\MyMail;
use App\Helpers\Util;
use App\Http\Controllers\Controller;
use App\Models\BinaryCommissionCarryoverHistory;
use App\Models\BinaryPlanNode;
use App\Models\User;
use App\Models\UserBucketVolume;
use App\Services\Twilio;
use Auth;
use Carbon\Carbon;
use DB;
use Illuminate\Http\Request;
use Log;
use Validator;

class BinaryPlacement extends Controller
{
    /**
     * Get the initial binary placement report
     *
     */
    public function index()
    {
        $user = Auth::user();

        $placement = DB::select("select binary_placement from users where id = '$user->id'");
        $resp['binary_placement'] = $placement[0]->binary_placement;


        $resp['total_enrolled'] = UserBucketVolume::countDistribuitors();

        $this->setResponse($resp);
        $this->setResponseCode(200);

        return $this->showResponse();
    }

    public function updatePlacement(request $request)
    {
        $request = request();
        $validator = Validator::make($request->all(), [
            'binary_placement' => 'required'
        ]);

        $msg = "";
        if ($validator->fails()) {
            $valid = 0;
            $messages = $validator->messages();
            foreach ($messages->all() as $m) {
                $msg = $m;
            }
            $this->setMessage($msg);
            $this->setResponseCode(400);
        } else {
            $user = Auth::user();
            User::updatePlacements($user->id, $request);

            $this->setMessage("Placement has been updated.");
            $this->setResponseCode(200);
        }
        return $this->showResponse();
    }

    public function getDirectLine()
    {
        $user   = Auth::user();
        $start  = Carbon::now()->subDays(30)->format('Y-m-d');
        $end    = Carbon::now()->format('Y-m-d');
        $placement = DB::select("SELECT
            u.distid,
            u.firstname,
            u.lastname,
        CASE
            WHEN bp.direction='R' THEN 'right'
            ELSE 'left'
        END
        AS binary_placement,
        CASE
            WHEN u.account_status='APPROVED' THEN 'Active'
            ELSE 'Terminated'
        END
        AS status,
        (u.id IN (
        SELECT userid FROM orders
        WHERE created_dt >= '" . $start . "' AND created_dt <= '" . $end . "' GROUP BY userid HAVING sum(orderqv) >= 100)
        or u.distid in (select user_distid from force_rank where force_active=1)
        ) as status_pqv
        FROM binary_plan bp
        JOIN users as u on u.id=bp.user_id
        where
        u.sponsorid = '$user->distid'");

        $resp['tree'] = $placement;

        $this->setResponse($resp);
        $this->setResponseCode(200);

        return $this->showResponse();
    }

    private function getLeftBinaryTotal($targetNode)
    {
        $mondayDate = date('Y-m-d', strtotime('monday this week'));
        $leftLeg = BinaryPlanManager::getLeftLeg($targetNode);
        $currentLeftAmount = 0;
        if ($leftLeg) {
            $currentLeftAmount = BinaryPlanManager::getNodeTotal($leftLeg, $mondayDate);
        }

        return $currentLeftAmount;
    }

    private function getRightBinaryTotal($targetNode)
    {
        $mondayDate = date('Y-m-d', strtotime('monday this week'));
        $rightLeg = BinaryPlanManager::getRightLeg($targetNode);
        $currentRightAmount = 0;
        if ($rightLeg) {
            $currentRightAmount = BinaryPlanManager::getNodeTotal($rightLeg, $mondayDate);
        }

        return $currentRightAmount;
    }

    public function sendMail(Request $request)
    {

        $req = request();
        $email = trim($request->email);

        if (Util::isNullOrEmpty($email)) {
            return response()->json(['error' => 1, 'msg' => 'Email is required']);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return response()->json(['error' => 1, 'msg' => 'Invalid email format']);
        }

        $sent = MyMail::sendBinaryPlacementMail($email, $request->url);

        if ($sent) {
            return response()->json(['error' => 0, 'msg' => 'Email successfully sent']);
        } else {
            return response()->json(['error' => 1, 'msg' => 'Email service is not configured. Please contact us']);
        }
    }

    public function sendSMS(Request $request)
    {
        $mobile     = str_replace("+", "", $request->get('m'));

        $message    = "Here is your link to join my team! " . $request->get('url');
        $response   = Twilio::sendSMS($mobile, $message);
        if ($response['status'] == 'success') {
            return response()->json(['error' => 0, 'msg' => 'SMS successfully sent']);
        } else {
            //return response()->json(['error' => 1, 'msg' => $response['msg']]);
            return response()->json(['error' => 1, 'msg' => "Invalid mobile number. Please enter with country code"]);
        }
    }
}
