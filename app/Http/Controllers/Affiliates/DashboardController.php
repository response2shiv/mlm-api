<?php

namespace App\Http\Controllers\Affiliates;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Product;
use App\Models\PromoInfo;
use App\Models\Address;
use App\Models\BoomerangInv;
use App\Models\IDecide;
use App\Models\UserRankHistory;
use App\Models\RankInterface;
use App\Models\RankDefinition;
use App\Models\ProductTermsAgreement;
use App\Models\SaveOn;
use App\Helpers\Util;
use App\Models\BinaryPlanNode;
use App\Models\Commission;
use App\Models\Order;
use App\Models\MailTemplate;
use App\Models\EwalletTransaction;
use App\Facades\BinaryPlanManager;
use App\Models\UserBucketVolume;
use Yajra\DataTables\Facades\DataTables;
use App\Services\SubscriptionGroupService;
use Illuminate\Support\Facades\Cache;
use Log;
use Mail;
use Auth;
// use Mailgun;
use Config;
use DB;

/**
 * Class DashboardController
 * @package App\Http\Controllers
 */

/**
 * @group Affiliates Dashboard
 *
 * All affiliates dashboard controllers.
 */
class DashboardController extends Controller
{
    // /** @var BinaryPlanService */
    // private $binaryPlanService;

    // /** @var SubscriptionGroupService */
    // private $subscriptionGroupService;

    // /**
    //  * DashboardController constructor.
    //  * @param BinaryPlanService $binaryPlanService
    //  * @param SubscriptionGroupService $subscriptionGroupService
    //  */
    public function __construct(
        // BinaryPlanService $binaryPlanService,
        // SubscriptionGroupService $subscriptionGroupService
    )
    {
        // $this->binaryPlanService = $binaryPlanService;
        // $this->subscriptionGroupService = $subscriptionGroupService;
        // $this->middleware('auth.admin', ['only' => [
        //     'getTotalOrderSumChart'
        // ]]);
        // $this->middleware('auth.affiliate', ['only' => [
        //     'gotoStoreFront',
        //     'iGo',
        //     'idecide',
        //     'getCurrentMonthTotalCV',
        //     'savePreferences',
        //     'resetPreferences'
        // ]]);
        // $this->middleware('auth');
    }

    /**
     * Dashboard Details
     *
     * [This brings all the dashboard data details to build the first affiliate dashboard]
     *
     */
    public function index()
    {
        $user = Auth::user();
        $d = array();
        $d['promo'] = PromoInfo::getPromoSummary();


        $d['bucket_volumes'] = UserBucketVolume::getUserBucketVolumes($user->id);
        $d['bucket_current_week'] = UserBucketVolume::getUserBucketCurrentWeek($user->id);

        $ncrease_nsbo_count = 0;
        $visionary_pack = 0;
        $basic_pack = 0;
        //
        $d['enrollments'] = User::getTotalEnrollments(Auth::user()->distid);
        $packs = User::getGroupCountByUser(Auth::user()->distid);
        foreach ($packs as $pack) {
            if ($pack->current_product_id == Product::ID_NCREASE_NSBO) {
                $ncrease_nsbo_count = $pack->total;
            }
            if ($pack->current_product_id == Product::ID_VISIONARY_PACK) {
                $visionary_pack = $pack->total;
            }
            if ($pack->current_product_id == Product::ID_BASIC_PACK) {
                $basic_pack = $pack->total;
            }
        }

        $d['ncrease_nsbo_count'] = $ncrease_nsbo_count;
        $d['visionary_pack'] = $visionary_pack;
        $d['basic_pack'] = $basic_pack;
        //
        $currentProductId = User::getCurrentProductId(Auth::user()->id);
        // if ($currentProductId == Product::ID_FIRST_CLASS ||
        //     $currentProductId == Product::ID_EB_FIRST_CLASS) {
        //     $currentPackageName = "Founders";
        // } else {
        //     $currentPackageName = Product::getProductName($currentProductId);
        // }
        $currentPackageName = Product::getProductName($currentProductId);

        $d['currentPackageName'] = $currentPackageName;
        // show package options
        $showUpgradeBtn = false;
        $idProductsUpgrade = [];

        // check for premium first class
        $accessToPrimaryFC = false;
        $primaryAddress = Address::getRec(Auth::user()->id, Address::TYPE_BILLING);
        if (!empty($primaryAddress)) {
            $countryCode = $primaryAddress->countrycode;
            if ($countryCode == "FO") {
                $accessToPrimaryFC = true;
            }
        }

        if ($currentProductId == Product::ID_NCREASE_NSBO) {
            $showUpgradeBtn = true;
            $idProductsUpgrade = [
                Product::UPG_ISBO_TO_BASIC,
                Product::UPG_ISBO_TO_FX,
                Product::UPG_ISBO_TO_VISIONARY
            ];
        } else if ($currentProductId == Product::ID_FX_PACK) {
            $showUpgradeBtn = true;
            $idProductsUpgrade = [
                Product::UPG_FX_TO_VISIONARY
            ];
        } else if ($currentProductId == Product::ID_BASIC_PACK) {
            $showUpgradeBtn = true;
            $idProductsUpgrade = [
                Product::UPG_BASIC_TO_VISIONARY
            ];
        }

        $d['showUpgradeBtn'] = $showUpgradeBtn;
        $d['productsUpgrade'] = Product::select('productname as name', 'id as value', 'price as price')->whereIn('id', $idProductsUpgrade)
            ->get()
            ->toArray();

        $d['currentProductId'] = $currentProductId;
        $isTvUser = User::isTvUser(Auth::user()->id);
        // rank
        $current_rank_info = UserRankHistory::getCurrentMonthUserInfo(Auth::user()->id);
        if ($current_rank_info == null) {
            $rank = 10;
            $achieved_rank_desc = strtoupper("Ambassador");
            $monthly_rank_desc = strtoupper("Ambassador");
            $monthly_qv = 0;
            $monthly_tsa = 0;
            $monthly_qc = 0;
        } else {
            $rank = $current_rank_info->monthly_rank;
            $achieved_rank_desc = strtoupper($current_rank_info->achieved_rank_desc);
            $monthly_rank_desc = strtoupper($current_rank_info->monthly_rank_desc);
            $monthly_qv = number_format($current_rank_info->monthly_qv);
            $monthly_tsa = number_format($current_rank_info->monthly_tsa);
            $monthly_qc = number_format($current_rank_info->monthly_qc);
        }

        $paidRank = Auth::user()->getCommissionRank(Carbon::now());
        $paidRank = RankDefinition::where('id', $paidRank)->first();

        $d['achieved_rank_desc'] = $achieved_rank_desc;
        $d['monthly_rank_desc'] = $monthly_rank_desc;
        $d['paidRank'] = strtoupper($paidRank->rankdesc);
        $d['monthly_qv'] = Auth::user()->current_month_qv;
        $d['monthly_tsa'] = $monthly_tsa;
        $d['monthly_qc'] = $monthly_qc;
        $d['upper_ranks'] = RankDefinition::getUpperRankInfo($rank);
        $d['rank_matric'] = UserRankHistory::getRankMatrics(Auth::user()->distid, $rank);
        $d['qcContributors'] = Auth::user()->getTopQCLegs();
        $d['contributors'] = UserRankHistory::getTopContributors(Auth::user()->distid, $rank);
        $d['qv'] = $current_rank_info ? $current_rank_info->qualified_qv : 0;
        $d['tsaRank'] = $rank >= RankInterface::RANK_VALUE_EXECUTIVE;
        $d['activeQC'] = Auth::user()->getActiveQC();
        $d['qualifyingQC'] = Auth::user()->getQualifyingQC();
        $d['limit'] = Auth::user()->getRankLimit();
        $d['font'] = ['brand', 'success', 'info', 'warning', 'danger'];
        $d['binaryQualified'] = Auth::user()->getBinaryQualifiedValues();

        // $d['commission_this_week']  = EwalletTransaction::getThisWeekCommission(Auth::user()->id);
        $d['commission_this_week']  = EwalletTransaction::getThisWeekCommission(Auth::user()->id);
        $d['commission_this_month'] = EwalletTransaction::getThisMonthCommission(Auth::user()->id);
        $d['commission_this_year']  = EwalletTransaction::getThisYearCommission(Auth::user()->id);
        $d['ewallet_balance']       = EwalletTransaction::getUserBalance(Auth::user()->id);


        //select sum(amount) from unilevel_commission where user_id=15163 and calculation_date between '2020-03-01' and '2020-03-31';
        $prevRank = UserRankHistory::getRankInMonth(
            Auth::user(),
            Util::getUserCurrentDate()->modify('last day of previous month')->endOfMonth()->startOfDay()
        );

        if ($prevRank) {
            $d['prevRank'] = strtoupper($prevRank->monthly_rank_desc);
            $d['prevQv'] = $prevRank->qualified_qv;
        } else {
            $d['prevRank'] = strtoupper("Ambassador");
            $d['prevQv'] = 0;
        }

        // business snapshot
        $businessSS = UserRankHistory::getCurrentMonthlyRec(Auth::user()->id);
        if (empty($businessSS)) {
            $biz_acheived_rank = "-";
            $biz_monthly_qv = 0;
            $biz_qulified_vol = 0;
            $biz_monthly_cv = 0;
        } else {
            $biz_acheived_rank = $businessSS->rankdesc;
            $biz_monthly_qv = $businessSS->monthly_qv;
            $biz_qulified_vol = $businessSS->qualified_qv;
            $biz_monthly_cv = $businessSS->monthly_cv;
        }
        //
        $d['biz_acheived_rank'] = $biz_acheived_rank;
        $d['biz_monthly_qv'] = $biz_monthly_qv;
        $d['biz_monthly_cv'] = $biz_monthly_cv;
        $d['biz_qulified_vol'] = $biz_qulified_vol;
        $d['is_active'] = Auth::user()->getCurrentActiveStatus();
        $pv = Order::getThisMonthOrderQV(Auth::user()->id);
        $d['pv'] = $pv > 100 ? 100 : $pv;
        // get current month total cv of all personal enrollments
        $d['total_current_month_cv'] = $this->getCurrentMonthTotalCV(Auth::user()->distid);
        //
        $targetNode = BinaryPlanNode::where('user_id', Auth::user()->id)->first();
        if (empty($targetNode)) {
            $d['total_left'] = 0;
            $d['total_right'] = 0;
        } else {
            $d['total_left'] = $this->getLeftBinaryTotal($targetNode);
            $d['total_right'] = $this->getRightBinaryTotal($targetNode);
        }
        //
        $d['current_month_commission'] = Commission::getCurrentMonthCommission(Auth::user()->id);
        // get previous month recs
        $prev_rec = UserRankHistory::getPreviousMonthlyRec(Auth::user()->id);
        if (empty($prev_rec)) {
            $prev_biz_acheived_rank = "-";
        } else {
            $prev_biz_acheived_rank = strtoupper($prev_rec->rankdesc);
        }
        $d['prev_biz_acheived_rank'] = $prev_biz_acheived_rank;

        //$subscriptionGroupService = new SubscriptionGroupService();
        //$d['subscriptionTypes'] = $subscriptionGroupService->getSubscriptionTypes(Auth::user());
        $d['monthly_performance_ambassadors'] = UserRankHistory::getMonthlyPerformanceAmbassadors();
        $d['monthly_performance_customers'] = UserRankHistory::getMontlhyPerformanceCustomers();
        $user = Auth::user();
        $preferences = $user->replicatedPreferences;

        $d['preferences'] = [
            'buiness_name' => $preferences && $preferences->displayed_name ? $preferences->displayed_name : '',
            'displayed_name' => $preferences && $preferences->business_name ? $preferences->business_name : $user->firstname . ' ' . $user->lastname,
            'name' => $user->firstname . ' ' . $user->lastname,
            'co_name' => $user->co_applicant_name,
            'co_display_name' => $preferences && $preferences->co_name ? $preferences->co_name : $user->co_applicant_name,
            'phone' => $preferences && $preferences->phone ? $preferences->phone : $user->phonenumber,
            'email' => $preferences && $preferences->email ? $preferences->email : $user->email,
            'show_email' => $preferences ? $preferences->show_email : 0,
            'show_phone' => $preferences ? $preferences->show_phone : 0,
            'show_name' => $preferences ? $preferences->show_name : 1,
            'disable_co_app' => !$user->co_applicant_name,
        ];
        $d['distributor_counts'] = $this->volumeCalcutate();
        $d['user'] = $user;
        $d['showVibeAgreementModal'] = User::isVibeImportUser() && Auth::user()->has_agreed_vibe == false;
        $this->setResponse($d);
        $this->getResponseCode(200);
        return $this->showResponse();
    }

    public function iGo()
    {
        $agreed = ProductTermsAgreement::getByUserId(Auth::user()->id, 'sor');
        if (empty($agreed)) {
            return response()->json(['error' => '2', 'msg' => 'User has not agreed with iGo.']);
        }

        $currentProductId = User::getCurrentProductId(Auth::user()->id);
        if ($currentProductId == 0)
            return response()->json(['error' => '1', 'msg' => 'Get your enrollment pack now!']);

        if ($currentProductId == 13)
            $currentProductId = 4;
        if ($currentProductId == 14)
            $currentProductId = 3;


        $response = SaveOn::SSOLogin($currentProductId, Auth::user()->distid);
        return response()->json($response);
    }

    public function idecide()
    {
        $agreed = ProductTermsAgreement::getByUserId(Auth::user()->id, 'idecide');
        if (empty($agreed)) {
            return response()->json(['error' => '2', 'msg' => 'User has not agreed with iDecide.']);
        }
        $idecideUserRec = DB::table('idecide_users')
            ->where('user_id', Auth::user()->id)
            ->first();
        if (empty($idecideUserRec)) {
            return response()->json(['error' => '1', 'msg' => 'iDecide service not available for your account. Please contact us to activate your iDecide Services']);
        }
        $response = IDecide::SSOLogin($idecideUserRec);
        return response()->json($response);
    }

    // public function getTotalOrderSumChart() {
    //     $req = request();
    //     $query = DB::table('v_total_order_sum_for_month');
    //     if (isset($req->year)) {
    //         $query->whereYear("created_dt", $req->year);
    //         $query->whereMonth("created_dt", $req->month);
    //     } else {
    //         $query->whereYear("created_dt", date("Y"));
    //         $query->whereMonth("created_dt", date("m"));
    //     }
    //     $query->orderBy('created_dt', "asc");
    //     $recs = $query->get();
    //     return response()->json(['error' => '0', 'data' => $recs]);
    // }

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

    public function getCurrentMonthTotalCV($distId)
    {
        $rec = DB::select("SELECT sum(current_month_cv) as cv FROM enrolment_tree_tsa('$distId')");
        if (count($rec) > 0)
            return $rec[0]->cv;
        else
            return 0;
    }

    // /**
    //  * @return \Illuminate\Http\JsonResponse
    //  */
    // public function savePreferences()
    // {
    //     $request = request();

    //     /** @var User $user */
    //     $user = Auth::user();

    //     $preferences = $user->replicatedPreferences;

    //     if (!$preferences) {
    //         $preferences = new ReplicatedPreferences();
    //     }

    //     $preferences->user_id = $user->id;
    //     $preferences->displayed_name = $request->display_name;
    //     $preferences->business_name = $request->business_name;
    //     $preferences->phone = $request->phone;
    //     $preferences->email = $request->email;
    //     $preferences->show_email = $request->show_email ? 1 : 0;
    //     $preferences->show_phone = $request->show_phone ? 1 : 0;
    //     $preferences->show_name = $request->show_name ?: 1;

    //     $preferences->save();

    //     return response()->json([
    //         'error' => 0,
    //         'msg' => 'Preferences have been saved successfully.',
    //     ]);
    // }

    // /**
    //  * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
    //  */
    // public function resetPreferences()
    // {
    //     $request = request();

    //     /** @var User $user */
    //     $user = Auth::user();

    //     $preferences = $user->replicatedPreferences;

    //     $d['preferences'] = [
    //         'buiness_name' => $preferences && $preferences->displayed_name ? $preferences->displayed_name : '',
    //         'displayed_name' => $preferences && $preferences->business_name ? $preferences->business_name : $user->firstname . ' ' . $user->lastname,
    //         'name' => $user->firstname . ' ' . $user->lastname,
    //         'co_name' => $user->co_applicant_name,
    //         'co_display_name' => $preferences && $preferences->co_name ? $preferences->co_name : $user->co_applicant_name,
    //         'phone' => $preferences && $preferences->phone ? $preferences->phone : $user->phonenumber,
    //         'email' => $preferences && $preferences->email ? $preferences->email : $user->email,
    //         'show_email' => $preferences ? $preferences->show_email : 0,
    //         'show_phone' => $preferences ? $preferences->show_phone : 0,
    //         'show_name' => $preferences ? $preferences->show_name : 1,
    //         'disable_co_app' => !$user->co_applicant_name,
    //         'tab' => 'replicated',
    //     ];

    //     return response()->json([
    //         'error' => 0,
    //         'template' => view('affiliate.dashboard.replicated_preferences')->with($d)->render(),
    //     ]);
    // }

    public function getRankWidget($rank, $month, $year)
    {
        $distid = Auth::user()->distid;
        $response['rank_metrics']       = UserRankHistory::getRankMetricsMonth($distid, $rank, $month, $year);

        if (Carbon::now()->format('m') == $month && Carbon::now()->format('Y') == $year) {
            $response['top_contributors']   = UserRankHistory::getTopContributors($distid, $rank - 10);
        } else {
            $response['top_contributors']   = UserRankHistory::getTopContributorsMonth($distid, $rank, $month, $year);
        }

        $last_day = Carbon::createFromDate($year, $month, '01', 'America/Chicago')->endOfMonth()->format('Y-m-d');
        $period = $last_day . ' 00:00:00';
        $history = UserRankHistory::getRankInMonth(Auth::user(), $period);
        if ($history) {
            $current_rank = $history->monthly_rank + 10;

            if (Carbon::now()->format('m') == $month && Carbon::now()->format('Y') == $year) {
                $response['top_contributors_current']   = UserRankHistory::getTopContributors($distid, $current_rank - 10);
            } else {
                $response['top_contributors_current']   = UserRankHistory::getTopContributorsMonth($distid, $current_rank, $month, $year);
            }


            $response['upper_ranks'] = RankDefinition::getUpperRankInfo($history->monthly_rank);
        } else {
            $current_rank = 0;
            $response['top_contributors_current']   = array();
            $response['upper_ranks'] = array();
        }

        $limit = Auth::user()->getRankLimit($rank - 10);
        $qcTopUsers = Auth::user()->getTopQCLegs($limit);

        $qc_contributors = [
            'qcContributors' => $qcTopUsers,
            'limit' => number_format($limit, 2)
        ];



        $response['qc_contributors'] = $qc_contributors;

        $this->setResponse($response);
        $this->getResponseCode(200);
        return $this->showResponse();
    }

    /*
    * Load monthly projected qv information for the dashboard
    */
    public function getMonthlyProjectedQv()
    {
        $value = Cache::remember('projected-month-qv-dash-' . Auth::user()->distid, 3600, function () {

            # THE ORIGINAL METHODS
            #//Total amount of QV expected to run this month
            # $totals = User::getUserMonthQvTotals();
            # //Total amount of QV of subscription that already processed this month
            # $paid = User::getUserMonthQvPaid();

            $totals = User::getUserMonthQvTotalsSeptember();
            $paid = User::getUserMonthQvPaidSeptember();

            $response['totals'] = $totals;
            $response['paid'] = $paid;
            return $response;
        });

        $this->setResponse($value);
        $this->getResponseCode(200);
        return $this->showResponse();
    }

    /*
    * Load monthly projected qv information for the detailed list
    */
    public function getMonthlyProjectedQvDetails()
    {
        $list = Cache::remember('projected-month-qv-details-' . Auth::user()->distid, 3600, function () {
            //Total amount of QV expected to run this month
            $resp = User::getUserMonthQvList();
            return $resp;
        });

        $this->setResponse($list);
        $this->getResponseCode(200);
        return $this->showResponse();
    }

    /*
    * Load monthly projected qv information for the detailed list in datatable
    */
    public function getMonthlyProjectedQvDetailsDataTable()
    {
        $list = Cache::remember('projected-month-qv-details-' . Auth::user()->distid, 3600, function () {
            //Total amount of QV expected to run this month
            $resp = User::getUserMonthQvList();
            return $resp;
        });

        return DataTables::of($list)->toJson();
    }

    public function upateEventsToken(Request $request)
    {
        $user = Auth::user();

        $token = User::updateRemeberToken($user->id, $request->remember_token);

        $this->setResponseCode(200);

        $this->setResponse($token);

        return $this->showResponse();
    }

    /**
     * Volume Calcaulation based on logged user
     */

    public function volumeCalcutate()
    {
        $user = Auth::user();
        $currentNoteTids = DB::select("select * from bucket_tree_plan where uid = '" . $user->id . "'");

        $aISBO = 0;
        $bISBO = 0;
        $cISBO = 0;

        if (count($currentNoteTids) > 0) {
            if (isset($currentNoteTids[0]->auid)) {
                $aISBOCount = DB::select("WITH RECURSIVE bucket_tree AS ("
                    . "    SELECT tid, uid, sid, pid, pbid, sbid "
                    . "    FROM bucket_tree_plan "
                    . "    WHERE uid = '" . $currentNoteTids[0]->auid . "' "
                    . "    UNION SELECT bt.tid, bt.uid, bt.sid, bt.pid, bt.pbid, bt.sbid "
                    . "    FROM bucket_tree_plan bt INNER JOIN bucket_tree T ON T.uid = bt.pid "
                    . "    ) SELECT count(*) "
                    . "    FROM bucket_tree bt JOIN users AS u ON bt.uid = u.ID");
                if (count($aISBOCount) > 0) {
                    if (isset($aISBOCount[0]->count)) {
                        $aISBO = $aISBOCount[0]->count;
                    }
                }
            }
            if (isset($currentNoteTids[0]->buid)) {
                $bISBOCount =  DB::select("WITH RECURSIVE bucket_tree AS ("
                    . "    SELECT tid, uid, sid, pid, pbid, sbid "
                    . "    FROM bucket_tree_plan "
                    . "    WHERE uid = '" . $currentNoteTids[0]->buid . "' "
                    . "    UNION SELECT bt.tid, bt.uid, bt.sid, bt.pid, bt.pbid, bt.sbid "
                    . "    FROM bucket_tree_plan bt INNER JOIN bucket_tree T ON T.uid = bt.pid "
                    . "    ) SELECT count(*) "
                    . "    FROM bucket_tree bt JOIN users AS u ON bt.uid = u.ID");
                if (count($bISBOCount) > 0) {
                    if (isset($bISBOCount[0]->count)) {
                        $bISBO = $bISBOCount[0]->count;
                    }
                }
            }
            error_log($currentNoteTids[0]->cuid);
            if (isset($currentNoteTids[0]->cuid)) {
                $cISBOCount =  DB::select("WITH RECURSIVE bucket_tree AS ("
                    . "    SELECT tid, uid, sid, pid, pbid, sbid "
                    . "    FROM bucket_tree_plan "
                    . "    WHERE uid = '" . $currentNoteTids[0]->cuid . "' "
                    . "    UNION SELECT bt.tid, bt.uid, bt.sid, bt.pid, bt.pbid, bt.sbid "
                    . "    FROM bucket_tree_plan bt INNER JOIN bucket_tree T ON T.uid = bt.pid "
                    . "    ) SELECT count(*) "
                    . "    FROM bucket_tree bt JOIN users AS u ON bt.uid = u.ID");
                if (count($cISBOCount) > 0) {
                    if (isset($cISBOCount[0]->count)) {
                        $cISBO = $cISBOCount[0]->count;
                    }
                }
            }
        }

        $aCV = 0;
        $bCV = 0;
        $cCV = 0;
        $aFourWV = 0;
        $bFourWV = 0;
        $cFourWV = 0;
        $fourWeekPEV = 0;
        $volumeDetails = DB::select("SELECT * FROM user_bucket_volumes WHERE user_id = '" . $user->id . "' ORDER BY id DESC LIMIT 4");

        if (count($volumeDetails) > 0) {
            if (isset($volumeDetails[0]->bv_a)) {
                $aCV = $volumeDetails[0]->bv_a;
                $aFourWV = $volumeDetails[0]->bv_a;
            }
            if (isset($volumeDetails[0]->bv_a)) {
                $bCV = $volumeDetails[0]->bv_b;
                $bFourWV = $volumeDetails[0]->bv_b;
            }
            if (isset($volumeDetails[0]->bv_a)) {
                $cCV = $volumeDetails[0]->bv_c;
                $cFourWV = $volumeDetails[0]->bv_c;
            }
            if (isset($volumeDetails[0]->pev)) {
                $fourWeekPEV = $volumeDetails[0]->pev;
            }
        }
        if (count($volumeDetails) > 1) {
            if (isset($volumeDetails[1]->bv_a)) {
                $aFourWV += $volumeDetails[0]->bv_a;
            }
            if (isset($volumeDetails[1]->bv_a)) {
                $bFourWV += $volumeDetails[0]->bv_b;
            }
            if (isset($volumeDetails[1]->bv_a)) {
                $cFourWV += $volumeDetails[0]->bv_c;
            }
            if (isset($volumeDetails[1]->pev)) {
                $fourWeekPEV += $volumeDetails[1]->pev;
            }
        }
        if (count($volumeDetails) > 2) {
            if (isset($volumeDetails[2]->bv_a)) {
                $aFourWV += $volumeDetails[0]->bv_a;
            }
            if (isset($volumeDetails[2]->bv_a)) {
                $bFourWV += $volumeDetails[0]->bv_b;
            }
            if (isset($volumeDetails[2]->bv_a)) {
                $cFourWV += $volumeDetails[0]->bv_c;
            }
            if (isset($volumeDetails[2]->pev)) {
                $fourWeekPEV += $volumeDetails[2]->pev;
            }
        }
        if (count($volumeDetails) > 3) {
            if (isset($volumeDetails[3]->bv_a)) {
                $aFourWV += $volumeDetails[0]->bv_a;
            }
            if (isset($volumeDetails[3]->bv_a)) {
                $bFourWV += $volumeDetails[0]->bv_b;
            }
            if (isset($volumeDetails[3]->bv_a)) {
                $cFourWV += $volumeDetails[0]->bv_c;
            }
            if (isset($volumeDetails[3]->pev)) {
                $fourWeekPEV += $volumeDetails[3]->pev;
            }
        }

        $bucket_a_pers_enrolled = DB::select("select * from get_bucket_pers_enrolled_count($user->id, 1)");
        $bucket_b_pers_enrolled = DB::select("select * from get_bucket_pers_enrolled_count($user->id, 2)");
        $bucket_c_pers_enrolled = DB::select("select * from get_bucket_pers_enrolled_count($user->id, 3)");

        $data = [
            'aISBO' => $aISBO,
            'bISBO' => $bISBO,
            'cISBO' => $cISBO,
            'bucket_a_pers_enrolled' => count($bucket_a_pers_enrolled),
            'bucket_b_pers_enrolled' => count($bucket_b_pers_enrolled),
            'bucket_c_pers_enrolled' => count($bucket_c_pers_enrolled),
            'volumes' => [
                'aCV' => $aCV,
                'bCV' => $bCV,
                'cCV' => $cCV,
                'aFourWV' => $aFourWV,
                'bFourWV' => $bFourWV,
                'cFourWV' => $cFourWV,
                'fourWeekPEV' => $fourWeekPEV
            ],
        ];

        return $data;
    }


    /**
     * Dashboard Details
     * Test mailgun
     */
    public function testMailgun($sendmail = false)
    {
        $response['env_vars'] = $_ENV;

        $response['config_mailgun'] = config('mailgun');
        if ($sendmail) {
            $data = array();
            $template = \App\Models\MailTemplate::getRec(\App\Models\MailTemplate::TYPE_BOOMERANG_INVITATION_MAIL);
            if ($template->is_active == 1) {
                $subject = $template->subject;
                $content = $template->content;
                $fullName = "Euclides Netto";
                //
                $content = str_replace("<dist_first_name>", "Euclides", $content);
                $content = str_replace("<dist_last_name>", "Netto", $content);
                $content = str_replace("<customer_first_name>", "Customer", $content);
                $content = str_replace("<customer_last_name>", "Test", $content);
                $content = str_replace("<boomerang_code>", "123456", $content);
                $content = nl2br($content);
                $data['content'] = $content;

                $toEmail = "enettolima@gmail.com";

                try {
                    $mailgun = Mail::send('admin.mail_template.base_template', $data, function ($message) use ($toEmail, $fullName, $subject) {
                        $message->to($toEmail, $fullName)->subject($subject);
                    });
                } catch (\Exception $e) {
                    report($e);
                    $mailgun = $e;
                }

                // dd($mail);
                $response['mailgun_response'] = $mailgun;

                try {
                    $laravel_mail = Mail::send('admin.mail_template.base_template', $data, function ($m) use ($toEmail, $fullName, $subject) {
                        $m->from("support@ncrease.com", "ibuumerang Support - Laravel Mail");

                        $m->to($toEmail, $fullName)->subject($subject);
                    });
                } catch (\Exception $e) {
                    report($e);
                    $laravel_mail = $e;
                }

                $response['laravel_mail_response'] = $laravel_mail;
            }
        }
        $this->setResponseCode(200);

        $this->setResponse($response);

        return $this->showResponse();
    }
}
