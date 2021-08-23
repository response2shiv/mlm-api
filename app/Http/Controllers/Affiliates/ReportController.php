<?php

namespace App\Http\Controllers\Affiliates;

use Log;
use Carbon\Carbon;
use App\Models\User;
use App\Helpers\Util;
use App\Models\Order;
use App\Models\Address;
use App\Models\Product;
use App\Models\PreOrder;
use App\Models\OrderItem;
use App\Models\PreOrderItem;
use Illuminate\Http\Request;
use App\Models\BinaryPlanNode;
use App\Models\DiscountCoupon;
use App\Models\RankDefinition;
use App\Models\AdminPermission;
use App\Models\OrderConversion;
use App\Models\UserRankHistory;
use App\Models\BinaryCommission;
use App\Models\EwalletTransaction;
use App\Models\UnilevelCommission;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Services\BinaryPlanService;
use App\Http\Controllers\Controller;
use App\Models\LeadershipCommission;
use Illuminate\Support\Facades\Auth;
use App\Services\TsbCommissionService;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\Validator;
use App\Services\UnilevelService as UnilevelService;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * @group Affiliates Reports
 *
 * All affiliates reports.
 */
class ReportController extends Controller
{
    const PAGE_ITEMS_LIMIT = 100;
    const UNILEVEL_KEY = 'unilevel';
    const LEADERSHIP_KEY = 'leadership';

    /** @var BinaryPlanService */
    private $binaryPlanService;

    /**
     * ReportController constructor.
     * @param BinaryPlanService $binaryPlanService
     */
    public function __construct(BinaryPlanService $binaryPlanService)
    {
        $this->binaryPlanService = $binaryPlanService;
    }

    public function getFsbCommissionDataTable()
    {
        $req = request();
        $validator = Validator::make($req->all(), [
            'order_number' => 'int|nullable',
            'tsa' => 'required',
            'volume_type' => 'required',
            'd_from' => 'required',
            'd_to' => 'required',
        ], [
            'order_number.integer' => 'Order number should be integer value',
            'tsa.required' => 'User TAS or Username cannot be empty',
            'volume_type.required' => 'Volume type cannot be empty',
            'd_from.required' => 'From date is required',
            'd_to.required' => 'To date is required',
        ]);
        $msg = "";
        if ($validator->fails()) {
            $messages = $validator->messages();
            foreach ($messages->all() as $m) {
                $msg .= "<div> - " . $m . "</div>";
            }
            return response()->json(['error' => 1, 'msg' => $msg]);
        }
        $nType = [];
        if ($req->volume_type == 'enrollments') {
            $nType = ['Enrollment', 'Upgrade'];
        } 
        if (!isset($req->d_from) || !isset($req->d_to) || !isset($nType) || !isset($req->tsa)) {
            $query = [];
            return DataTables::of($query)->toJson();
        }
        if (substr($req->tsa, 0, 3) == "TSA") {
            $user = \App\User::getByDistId($req->tsa);
        } else if (substr($req->tsa, 0, 1) == "A") {
            $user = \App\User::getByDistId($req->tsa);
        } else {
            $user = \App\User::getByUsername(strtolower($req->tsa));
        }
        $filters['users.sponsorid'] = $user->distid;
        if (isset($req->order_number)) {
            $filters['orders.id'] = $req->order_number;
        }
        $query = DB::table('commission')
            ->select('commission_setting.percentage', 'orders.created_dt', 'users.firstname', 'users.lastname', 'users.username', 'producttype.typedesc', 'products.cv', 'commission.amount', 'commission.memo')
            ->join('users', 'users.id', '=', 'commission.user_id')
            ->join('orderItem', 'commission.order_id', '=', 'orderItem.orderid')
            ->join('orders', 'commission.order_id', '=', 'orders.id')
            ->join('products', 'orderItem.productid', '=', 'products.id')
            ->join('producttype', 'products.producttype', '=', 'producttype.id')
            ->crossJoin('commission_setting')
            ->whereIn('producttype.typedesc', $nType)
            ->whereDate('orders.created_dt', '>=', $req->d_from)
            ->whereDate('orders.created_dt', '<=', $req->d_to)
            ->where('products.id', '!=', \App\Product::ID_STANDBY_CLASS)
            ->where(function ($query) use ($filters) {
                foreach ($filters as $column => $key) {
                    if ($column == 'users.sponsorid' && !empty($key)) {
                        $query->where('users.sponsorid', $key);
                    }
                    if ($column == 'orders.id' && !empty($key)) {
                        $query->where('orders.id', $key);
                    }
                }
            });
        return DataTables::of($query)->toJson();
    }

    ////In use on version 2////
    /**
     * @param null $id
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function getPearReportByUser($id = null)
    {
        if ($id) {
            $user = User::where('id', $id)->first();

            if ($user) {
                return $this->getPearReport($id);
            }
        }
    }

    /**
     * @param null $id
     * @return mixed
     */
    public function getPearReport(Request $request)
    {
        if ($request->history) {
            $month = substr($request->history, 0, 2);
            $year = substr($request->history, 2, 4);
            $current = Carbon::parse($year . '-' . $month);
            $current = Carbon::parse($current->endOfMonth()->endOfDay());
            // dd($current);
        } else {
            $month = date('m');
            $year = date('Y');
            $current = Carbon::now();
        }

        if ($request->id) {
            $user = User::where('id', $request->id)->first();

            if (!$user) {
                throw new AccessDeniedHttpException();
            }
        } else {
            $user = Auth::user();
        }

        $current_month_rank = UserRankHistory::where('user_id', $user->id)
            ->whereMonth('period', $month)
            ->whereYear('period', $year)
            ->pluck('monthly_rank')
            ->first();
        
        if (!is_null($current_month_rank)) {
            $UserRankHistory = UserRankHistory::where('user_id', $user->id)->orderBy('period', 'desc')->first();
            $current_month_rank = $UserRankHistory->monthly_rank;
        }

        $rank_def = RankDefinition::where('rankval', $current_month_rank + 10)->first();

        $min_qv = $rank_def->min_qv * $rank_def->rank_limit;
        $min_tsa = $rank_def->min_tsa * $rank_def->rank_limit;

        $query = DB::select(sprintf(
            "
            SELECT
                u.firstname,
                u.lastname,
                u.distid,
                u.id,
                urh.user_id,
                urh.monthly_qv,
                urh.qv_contribution,
                urh.monthly_cv,
                urh.monthly_rank_desc,
                urh.qualified_qv,
                u.account_status,
                COALESCE(pqv, 0) as pqv,
                concat(u.firstname,' ',u.lastname) as name
            FROM users u
            JOIN rank_definition on u.current_month_rank = rank_definition.rankval
            left JOIN (
                SELECT
                    CAST(CASE WHEN
                        COALESCE(h.monthly_qv, 0) > " . $min_qv . " THEN " . $min_qv . "
                        ELSE COALESCE(h.monthly_qv , 0) END as bigint) as qv_contribution,
                    " . $min_qv . " as min_qv,
                    CAST(CASE WHEN COALESCE(h.monthly_tsa, 0) > " . $min_tsa . " THEN round(coalesce(" . $min_tsa . ",0),0)
                        ELSE COALESCE(h.monthly_tsa, 0) END as integer) as tsa_contribution,
                    round(coalesce(" . $min_tsa . ",0),0) as min_tsa,
                    h.monthly_qv,
                    h.qualified_qv,
                    h.monthly_cv,
                    h.monthly_rank_desc,
                    h.user_id
                FROM user_rank_history AS h
                WHERE
                    EXTRACT(MONTH FROM h.period) = '%s'
                    AND EXTRACT(YEAR FROM h.period) = '%s'
                ORDER BY qv_contribution DESC
            ) urh on u.id = urh.user_id
            left join (
                SELECT
                    COALESCE(SUM(o.orderqv), 0) as pqv, userid
                FROM users u_2 JOIN orders o ON u_2.id = o.userid
                WHERE o.created_dt >= '%s'
                    AND o.created_dt <= '%s'
                    AND (o.statuscode = 1 OR o.statuscode = 6)
                    AND u_2.sponsorid = '%s'
                GROUP BY userid
            ) x ON u.id = x.userid
            where u.sponsorid = '" . $user->distid . "' AND u.usertype <> 5;
        ",
            $month,
            $year,
            $current->copy()->startOfMonth()->startOfDay()->format('Y-m-d'),
            $current->format('Y-m-d'),
            $user->distid
        ));

        // dd($current->endOfMonth()->endOfDay()->format('Y-m-d'));

        return DataTables::of($query)
            // ->addColumn('name', function ($user) {
            //     return sprintf(
            //         '<a class="table-link" href="%s">%s %s</a>',
            //         route('pear-report', $user->user_id),
            //         $user->firstname,
            //         $user->lastname
            //     );
            // })
            // ->rawColumns(['name'])
            ->toJson();
    }
    /////

    public function getHistoricalReport()
    {
        $user = Auth::user();

        $currentMonth = Util::getUserCurrentDate()->startOfMonth();
        $currentWeek = Util::getUserCurrentDate()->startOfWeek();

        $commissions = BinaryCommission::where('user_id', $user->id)
            ->whereIn('status', [BinaryCommission::PAID_STATUS])
            ->where('week_ending', '<', $currentWeek)
            ->orderBy('week_ending', 'desc')
            ->get();

        $rankHistory = UserRankHistory::where('user_id', $user->id)
            ->where('period', '<', $currentMonth)
            ->orderBy('period', 'desc')
            ->get();

        $this->setResponse([
            'commissions' => $commissions,
            'rankHistory' => $rankHistory,
        ]);

        $this->setResponseCode(200);
        return $this->showResponse();
    }

    public function commissionVolume()
    {
        return view('admin.reports.volume');
    }
    public function salesReport()
    {
        return view('affiliate.reports.sales');
    }

    public function tools()
    {
        return view('affiliate.reports.tools');
    }

    public function commissionReport()
    {
        $pendingPost = DB::table('commission_dates')
            ->select('*')
            ->get();

        return view('affiliate.reports.commission')->with([
            'weeks' => $this->getWeeklyCommissionDates(),
            'pendingPost' => $pendingPost,
            'monthCommissionDates' => $this->getMonthCommissionDates(),
        ]);
    }

    public function unilevelCommissionDetails(Request $request)
    {
        $date = $request->input('date');

        $unilevelCommissions = UnilevelCommission::where('user_id', '=', Auth::user()->id)
            ->where('end_date', '=', $date)
            ->whereIn('status', [UnilevelService::POSTED_STATUS, UnilevelService::PAID_STATUS])
            ->simplePaginate(self::PAGE_ITEMS_LIMIT);

        $sum = UnilevelCommission::where('user_id', '=', Auth::user()->id)
            ->where('end_date', '=', $date)
            ->whereIn('status', [UnilevelService::POSTED_STATUS, UnilevelService::PAID_STATUS])
            ->sum('amount');

        return view('affiliate.reports.unilevel')->with([
            'commissions' => $unilevelCommissions,
            'sum' => round($sum, 2),
        ]);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function leadershipCommissionDetails(Request $request)
    {
        $date = $request->input('date');

        $commissions = LeadershipCommission::where('user_id', '=', Auth::user()->id)
            ->where('end_date', '=', $date)
            ->whereIn('status', [UnilevelService::POSTED_STATUS, UnilevelService::PAID_STATUS])
            ->simplePaginate(self::PAGE_ITEMS_LIMIT);

        $sum = LeadershipCommission::where('user_id', '=', Auth::user()->id)
            ->where('end_date', '=', $date)
            ->whereIn('status', [UnilevelService::POSTED_STATUS, UnilevelService::PAID_STATUS])
            ->sum('amount');

        return view('affiliate.reports.leadership')->with([
            'commissions' => $commissions,
            'sum' => round($sum, 2),
        ]);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function tsbCommissionDetails(Request $request)
    {
        $date = $request->input('date');
        $commissions = \App\TSBCommission::where('user_id', '=', Auth::user()->id)
            ->where('created_at', '=', $date)
            ->whereIn('status', [UnilevelService::POSTED_STATUS, UnilevelService::PAID_STATUS])
            ->simplePaginate(self::PAGE_ITEMS_LIMIT);

        $sum = \App\TSBCommission::where('user_id', '=', Auth::user()->id)
            ->where('created_at', '=', $date)
            ->whereIn('status', [UnilevelService::POSTED_STATUS, UnilevelService::PAID_STATUS])
            ->sum('amount');

        $pendingPost = DB::table('commission_dates')
            ->select('*')
            ->get();

        return view('affiliate.reports.tsb')->with([
            'commissions' => $commissions,
            'sum' => round($sum, 2),
            'pendingPost' => $pendingPost,
            'monthCommissionDates' => $this->getMonthCommissionDates(),
            'weeks' => $this->getWeeklyCommissionDates()
        ]);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function orderDetails(Request $request)
    {
        $id = $request->input('id');
        $date = $request->input('date');
        $commission = $request->input('commission');

        switch ($commission) {
            case self::UNILEVEL_KEY:
                $commissions = UnilevelCommission::where('order_id', $id)
                    ->whereDate('end_date', '=', $date)
                    ->whereIn('status', [UnilevelService::POSTED_STATUS, UnilevelService::PAID_STATUS])
                    ->orderBy('level', 'asc')
                    ->get();
                break;
            case self::LEADERSHIP_KEY:
                $commissions = LeadershipCommission::where('order_id', $id)
                    ->whereDate('end_date', '=', $date)
                    ->whereIn('status', [UnilevelService::POSTED_STATUS, UnilevelService::PAID_STATUS])
                    ->orderBy('level', 'desc')
                    ->get();
                break;
            default:
                $commissions = [];
        }

        return view('affiliate.reports.order_details')->with([
            'commissions' => $commissions,
            'type' => $commission,
        ]);
    }

    public function commissionReportWeekly(Request $request)
    {
        $selected = $request->input('unilevel_date');

        $pendingPost = DB::table('commission_dates')
            ->select('*')
            ->get();

        $weekEnding = $request->input('week_ending');
        $weekEnding = explode('#', $weekEnding);

        $sum = UnilevelCommission::where('user_id', '=', Auth::user()->id)
            ->whereRaw('end_date::date = ?', [$selected])
            ->sum('amount');


        $unilevelCommissions = DB::table('unilevel_commission')
            ->selectRaw('end_date, ' . round($sum, 2) . ' as sum')
            ->where('user_id', Auth::user()->id)
            ->whereIn('status', [UnilevelService::POSTED_STATUS, UnilevelService::PAID_STATUS])
            ->whereRaw('end_date::date = ?', [$selected])
            ->groupBy('end_date')
            ->get();


        $sum = LeadershipCommission::where('user_id', '=', Auth::user()->id)
            ->whereRaw('end_date::date = ?', [$selected])
            ->sum('amount');

        $leadershipCommissions = DB::table('leadership_commission')
            ->selectRaw('end_date, ' . round($sum, 2) . ' as sum')
            ->where('user_id', Auth::user()->id)
            ->whereIn('status', [UnilevelService::POSTED_STATUS, UnilevelService::PAID_STATUS])
            ->whereRaw('end_date::date = ?', [$selected])
            ->groupBy('end_date')
            ->get();


        $sum = DB::table('tsb_commission')->where('user_id', '=', Auth::user()->id)
            ->whereRaw('created_at::date = ?', [$selected])
            ->sum('amount');

        $tsbCommissions = DB::table('tsb_commission')
            ->selectRaw('created_at as end_date, ' . round($sum, 2) . ' as sum')
            ->where('user_id', Auth::user()->id)
            ->whereIn('status', [UnilevelService::POSTED_STATUS, UnilevelService::PAID_STATUS])
            ->whereRaw('created_at::date = ?', [$selected])
            ->groupBy('created_at')
            ->get();

        if (count($weekEnding) > 1) {
            //pending post
            $pendingCommission = DB::table('commission_temp_post')
                ->where('user_id', Auth::user()->id)
                ->get();

            return view('affiliate.reports.pending_commission')->with([
                'weeks' => $this->getWeeklyCommissionDates(),
                'week_ending' => $weekEnding,
                'pendingCommission' => $pendingCommission ?: 0,
                'pendingPost' => $pendingPost,
                'unilevelCommissions' => $unilevelCommissions,
                'leadershipCommissions' => $leadershipCommissions,
                'tsbCommissions' => $tsbCommissions,
                'selected' => $selected,
                'monthCommissionDates' => $this->getMonthCommissionDates(),
            ]);
        } else {
            $weekEnding = $weekEnding[0];
            $weekCommissionDetail = $binaryCommission = null;
            if ($weekEnding) {
                $weekCommissionDetail = DB::table('week_detail')
                    ->where('user_id', Auth::user()->id)
                    ->where('week_ending', $weekEnding)
                    ->get();

                $binaryCommission = DB::table('binary_commission')
                    ->where('user_id', Auth::user()->id)
                    ->where('week_ending', $weekEnding)
                    ->whereIn('status', [BinaryCommission::PAID_STATUS, BinaryCommission::POSTED_STATUS])
                    ->value('amount_earned');
            }

            // TODO: Remove it in future
            $adjustmentBinary26 = null;
            if ($weekEnding === '2019-06-02 00:00:00') {

                $adjustTransactRow = EwalletTransaction::where('commission_type', 'BC_ADJUST_26')
                    ->where('user_id', Auth::user()->id)
                    ->first();

                if ($adjustTransactRow) {
                    $adjustmentBinary26 = floatval($adjustTransactRow->amount);
                }
            }

            $adjustment5_12 = null;
            $adjustment5_19 = null;
            $adjustment5_26 = null;
            $adjustment6_02 = null;

            if ($weekEnding === '2019-05-12 23:59:59') {
                $adjustTransactRow512 = EwalletTransaction::where('commission_type', 'BC_ADJUST_5_12')
                    ->where('user_id', Auth::user()->id)
                    ->first();

                if ($adjustTransactRow512) {
                    $adjustment5_12 = floatval($adjustTransactRow512->amount);
                }
            }

            if ($weekEnding === '2019-05-19 23:59:59') {
                $adjustTransactRow519 = EwalletTransaction::where('commission_type', 'BC_ADJUST_5_19')
                    ->where('user_id', Auth::user()->id)
                    ->first();

                if ($adjustTransactRow519) {
                    $adjustment5_19 = floatval($adjustTransactRow519->amount);
                }
            }

            if ($weekEnding === '2019-05-26 23:59:59') {
                $adjustTransactRow526 = EwalletTransaction::where('commission_type', 'BC_ADJUST_5_26')
                    ->where('user_id', Auth::user()->id)
                    ->first();

                if ($adjustTransactRow526) {
                    $adjustment5_26 = floatval($adjustTransactRow526->amount);
                }
            }

            if ($weekEnding === '2019-06-02 23:59:59') {
                $adjustTransactRow602 = EwalletTransaction::where('commission_type', 'BC_ADJUST_6_02')
                    ->where('user_id', Auth::user()->id)
                    ->first();

                if ($adjustTransactRow602) {
                    $adjustment6_02 = floatval($adjustTransactRow602->amount);
                }
            }

            $uni_5_31 = null;
            $uni_5_31_prefix = '+';
            if ($selected === '2019-05-31 23:59:59') {
                $uni5_31_row = EwalletTransaction::where('commission_type', 'UL_5_31')
                    ->where('user_id', Auth::user()->id)
                    ->first();

                if ($uni5_31_row) {
                    $uni_5_31_prefix = $uni5_31_row->type === EwalletTransaction::ADJUSTMENT_DEDUCT ? '-' : '+';
                    $uni_5_31 = number_format(floatval($uni5_31_row->amount), 2);
                }
            }
            return view('affiliate.reports.commission')->with([
                'weeks' => $this->getWeeklyCommissionDates(),
                'week_ending' => $weekEnding,
                'week_commission_detail' => $weekCommissionDetail,
                'binaryCommission' => $binaryCommission ?: 0,
                'adjustmentBinary26' => $adjustmentBinary26,
                'pendingPost' => $pendingPost,
                'unilevelCommissions' => $unilevelCommissions,
                'leadershipCommissions' => $leadershipCommissions,
                'tsbCommissions' => $tsbCommissions,
                'selected' => $selected,
                'adjustment_5_31' => sprintf('%s$%s', $uni_5_31_prefix, $uni_5_31),
                'monthCommissionDates' => $this->getMonthCommissionDates(),
                'adjustment5_12' => $adjustment5_12,
                'adjustment5_19' => $adjustment5_19,
                'adjustment5_26' => $adjustment5_26,
                'adjustment6_02' => $adjustment6_02,
            ]);
        }
    }

    /*public function commissionReportWeeklyDetails(Request $request) {
        $weekEnding = $request->input('week_ending');
        $binaryWeekEnding = $request->input('binary_week_ending');

        $pendingPost = DB::table('commission_dates')
            ->select('*')
            ->get();

        $weekDate = $weekEnding ?: $binaryWeekEnding;

        $weekCommissionDetail = DB::table('week_detail')
            ->where('user_id', Auth::user()->id)
            ->where('week_ending', $weekDate)
            ->get();

        $binaryCommission = DB::table('binary_commission')
            ->select('amount_earned')
            ->where('user_id', Auth::user()->id)
            ->whereIn('status', [BinaryCommission::PAID_STATUS, BinaryCommission::POSTED_STATUS])
            ->where('week_ending', $weekDate)
            ->value('amount_earned');

        $commissions = $weekEnding ? DB::table('commission')
            ->join('users', 'commission.initiated_user_id', '=', 'users.id')
            ->select('commission.*', 'users.firstname', 'users.lastname')
            ->where('user_id', Auth::user()->id)
            ->where('processed_date', $weekDate)
            ->get() : null;

        $binaryCommissions = $binaryWeekEnding ? BinaryCommission::select('*')
            ->where('user_id', Auth::user()->id)
            ->where('week_ending', $weekDate)
            ->get() : null;

        // TODO: Remove it in future
        $adjustmentBinary26 = null;
        if ($binaryWeekEnding === '2019-06-02 00:00:00') {

            $adjustTransactRow = EwalletTransaction::where('commission_type', 'BC_ADJUST_26')
                ->where('user_id', Auth::user()->id)
                ->first();

            if ($adjustTransactRow) {
                $adjustmentBinary26 = floatval($adjustTransactRow->amount);
            }
        }

        $adjustment5_12 = null;
        $adjustment5_19 = null;
        $adjustment5_26 = null;
        $adjustment6_02 = null;

        if ($binaryWeekEnding === '2019-05-12 23:59:59') {
            $adjustTransactRow512 = EwalletTransaction::where('commission_type', 'BC_ADJUST_5_12')
                ->where('user_id', Auth::user()->id)
                ->first();

            if ($adjustTransactRow512) {
                $adjustment5_12 = floatval($adjustTransactRow512->amount);
            }
        }

        if ($binaryWeekEnding === '2019-05-19 23:59:59') {
            $adjustTransactRow519 = EwalletTransaction::where('commission_type', 'BC_ADJUST_5_19')
                ->where('user_id', Auth::user()->id)
                ->first();

            if ($adjustTransactRow519) {
                $adjustment5_19 = floatval($adjustTransactRow519->amount);
            }
        }

        if ($binaryWeekEnding === '2019-05-26 23:59:59') {
            $adjustTransactRow526 = EwalletTransaction::where('commission_type', 'BC_ADJUST_5_26')
                ->where('user_id', Auth::user()->id)
                ->first();

            if ($adjustTransactRow526) {
                $adjustment5_26 = floatval($adjustTransactRow526->amount);
            }
        }

        if ($binaryWeekEnding === '2019-06-02 23:59:59') {
            $adjustTransactRow602 = EwalletTransaction::where('commission_type', 'BC_ADJUST_6_02')
                ->where('user_id', Auth::user()->id)
                ->first();

            if ($adjustTransactRow602) {
                $adjustment6_02 = floatval($adjustTransactRow602->amount);
            }
        }

        $unilevelCommissions = DB::table('unilevel_commission')
            ->select('end_date')
            ->where('user_id', Auth::user()->id)
            ->whereIn('status', [UnilevelService::POSTED_STATUS, UnilevelService::PAID_STATUS])
            ->groupBy('end_date')
            ->orderBy('end_date', 'desc')
            ->get();

        return view('affiliate.reports.commission')->with([
            'weeks' => $this->getWeeklyCommissionDates(),
            'week_ending' => $weekEnding ?: $binaryWeekEnding,
            'week_commission_detail' => $weekCommissionDetail,
            'commissions' => $commissions,
            'binaryCommissions' => $binaryCommissions,
            'binaryCommission' => $binaryCommission ?: 0,
            'adjustmentBinary26' => $adjustmentBinary26,
            'pendingPost' => $pendingPost,
            'unilevelCommissions' => $unilevelCommissions,
            'monthCommissionDates' => $this->getMonthCommissionDates(),
            'adjustment5_12' => $adjustment5_12,
            'adjustment5_19' => $adjustment5_19,
            'adjustment5_26' => $adjustment5_26,
            'adjustment6_02' => $adjustment6_02,
        ]);
    }*/

    /*public function personallyEnrolledReport() {
        return view('affiliate.reports.personally_enrolled');
    }

    public function entireOrganizationReport() {
        return view('affiliate.reports.entire_organization_report');
    }

    public function weeklyBinaryReport(Request $request)
    {

        $d = array();
        $d['from'] = $request->get('from');
        $d['to'] = $request->get('to');
        return view('affiliate.reports.weekly_binary_view')->with($d);
    }

    public function weeklyEnrollmentReport()
    {
        return view('affiliate.reports.weekly_enrollment_report');
    }*/

    /**
     * @param Request $request
     * @return false|string
     */
    /**
     * Entire Organization Report
     *
     * [This brings an entire organization report]
     *
     */

    public function getEntireOrganizationReportDataTable(Request $request)
    {
        $draw = $request->get('draw');
        $start = $request->get('start');
        $length = $request->get('length');
        $search = $request->get('search')['value'];
        $order = !empty($request->get('order')[0]) ? $request->get('order')[0] : [];

        $levelFromSearch = !is_null($request->get('levelFrom')) ? trim($request->get('levelFrom')) : null;
        $levelToSearch = !is_null($request->get('levelTo')) ? trim($request->get('levelTo')) : null;
        $viewOption = !empty($request->get('viewOption')) ? $request->get('viewOption') : 'selected';

        $dateFrom = !is_null($request->get('dateFrom')) ? trim($request->get('dateFrom')) : null;
        $dateTo = !is_null($request->get('dateTo')) ? trim($request->get('dateTo')) : null;

        $orderByColumnName = 'level';
        $orderByDirection  = 'asc';

        // Order
        if (isset($order['column']) && !empty($request['columns'][$order['column']])) {
            // Sets order by column name
            $orderByColumnName = isset($request['columns'][$order['column']]['name'])
                ? $request['columns'][$order['column']]['name']
                : $request['columns'][$order['column']]['data'];

            // Sets order by direction
            $orderByDirection  = $order['dir'];
        }

        if ($search) {
            $search = strtoupper($search);
            $criteria = "WHERE (upper(CONCAT(u.firstname, ' ', u.lastname)) LIKE '%$search%' OR upper(u.distid) LIKE '%$search%')";

        } else {
            $criteria = '';
        }

        // Date range
        $dateCriteria = '';

        if (!is_null($dateFrom) && !is_null($dateTo)) {
            $operator = (!empty($criteria) || !empty($levelCriteria)) ? 'AND' : 'WHERE';

            $dateCriteria = sprintf("%s u.created_dt::date BETWEEN '%s' AND '%s'", $operator, $dateFrom, $dateTo);
        }

        // Level
        $levelCriteria = '';

        if ($viewOption == 'selected') {
            $levelCriteria = (!is_null($levelFromSearch) &&  !is_null($levelToSearch) && !$dateCriteria)
                ? sprintf(' WHERE (et.level >= %s AND et.level <= %s)', $levelFromSearch, $levelToSearch) : '';

            if ($criteria) {
                $levelCriteria = (!is_null($levelFromSearch) &&  !is_null($levelToSearch) && !$dateCriteria)
                    ? sprintf(' AND (et.level >= %s AND et.level <= %s)', $levelFromSearch, $levelToSearch) : '';
            }
        }

        // dd($levelCriteria);

        // Is active
        $isActiveCriteria = " WHERE u.is_active = 1";

        if ($criteria || $levelCriteria || $dateCriteria) {
            $isActiveCriteria = " AND u.is_active = 1";
        }

        // Builds the query
        $basicSelectQuery = "select et.level,u.id,u.firstname,u.lastname,u.distid,u.username,u.binary_q_l,u.binary_q_r,
            c.countrycode,a.stateprov,u.current_product_id,concat(sps.firstname,' ',sps.lastname) as sponser_name,
            u.sponsorid,rd_lifetime.rankdesc as lifetime_rank,previous_month_rank.rankdesc as previous_month_rank,
            u.current_month_pqv,u.is_active,
            u.created_dt::date as enrollment_date ";

        $fromQueryPart = " from enrolment_tree_tsa(:distid) et
            left join users as u on u.id = et.id
            left join (select * from addresses where addrtype = '3' AND \"primary\" = 1) a on a.userid = u.id
            left join country as c on a.countrycode = c.countrycode
            left join users as sps on u.sponsorid = sps.distid
            left join (select users_id,max(lifetime_rank) as lifetime_rank
                        from rank_history group by users_id) rh on u.id=rh.users_id
            left join rank_definition as rd_lifetime on rd_lifetime.rankval = rh.lifetime_rank
            left join rank_definition as rd_current_month on rd_current_month.rankval = u.current_month_rank
            left join (SELECT b.rankdesc,
                        a.user_id
                        FROM user_rank_history a
                        JOIN rank_definition b ON a.monthly_rank = b.rankval
                        WHERE
                        a.period::date =
                         (date_trunc('MONTH'::text, now()::timestamp without time zone) + '-1 days'::interval)::date
                        ) as previous_month_rank on u.id = previous_month_rank.user_id
             %s %s %s";

        $orderQueryPart = " order by %s %s";

        $limitOffsetPart = " limit %s offset %s";

        $query = sprintf(
            $basicSelectQuery . $fromQueryPart . $orderQueryPart . $limitOffsetPart,
            $criteria,
            $levelCriteria,
            $dateCriteria,
            $orderByColumnName,
            $orderByDirection,
            $length,
            $start
        );

        // Query just for count the total records
        $countSelectQuery = "select count(et.id) ";

        $countQuery = sprintf(
            $countSelectQuery . $fromQueryPart,
            $criteria,
            $levelCriteria,
            $dateCriteria
        );

        // Query to count the records for ACTIVE USERS
        $countActiveSelectQueryPart = "select count(et.id) ";

        $countQueryActive = sprintf(
            $countActiveSelectQueryPart . $fromQueryPart,
            $criteria,
            $levelCriteria . $isActiveCriteria,
            $dateCriteria
        );

        // Executes the main query to populate the datatables
        $data = DB::select(DB::raw($query), [":distid" => Auth::user()->distid]);

        // Executes the query to count all records without limit and offset
        $recordsTotal = DB::select(DB::raw($countQuery), [":distid" => Auth::user()->distid])[0]->count;

        // Executes the query to count all active users
        $countActiveUsers = DB::select(DB::raw($countQueryActive), [":distid" => Auth::user()->distid])[0]->count;

        foreach ($data as $user) {
            $node = BinaryPlanNode::where('user_id', $user->id)->first();

            if ($node) {
                $directions = $this->binaryPlanService->getActiveDirections($node);

                $user->binary_q_l = $directions['left'];
                $user->binary_q_r = $directions['right'];
            }
        };

        return [
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsTotal,
            'countActiveUsers' => $countActiveUsers,
            'data' => $data,
            'levelTo' => $levelToSearch,
            'levelFrom' => $levelFromSearch,
            'viewOption' => $viewOption
        ];
    }

    /**
     * @param Request $request
     * @return false|string
     */
    /**
     * Entire Organization Report - get levels
     *
     * [This brings an entire organization report]
     *
     */
    public function getEntireOrganizationReportData()
    {
        $maxLevelRecords = DB::select(
            DB::raw("SELECT level FROM enrolment_tree_tsa(:distid) ORDER BY level DESC LIMIT 1"),
            [":distid" => Auth::user()->distid]
        );
        $d['max_level'] = 0;
        foreach ($maxLevelRecords as $maxLevelRecord) {
            $d['max_level'] = $maxLevelRecord->level;
        }

        $d['viewOption'] = 'selected';
        $d['levelFrom'] = 0;
        $d['levelTo'] = 0;

        if ($d['max_level']) {
            $d['levelFrom'] = 1;
            $d['levelTo'] = 1;
        }

        $this->setResponse($d);
        $this->setResponseCode(200);

        return $this->showResponse();
    }

    /**
     * @param Request $request
     * @return false|string
     */
    /**
     * Entire Organization Report
     *
     * [This brings an entire organization report]
     *
     */
    //    public function getEntireOrganizationReportDataTable(Request $request)
    //    {
    //        $data = $this->getUsersAndCount($request);
    //
    //        return json_encode($data);
    //    }

    public function getUsersAndCount(Request $request)
    {
        $draw = $request->get('draw');
        $start = $request->get('start');
        $length = $request->get('length');
        $search = $request->get('search')['value'];
        $order = !empty($request->get('order')[0]) ? $request->get('order')[0] : [];

        $levelFromSearch = !is_null($request->get('levelFrom')) ? trim($request->get('levelFrom')) : null;
        $levelToSearch = !is_null($request->get('levelTo')) ? trim($request->get('levelTo')) : null;
        $viewOption = !empty($request->get('viewOption')) ? $request->get('viewOption') : 'selected';

        $orderByColumnName = 'level';
        $orderByDirection  = 'asc';



        $criteria = $search ? sprintf('WHERE u.distid LIKE \'%%%s%%\'', $search) : '';
        $levelCriteria = '';

        if ($viewOption == 'selected') {
            $levelCriteria = (!is_null($levelFromSearch) &&  !is_null($levelToSearch))
                ? sprintf(' WHERE (et.level >= %s AND et.level <= %s)', $levelFromSearch, $levelToSearch) : '';

            if ($criteria) {
                $levelCriteria = (!is_null($levelFromSearch) &&  !is_null($levelToSearch))
                    ? sprintf(' AND (et.level >= %s AND et.level <= %s)', $levelFromSearch, $levelToSearch) : '';
            }
        }
        $isActiveCriteria = " WHERE et.is_active = 1";

        if ($criteria || $levelCriteria) {
            $isActiveCriteria = " AND et.is_active = 1";
        }
        $basicSelectQuery = "select et.level,u.id,u.firstname,u.lastname,u.distid,u.username,u.binary_q_l,u.binary_q_r,
            c.countrycode,a.stateprov,u.current_product_id,concat(sps.firstname,' ',sps.lastname) as sponser_name,
            u.sponsorid,rd_lifetime.rankdesc as lifetime_rank,previous_month_rank.rankdesc as previous_month_rank,
            u.current_month_pqv,u.is_active,
            u.created_dt::date as enrollment_date ";

        $fromQueryPart = " from enrolment_tree_tsa(:distid) et
            left join users as u on u.id = et.id
            left join (select * from addresses where addrtype = '3' AND \"primary\" = 1) a on a.userid = u.id
            left join country as c on a.countrycode = c.countrycode
            left join users as sps on u.sponsorid = sps.distid
            left join (select users_id,max(lifetime_rank) as lifetime_rank
                        from rank_history group by users_id) rh on u.id=rh.users_id
            left join rank_definition as rd_lifetime on rd_lifetime.rankval = rh.lifetime_rank
            left join rank_definition as rd_current_month on rd_current_month.rankval = u.current_month_rank
            left join (SELECT b.rankdesc,
                        a.user_id
                        FROM user_rank_history a
                        JOIN rank_definition b ON a.monthly_rank = b.rankval
                        WHERE
                        a.period::date =
                         (date_trunc('MONTH'::text, now()::timestamp without time zone) + '-1 days'::interval)::date
                        ) as previous_month_rank on u.id = previous_month_rank.user_id
             %s %s";
        $orderQueryPart = " order by %s %s";

        $query = sprintf(
            $basicSelectQuery . $fromQueryPart . $orderQueryPart,
            $criteria,
            $levelCriteria,
            $orderByColumnName,
            $orderByDirection
        );

        $countActiveSelectQueryPart = "select et.id ";

        $countQuery = sprintf(
            $countActiveSelectQueryPart . $fromQueryPart,
            $criteria,
            $levelCriteria . $isActiveCriteria
        );

        $users = DB::select(DB::raw($query), [":distid" => Auth::user()->distid]);

        $activeUsersCountRecords = DB::select(DB::raw($countQuery), [":distid" => Auth::user()->distid]);

        $count = count($users);
        $countActiveUsers = count($activeUsersCountRecords);

        $userPagination = array_slice($users, $start, $length);

        foreach ($userPagination as $user) {
            $node = BinaryPlanNode::where('user_id', $user->id)->first();

            if (!$node) {
                continue;
            }

            $directions = $this->binaryPlanService->getActiveDirections($node);

            $user->binary_q_l = $directions['left'];
            $user->binary_q_r = $directions['right'];
        };

        return [
            'draw' => $draw,
            'recordsTotal' => $count,
            'recordsFiltered' => $count,
            'countActiveUsers' => $countActiveUsers,
            'data' => $userPagination,
            'levelTo' => $levelToSearch,
            'levelFrom' => $levelFromSearch,
            'viewOption' => $viewOption
        ];
    }


    public function weeklyEnrollmentReportDataTable(Request $request)
    {
        $draw = $request->get('draw');
        $start = $request->get('start');
        $length = $request->get('length');
        $search = $request->get('search')['value'];
        $firstDayOfTheWeek = date('Y-m-d', strtotime("this week"));
        $lastDayOfTheWeek = date('Y-m-d', strtotime($firstDayOfTheWeek . ' + 7 days'));
        $criteria = "where date(u.created_dt) >= '$firstDayOfTheWeek' and date(u.created_dt) < '$lastDayOfTheWeek'";
        $criteria .= $search ? sprintf('AND u.distid LIKE \'%%%s%%\'', $search) : '';

        $query = sprintf("select u.id,u.firstname,u.lastname,u.distid,u.username,u.created_dt,c.countrycode,
            a.stateprov,u.current_product_id,concat(sps.firstname,' ',sps.lastname) as sponser_name,
            u.sponsorid,u.is_active
            from enrolment_tree_tsa(:distid) et
            left join users as u on u.id = et.id
            left join (select * from addresses where addrtype = '3' AND \"primary\" = 1) a on a.userid = u.id
            left join country as c on a.countrycode = c.countrycode
            left join users as sps on u.sponsorid = sps.distid
            %s
            order by date(u.created_dt) desc", $criteria);


        $users = DB::select(DB::raw($query), [":distid" => Auth::user()->distid]);

        $count = count($users);
        $userPagination = array_slice($users, $start, $length);

        $data = array(
            'draw' => $draw,
            'recordsTotal' => $count,
            'recordsFiltered' => $count,
            'data' => $userPagination,
        );

        return json_encode($data);
    }

    public function weeklyBinaryReportDataTable(Request $request)
    {
        $distid = Auth::user()->distid;
        $draw = $request->get('draw');
        $start = $request->get('start');
        $length = $request->get('length');
        $search = $request->get('search')['value'];
        $order = !empty($request->get('order')[0]) ? $request->get('order')[0] : [];

        # Get initial day of the week
        $firstDayOfTheWeek = date('Y-m-d', strtotime("this week"));
        $lastDayOfTheWeek = date('Y-m-d', strtotime($firstDayOfTheWeek . ' + 7 days'));

        # Date range
        $from = $request->get('from', $firstDayOfTheWeek);
        $to   = $request->get('to', $lastDayOfTheWeek);

        if (!empty($from) && !empty($to)) {
            $firstDayOfTheWeek = date('Y-m-d', strtotime($from));
            $lastDayOfTheWeek = date('Y-m-d', strtotime($to));
        }


        if (isset($order['column']) && !empty($request['columns'][$order['column']])) {
            $orderByColumnName = isset($request['columns'][$order['column']]['name'])
                ? $request['columns'][$order['column']]['name'] : $request['columns'][$order['column']]['data'];
            $orderByDirection  = $order['dir'];
        }

        $criteria = "AND  (b.distid LIKE '%$search%'
        OR b.firstname LIKE '%$search%'
        OR b.lastname LIKE '%$search%'
        OR b.username LIKE '%$search%')";

        $query = "
            SELECT b.distid
                 , b.firstname
                 , b.lastname
                 , b.username
                 , b.created_dt
                 , b.current_product_id
                 , x.*
                 , b.usertype
            FROM (
                SELECT *, 'R' as direction
                  FROM public.get_binary_orders(
                    '$distid',
                    'R',
                    '$firstDayOfTheWeek',
                    '$lastDayOfTheWeek 23:59:59'
                )
                UNION ALL

                SELECT *, 'L' as direction
                  FROM public.get_binary_orders(
                    '$distid',
                    'L',
                    '$firstDayOfTheWeek',
                    '$lastDayOfTheWeek 23:59:59'
                )
            ) x, users b

    INNER JOIN products p ON b.current_product_id = p.id
            WHERE x.user_id = b.id
              AND b.usertype <> 5 $criteria
        ORDER BY b.$orderByColumnName $orderByDirection";

        $users = DB::select(DB::raw($query));
        $count = count($users);
        $userPagination = array_slice($users, $start, $length);

        $data = array(
            'draw' => $draw,
            'recordsTotal' => $count,
            'recordsFiltered' => $count,
            'data' => $userPagination,
            'from' => $from,
            'to'   => $to,
        );

        return json_encode($data);
    }

    /*public function personallyEnrolledByPackage($packId) {
        $d = array();
        $title = "";
        if ($packId == 1) {
            $title = "Standby distributors";
        } else if ($packId == 2) {
            $title = "Coach class distributors";
        } else if ($packId == 3) {
            $title = "Business class distributors";
        } else if ($packId == 4) {
            $title = "First class distributors";
        }
        $d['title'] = $title;
        $d['packId'] = $packId;
        return view('affiliate.reports.personally_enrolled_by_package')->with($d);
    }*/

    public function getPersonallyEnrolledByPackage($packId)
    {
        $q = DB::table('users');
        $q->select('id', 'firstname', 'lastname', 'email', 'account_status', 'username', 'basic_info_updated', 'distid', 'current_product_id');
        $q->where('sponsorid', Auth::user()->distid);
        if ($packId == 4) {
            $query = $q->whereIn('current_product_id', [4, 13]);
        } else {
            $query = $q->where('current_product_id', $packId);
        }
        return DataTables::of($query)->toJson();
    }

    /*public function adminReport($type) {
        if ($type == "sales") {
            return $this->adminReport_sales();
        } else if ($type == "vip-users") {
            return view('admin.reports.vip_distributors');
        } else if ($type == "personally_enrolled_distributors") {
            return view('admin.reports.personal_enrollment');
        } else if ($type == "dist-by-country") {
            return view('admin.reports.distByCountry');
        } else {
            abort(404);
        }
    }

    private function adminReport_sales() {
        $d = array();
        return view('admin.reports.sales')->with($d);
    }

    public function showVIPReport() {
        $d = array();
        $d['recs'] = DB::table('orderItem as oi')
            ->select('u.firstname', 'u.lastname', 'u.email', 'u.username', 'u.distid')
            ->join('orders as o', 'oi.orderid', '=', 'o.id')
            ->join('users as u', 'u.id', '=', 'o.userid')
            ->where('oi.productid', \App\Product::ID_EB_FIRST_CLASS)
            ->get();
        return view('admin.reports.vip')->with($d);
    }*/

    public function getDistributorsByCountryDataTable()
    {
        $query = DB::table('vdistributorsbycountry');
        return DataTables::of($query)->toJson();
    }

    public function exportDistByCountry($sort_col, $asc_desc, $q = null)
    {
        $headers = array(
            "Content-type" => "text/csv",
            "Content-Disposition" => "attachment; filename=Distributors by Country Report.csv",
            "Pragma" => "no-cache",
            "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
            "Expires" => "0"
        );
        $recs = DB::table('vdistributorsbycountry')
            ->select('country', 'users_count');
        if ($q != null) {
            $recs->where(function ($sq) use ($q) {
                $sq->where('country', 'ilike', "%" . $q . "%")
                    ->orWhere('users_count', 'ilike', "%" . $q . "%");
            });
        }

        $recs->orderBy($sort_col, $asc_desc);
        $recs = $recs->get();
        $columns = array('Country', 'Distributors');
        $callback = function () use ($recs, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            foreach ($recs as $rec) {
                fputcsv($file, array($rec->country, $rec->users_count));
            }
            fclose($file);
        };
        return response()->stream($callback, 200, $headers);
    }

    public function getPersonallyEnrolledDistributorsDataTable()
    {
        $query = DB::table('vpersonalenrolleddistributors')
            ->where('usertype', \App\UserType::TYPE_DISTRIBUTOR)
            ->where('is_tv_user', 0);
        return DataTables::of($query)->toJson();
    }

    public function exportPersonallyEnrolledDistributors($sort_col, $asc_desc, $q = null)
    {
        $headers = array(
            "Content-type" => "text/csv",
            "Content-Disposition" => "attachment; filename=Personally Enrolled Report.csv",
            "Pragma" => "no-cache",
            "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
            "Expires" => "0"
        );

        if ($q == null) {
            $recs = DB::table('vpersonalenrolleddistributors')
                ->select('distid', 'firstname', 'lastname', 'sponsees')
                ->where('usertype', \App\UserType::TYPE_DISTRIBUTOR)
                ->where('is_tv_user', 0)
                ->orderBy($sort_col, $asc_desc)
                ->get();
        } else {
            $recs = DB::table('vpersonalenrolleddistributors')
                ->select('distid', 'firstname', 'lastname', 'sponsees')
                ->where('usertype', \App\UserType::TYPE_DISTRIBUTOR)
                ->where('is_tv_user', 0)
                ->where(function ($sq) use ($q) {
                    $sq->where('firstname', 'ilike', "%" . $q . "%")
                        ->orWhere('lastname', 'ilike', "%" . $q . "%")
                        ->orWhere('distid', 'ilike', "%" . $q . "%");
                })
                ->orderBy($sort_col, $asc_desc)
                ->get();
        }


        $columns = array('Dist ID', 'First Name', 'Last Name', 'Personally Enrolled Distributors');

        $callback = function () use ($recs, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            foreach ($recs as $rec) {
                fputcsv($file, array($rec->distid, $rec->firstname, $rec->lastname, $rec->sponsees));
            }
            fclose($file);
        };
        return response()->stream($callback, 200, $headers);
    }

    /*public function viewPersonallyEnrolledDistributors($distid) {
        return view('admin.reports.personally_enrolled_detail')->with('distid', $distid);
    }*/

    /**
     * Get Enrolled Intern
     *
     * @bodyParam distid int required The distid. Example: 9
     *
     */
    public function getEnrolledInternDataTable(Request $request)
    {
        //->select('id', 'firstname', 'lastname', 'email', 'account_status', 'username')
        $query = DB::table('users')
            ->select('id', 'firstname', 'lastname', 'email', 'account_status', 'username', 'basic_info_updated', 'distid', 'current_product_id')
            ->where('usertype', \App\UserType::TYPE_DISTRIBUTOR)
            ->where('sponsorid', $request->get('distid'));
        return DataTables::of($query)->toJson();
    }

    public function getVipDistributorsDataTable()
    {
        $query = DB::table('vusersandaddresses')
            ->where('usertype', \App\UserType::TYPE_DISTRIBUTOR)
            ->where('current_product_id', \App\Product::ID_EB_FIRST_CLASS);
        return DataTables::of($query)->toJson();
    }

    public function exportVipDistData($sort_col, $asc_desc, $q = null)
    {
        $headers = array(
            "Content-type" => "text/csv",
            "Content-Disposition" => "attachment; filename=VIP Ambassador.csv",
            "Pragma" => "no-cache",
            "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
            "Expires" => "0"
        );

        if ($q == null) {
            $recs = DB::table('vusersandaddresses')
                ->select('distid', 'firstname', 'lastname', 'phonenumber', 'email', 'username', 'countrycode', 'stateprov', 'sponsorid', 'account_status', 'current_product_id', 'created_dt')
                ->where('usertype', \App\UserType::TYPE_DISTRIBUTOR)
                ->where('current_product_id', \App\Product::ID_EB_FIRST_CLASS)
                ->orderBy($sort_col, $asc_desc)
                ->get();
        } else {
            $recs = DB::table('vusersandaddresses')
                ->select('distid', 'firstname', 'lastname', 'phonenumber', 'email', 'username', 'countrycode', 'stateprov', 'sponsorid', 'account_status', 'current_product_id', 'created_dt')
                ->where('usertype', \App\UserType::TYPE_DISTRIBUTOR)
                ->where('current_product_id', \App\Product::ID_EB_FIRST_CLASS)
                ->where(function ($sq) use ($q) {
                    $sq->where('firstname', 'ilike', "%" . $q . "%")
                        ->orWhere('lastname', 'ilike', "%" . $q . "%")
                        ->orWhere('email', 'ilike', "%" . $q . "%")
                        ->orWhere('account_status', 'ilike', "%" . $q . "%")
                        ->orWhere('distid', 'ilike', "%" . $q . "%")
                        ->orWhere('sponsorid', 'ilike', "%" . $q . "%")
                        ->orWhere('phonenumber', 'ilike', "%" . $q . "%")
                        ->orWhere('countrycode', 'ilike', "%" . $q . "%")
                        ->orWhere('stateprov', 'ilike', "%" . $q . "%")
                        ->orWhere('account_status', 'ilike', "%" . $q . "%")
                        ->orWhere('current_product_id', 'ilike', "%" . $q . "%")
                        ->orWhere('created_dt', 'ilike', "%" . $q . "%")
                        ->orWhere('username', 'ilike', "%" . $q . "%");
                })
                ->orderBy($sort_col, $asc_desc)
                ->get();
        }


        $columns = array('Dist ID', 'First Name', 'Last Name', 'Phone', 'Email', 'Username', 'Country', 'State', 'Sponsor ID', 'Account Status', 'Enrollment Pack', 'Joined Date',);

        $callback = function () use ($recs, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            foreach ($recs as $rec) {
                fputcsv($file, array($rec->distid, $rec->firstname, $rec->lastname, $rec->phonenumber, $rec->email, $rec->username, $rec->countrycode, $rec->stateprov, $rec->sponsorid, $rec->account_status, $rec->current_product_id, $rec->created_dt));
            }
            fclose($file);
        };
        return response()->stream($callback, 200, $headers);
    }

    /*public function distributorsByLevel() {
        $query = DB::select("select level,count(1)as count from enrolment_tree('" . Auth::user()->distid . "') group by level order by level");
//        $query = DB::select("select created_dt,tree.*,binary_plan.direction from (select * from enrolment_tree('" . Auth::user()->distid . "'))tree, users
//join binary_plan on users.id = binary_plan.user_id where tree.distid = users.distid;");
        return view('affiliate.reports.distributors_by_level')->with(array('recs' => $query));
    }

    public function distributorsByLevelDetail($level) {
        $d = array();
        $d["level"] = $level;
        $d["sponserid"] = Auth::user()->distid;
        return view('affiliate.reports.distributors_by_level_detail')->with($d);
    }*/

    public function orgDrillDown($distid)
    {
        $d = array();
        $rec = DB::table('users')
            ->select('firstname', 'lastname')
            ->where('distid', $distid)
            ->first();
        $d["distid"] = $distid;
        $d["name"] = $rec->firstname . " " . $rec->lastname;

        $v = (string) view('affiliate.reports.distributors_by_level_detail')->with($d);
        return response()->json(['error' => 0, 'v' => $v]);
    }


    /**
     * Get Distritutors By Level of Detail
     *
     * @bodyParam level int required The level. Example: 9
     *
     */
    public function getDistributorsByLevelDetailDataTable(Request $request)
    {
        //        $query = DB::select("select * from enrolment_tree('" . Auth::user()->distid . "') where level=" . $level);
        $query = DB::select("select created_dt,tree.*,binary_plan.direction from (select * from enrolment_tree('" . Auth::user()->distid . "'))tree, users
        join binary_plan on users.id = binary_plan.user_id where tree.distid = users.distid and tree.level = " . $request->get('level'));
        return DataTables::of($query)->toJson();
    }

    /**
     * Get Organization DrillDown Data
     *
     * @bodyParam distid int required The distid. Example: 9
     *
     */
    public function getOrgDrillDownDataTable(Request $request)
    {
        $query = DB::select("select * from enrolment_tree('" . $request->get('distid') . "') where distid <> '" . $request->get('distid') . "'");
        return DataTables::of($query)->toJson();
    }

    public function invoice()
    {
        $orders = Order::getInvoiceByUser(Auth::user()->id);
        //return view('affiliate.reports.invoice')->with(['orders' => $orders]);

        $this->setResponse(['orders' => $orders]);
        $this->setResponseCode(200);
        return $this->showResponse();
    }


    public function getOrderCompleted()
    {
        $query = DB::select(
            "select orders.id as order_id, * from orders inner join statuscode on orders.statuscode = statuscode.id  where userid = '" . Auth::user()->id . "'"
        );
        return DataTables::of($query)->toJson();
    }

    public function gerOrderPending()
    {
        $query = DB::select(
            "select pre_orders.id as order_id, * from pre_orders inner join statuscode on pre_orders.statuscode = statuscode.id where userid = '" . Auth::user()->id . "'"
        );
        return DataTables::of($query)->toJson();
    }

    public function viewInvoice($order_id)
    {
        $response['order'] = Order::getUserOrder($order_id);

        $orderConversion = OrderConversion::getOrderConversionById($order_id);
        $response['display_amount']  = ($orderConversion) ? $orderConversion->display_amount : '';

        if (!$response['order']) {
            $this->setResponseCode(404);
            $this->setMessage("Order Not found");
            return $this->showResponse();
            exit(0);
        }

        if ($response['order']->coupon_code) {
            $response['coupon'] = DiscountCoupon::find($response['order']->coupon_code);
        }

        $order_item = OrderItem::where('orderid', $order_id)->get();

        $response['order_item'] = array();
        for ($i = 0; $i < count($order_item); $i++) {
            $response['order_item'][$i] = $order_item[$i];
            $response['order_item'][$i]['productname']  = Product::getProductNameForInvoice($order_item[$i]);
        }

        $response['address'] = Address::getBillingAddress(Auth::user()->id);

        $this->setResponseCode(200);
        $this->setResponse($response);
        return $this->showResponse();
    }

    public function viewPreOrder($preOrder_id)
    {
        $response['order'] = PreOrder::getUserPreOrder($preOrder_id);
        $orderConversion = OrderConversion::getOrderConversionById($order_id);
        $response['display_amount']  = ($orderConversion) ? $orderConversion->display_amount : '';
        if (!$response['order']) {
            $this->setResponseCode(404);
            $this->setMessage("Order Not found");
            return $this->showResponse();
            exit(0);
        }

        $order_item = PreOrderItem::where('orderid', $preOrder_id)->get();
        $response['order_item'] = array();
        for ($i = 0; $i < count($order_item); $i++) {
            $response['order_item'][$i] = $order_item[$i];
            $response['order_item'][$i]['productname']  = Product::getProductNameForInvoice($order_item[$i]);
        }

        //$response['order_item']                 = $order_item;

        $response['address']                    = Address::getBillingAddress(Auth::user()->id);

        $this->setResponseCode(200);
        $this->setResponse($response);
        return $this->showResponse();
    }


    /*public function enrollmentsByDateList($from = null, $to = null) {
        $d = array();
        $d["from"] = $from;
        $d["to"] = $to;
        return view("admin.reports.enrollments_by_date")->with($d);
    }*/

    public function enrollmentsByDateListForCustomers(Request $request)
    {
        $recs = DB::table('customers')
            ->select('*');
        if ($request->from && $request->to) {
            $recs->where(DB::raw("date(created_date)"), "<=", $request->to);
            $recs->where(DB::raw("date(created_date)"), ">=", $request->from);
        }
        $recs = $recs->get();
        return response()->json(['error' => '0', 'msg' => 'Total customers - ' . count($recs)]);
    }

    public function getEnrollmentsByDateDataTable()
    {
        $req = request();
        if ($req->from != "" && $req->to != "") {
            $query = DB::table('vusersandaddresses')
                ->select('id', 'distid', 'firstname', 'lastname', 'email', 'phonenumber', 'username', 'countrycode', 'stateprov', 'sponsorid', 'account_status', 'current_product_id', 'basic_info_updated', 'created_dt', 'entered_by')
                ->where('usertype', \App\UserType::TYPE_DISTRIBUTOR)
                ->where('account_status', "<>", \App\User::ACC_STATUS_TERMINATED)
                ->whereDate('created_dt', '>=', $req->from)
                ->whereDate('created_dt', '<=', $req->to);
        } else {
            $query = DB::table('vusersandaddresses')
                ->select('id', 'distid', 'firstname', 'lastname', 'email', 'phonenumber', 'username', 'countrycode', 'stateprov', 'sponsorid', 'account_status', 'current_product_id', 'basic_info_updated', 'created_dt', 'entered_by')
                ->where('usertype', \App\UserType::TYPE_DISTRIBUTOR)
                ->where('account_status', "<>", \App\User::ACC_STATUS_TERMINATED);
        }
        return DataTables::of($query)->toJson();
    }

    public function exportEnrollmentsByDate($sort_col, $asc_desc, $q = null)
    {
        $req = request();
        $headers = array(
            "Content-type" => "text/csv",
            "Content-Disposition" => "attachment; filename=Enrollments by Date Report.csv",
            "Pragma" => "no-cache",
            "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
            "Expires" => "0"
        );
        $recs = DB::table('vusersandaddresses')
            ->join('products', 'vusersandaddresses.current_product_id', '=', 'products.id')
            ->select('vusersandaddresses.id', 'distid', 'firstname', 'lastname', 'email', 'phonenumber', 'username', 'countrycode', 'stateprov', 'sponsorid', 'account_status', 'current_product_id', 'basic_info_updated', 'created_dt', 'entered_by', 'productname')
            ->where('usertype', \App\UserType::TYPE_DISTRIBUTOR)
            ->where('account_status', "<>", \App\User::ACC_STATUS_TERMINATED);
        if ($q != null) {
            $recs->where(function ($sq) use ($q) {
                $sq->where('distid', 'ilike', "%" . $q . "%")
                    ->orWhere('firstname', 'ilike', "%" . $q . "%")
                    ->orWhere('lastname', 'ilike', "%" . $q . "%")
                    ->orWhere('email', 'ilike', "%" . $q . "%")
                    ->orWhere('username', 'ilike', "%" . $q . "%")
                    ->orWhere('countrycode', 'ilike', "%" . $q . "%")
                    ->orWhere('stateprov', 'ilike', "%" . $q . "%")
                    ->orWhere('sponsorid', 'ilike', "%" . $q . "%")
                    ->orWhere('account_status', 'ilike', "%" . $q . "%")
                    ->orWhere('current_product_id', 'ilike', "%" . $q . "%")
                    ->orWhere('basic_info_updated', 'ilike', "%" . $q . "%")
                    ->orWhere('created_dt', 'ilike', "%" . $q . "%")
                    ->orWhere('entered_by', 'ilike', "%" . $q . "%")
                    ->orWhere('phonenumber', 'ilike', "%" . $q . "%");
            });
        }
        if ($req->d_from != "") {
            $recs->whereDate('created_dt', '>=', $req->d_from);
        }
        if ($req->d_to != "") {
            $recs->whereDate('created_dt', '<=', $req->d_to);
        }
        $recs->orderBy($sort_col, $asc_desc);
        $recs = $recs->get();
        $columns = array('Dist ID', 'First Name', 'Last Name', 'Username', 'Created Date', 'Country Code', 'Sponsor ID', 'Enrollment Pack', 'Account Status', 'Email', 'Phone', 'State/Province');
        $callback = function () use ($recs, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            foreach ($recs as $rec) {
                fputcsv($file, array(
                    $rec->distid, $rec->firstname, $rec->lastname, $rec->username, $rec->created_dt,
                    $rec->countrycode, $rec->sponsorid, $rec->productname, $rec->account_status, $rec->email, $rec->phonenumber, $rec->stateprov
                ));
            }
            fclose($file);
        };
        return response()->stream($callback, 200, $headers);
    }

    private function validateInput()
    {
        $req = request();
        $validator = Validator::make($req->all(), [
            'from' => 'required|date',
            'to' => 'required|date',
        ], [
            'from.required' => 'From date is required',
            'to.required' => 'To date is required',
        ]);

        $msg = "";
        if ($validator->fails()) {
            $valid = 0;
            $messages = $validator->messages();
            foreach ($messages->all() as $m) {
                $msg .= "<div> - " . $m . "</div>";
            }
        } else {
            $valid = 1;
            if ($req->from > $req->to) {
                $valid = 0;
                $msg = "Invalid date range";
            }
        }
        $res = array();
        $res["valid"] = $valid;
        $res["msg"] = $msg;
        return $res;
    }

    /*public function distributorByRankList() {
        return view('admin.reports.distributor_by_rank');
    }*/

    public function getDistributorByRankDataTable()
    {
        $query = DB::table(DB::raw('get_usercount_by_highest_achievement()'))
            ->where('achieved_rank', '<>', 'Ambassador');
        return DataTables::of($query)->toJson();
    }

    public function exportDistributorByRank($sort_col, $asc_desc, $q = null)
    {
        $headers = array(
            "Content-type" => "text/csv",
            "Content-Disposition" => "attachment; filename=Distributors by Rank Report.csv",
            "Pragma" => "no-cache",
            "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
            "Expires" => "0"
        );
        $recs = DB::table(DB::raw('get_usercount_by_highest_achievement()'))
            ->where('achieved_rank', '<>', 'Ambassador');
        if ($q != null) {
            $recs->where(function ($sq) use ($q) {
                $sq->where('achieved_rank', 'ilike', "%" . $q . "%")
                    ->orWhere('total', 'ilike', "%" . $q . "%");
            });
        }

        $recs->orderBy($sort_col, $asc_desc);
        $recs = $recs->get();
        $columns = array('Rank', 'Distributors');
        $callback = function () use ($recs, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            foreach ($recs as $rec) {
                fputcsv($file, array($rec->achieved_rank, $rec->total));
            }
            fclose($file);
        };
        return response()->stream($callback, 200, $headers);
    }

    /*public function getDlgDistributorByRank($rank) {
        $d = array();
        $d["d_rank"] = $rank;
        return view("admin.reports.dlg_distributor_by_rank")->with($d);
    }*/

    public function getDistributorByRankDetailDataTable($rank)
    {
        $query = DB::table(DB::raw('get_users_by_highest_achievement()'))
            ->where('achieved_rank', '=', $rank);
        return DataTables::of($query)->toJson();
    }

    public function exportDistributorByRankDetail($sort_col, $asc_desc, $q = null)
    {
        $req = request();
        $headers = array(
            "Content-type" => "text/csv",
            "Content-Disposition" => "attachment; filename=Distributors by Rank Report.csv",
            "Pragma" => "no-cache",
            "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
            "Expires" => "0"
        );
        $recs = DB::table(DB::raw('get_users_by_highest_achievement()'));
        if ($q != null) {
            $recs->where(function ($sq) use ($q) {
                $sq->where('distid', 'ilike', "%" . $q . "%")
                    ->orWhere('firstname', 'ilike', "%" . $q . "%")
                    ->orWhere('lastname', 'ilike', "%" . $q . "%")
                    ->orWhere('created_dt', 'ilike', "%" . $q . "%");
            });
        }
        if ($req->rank) {
            $recs->where('achieved_rank', '=', $req->rank);
        }
        $recs->orderBy($sort_col, $asc_desc);
        $recs = $recs->get();
        $columns = array('Dist ID', 'First Name', 'Last Name', 'Created Date');
        $callback = function () use ($recs, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            foreach ($recs as $rec) {
                fputcsv($file, array($rec->distid, $rec->firstname, $rec->lastname, $rec->created_dt));
            }
            fclose($file);
        };
        return response()->stream($callback, 200, $headers);
    }

    /*public function highestAchievedRankList($from = null, $to = null) {
        $d = array();
        $d['from'] = $from;
        $d['to'] = $to;
        return view("admin.reports.highest_achieved_rank")->with($d);
    }*/

    public function getHighestAchievedRankDataTable()
    {
        $req = request();
        if ($req->from != "" && $req->to != "")
            $query = DB::select("select * from get_users_by_highest_achievement() where achieved_rank <> 'Ambassador' AND distid IS NOT null AND DATE(created_dt) >= '$req->from' AND DATE(created_dt) <= '$req->to'");
        else
            $query = DB::select('select * from get_users_by_highest_achievement() where achieved_rank <> \'Ambassador\' AND distid IS NOT null');
        return DataTables::of($query)->toJson();
    }

    /*public function salesReportList($from = null, $to = null) {
        $d = array();
        $d['from'] = $from;
        $d['to'] = $to;
        $total_amount = '';

        if ($from != null && $to != null) {
            $total_amount = DB::table('vorderuserspaymentmethods')
                ->select(DB::raw('SUM(ordersubtotal) AS amount'))
                ->whereDate('created_dt', '>=', $from)
                ->whereDate('created_dt', '<=', $to)->first();
        } else {
            $total_amount = DB::table('vorderuserspaymentmethods')
                ->select(DB::raw('SUM(ordersubtotal) AS amount'))->first();
        }
        $d['total_amount'] = $total_amount->amount;

        return view('admin.reports.sales_by_payment_method')->with($d);
    }*/

    public function getSalesByPaymentMethodDataTables()
    {
        $req = request();
        if ($req->from != "" && $req->to != "") {
            $query = DB::select(DB::raw("SELECT (case when pay_method_name is not null then pay_method_name else 'Coupen Code' end) as pay_method_name, SUM(ordersubtotal) AS amount
                FROM vorderuserspaymentmethods
                WHERE date(created_dt) >= :from
                AND date(created_dt) <= :to
                GROUP BY pay_method_name"), [':from' => $req->from, ':to' => $req->to,]);
        } else {
            $query = DB::select("SELECT (case when pay_method_name is not null then pay_method_name else 'Coupen Code' end) as pay_method_name, SUM(ordersubtotal) AS amount
                FROM vorderuserspaymentmethods
                GROUP BY pay_method_name");
        }

        return DataTables::of($query)->toJson();
    }

    public function exportSalesByPaymentMethod($sort_col, $asc_desc, $q = null)
    {
        $req = request();
        $headers = array(
            "Content-type" => "text/csv",
            "Content-Disposition" => "attachment; filename=Sales by Paymentmethod Report.csv",
            "Pragma" => "no-cache",
            "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
            "Expires" => "0"
        );
        $recs = DB::table('vorderuserspaymentmethods')
            ->select(DB::raw("(case when pay_method_name is not null then pay_method_name else 'Coupen Code' end) as pay_method_name"), DB::raw("SUM(ordersubtotal) AS amount"))
            ->groupBy('pay_method_name');
        if ($q != null) {
            $recs->where(function ($sq) use ($q) {
                $sq->where('pay_method_name', 'ilike', "%" . $q . "%");
            });
        }
        if ($req->d_from) {
            $recs->where(DB::raw("date(created_dt)"), ">=", $req->d_from);
        }
        if ($req->d_to) {
            $recs->where(DB::raw("date(created_dt)"), "<=", $req->d_to);
        }
        $recs->orderBy($sort_col, $asc_desc);
        $recs = $recs->get();
        $columns = array('Patment Method', 'Amount');
        $callback = function () use ($recs, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            foreach ($recs as $rec) {
                fputcsv($file, array($rec->pay_method_name, $rec->amount));
            }
            fclose($file);
        };
        return response()->stream($callback, 200, $headers);
    }

    /*public function idecideAndSor($from = null, $to = null) {
        $d = array();
        $d['from'] = $from;
        $d['to'] = $to;
        $idecide_total = '';
        $sor_total = '';

        if ($from != null && $to != null) {
            $idecide_total = DB::select("select count(1) as total from api_logs al, users u
                where al.endpoint='users/create'
                and al.user_id=u.id
                and lower(api) like '%idecide%'
                and u.account_status = 'APPROVED'
                and al.response like '%\"success\":true%'
                and created_date >= '$from'
                and created_date <= '$to'");
            $sor_total = DB::select("select count(1) as total from api_logs al, users u
                where al.endpoint='clubmembership/createdefault'
                and al.user_id=u.id
                and lower(api) like '%sor%'
                and u.account_status = 'APPROVED'
                and al.response like '%\"ResultType\":\"success\"%'
                and created_date >= '$from'
                and created_date <= '$to'");
        } else {
            $idecide_total = DB::select("select count(1) as total from api_logs al, users u
                where al.endpoint='users/create'
                and al.user_id=u.id
                and lower(api) like '%idecide%'
                and u.account_status = 'APPROVED'
                and al.response like '%\"success\":true%'");
            $sor_total = DB::select("select count(1) as total from api_logs al, users u
                where al.endpoint='clubmembership/createdefault'
                and al.user_id=u.id
                and lower(api) like '%sor%'
                and u.account_status = 'APPROVED'
                and al.response like '%\"ResultType\":\"success\"%'");
        }
        $d['idecide_total'] = $idecide_total[0]->total;
        $d['sor_total'] = $sor_total[0]->total;

        return view('admin.reports.idecide_sor')->with($d);
    }*/

    public function exportIDecideOrSor($q = null)
    {
        $req = request();
        $headers = array(
            "Content-type" => "text/csv",
            "Content-Disposition" => "attachment; filename=iDecide or SOR Report.csv",
            "Pragma" => "no-cache",
            "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
            "Expires" => "0"
        );
        $sor = DB::table('api_logs as al')
            ->select(DB::raw('count(1) as total'))
            ->join('users as u', 'al.user_id', '=', 'u.id')
            ->where(DB::raw('lower(api)'), 'like', '%sor%')
            ->where('u.account_status', '=', 'APPROVED')
            ->where('al.endpoint', '=', 'clubmembership/createdefault')
            ->where('al.response', 'like', '%\"ResultType\":\"success\"%');
        if ($req->d_from) {
            $sor->where('created_date', '>=', $req->d_from);
        }
        if ($req->d_to) {
            $sor->where('created_date', '<=', $req->d_to);
        }
        $sor = $sor->get();
        $idecide = DB::table('api_logs as al')
            ->select(DB::raw('count(1) as total'))
            ->join('users as u', 'al.user_id', '=', 'u.id')
            ->where(DB::raw('lower(api)'), 'like', '%idecide%')
            ->where('u.account_status', '=', 'APPROVED')
            ->where('al.endpoint', '=', 'users/create')
            ->where('al.response', 'like', '%\"success\":true%');
        if ($req->d_from) {
            $idecide->where('created_date', '>=', $req->d_from);
        }
        if ($req->d_to) {
            $idecide->where('created_date', '<=', $req->d_to);
        }
        $idecide = $idecide->get();
        $recs = ["iDecide" => $idecide[0]->total, "SOR" => $sor[0]->total];
        $columns = array('Account', 'Total');
        $callback = function () use ($recs, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            foreach ($recs as $ac => $rec) {
                fputcsv($file, array($ac, $rec));
            }
            fclose($file);
        };
        return response()->stream($callback, 200, $headers);
    }

    /*public function subcsriptionReport($from = null, $to = null) {
        $d = array();
        $d['from'] = $from;
        $d['to'] = $to;
        return view('admin.reports.subscription_report')->with($d);
    }*/

    public function getSubcsriptionReportDataTable()
    {
        $req = request();
        if ($req->from != "" && $req->to != "") {
            $query = DB::table(DB::raw("get_payment_status_summary()"))
                ->select(DB::raw("tran_date, sum(success) as total_successes, sum(case when success = 0 then 1 end) as total_fails"))
                ->whereBetween('tran_date', array($req->from, $req->to))
                ->groupBy("tran_date");
        } else {
            $query = DB::table(DB::raw("get_payment_status_summary()"))
                ->select(DB::raw("tran_date, sum(success) as total_successes, sum(case when success = 0 then 1 end) as total_fails"))
                ->groupBy("tran_date");
        }

        return DataTables::of($query)->toJson();
    }

    public function exportSubscriptionReport($sort_col, $asc_desc, $q = null)
    {
        $req = request();
        $headers = array(
            "Content-type" => "text/csv",
            "Content-Disposition" => "attachment; filename=Subscription Report.csv",
            "Pragma" => "no-cache",
            "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
            "Expires" => "0"
        );
        $recs = DB::table(DB::raw("get_payment_status_summary()"))
            ->select(DB::raw("tran_date, sum(success) as total_successes, sum(case when success = 0 then 1 end) as total_fails"))
            ->groupBy("tran_date");
        if ($q != null) {
            $recs->where(function ($sq) use ($q) {
                $sq->where('tran_date', 'ilike', "%" . $q . "%");
            });
        }
        if ($req->d_from != "" && $req->d_to != "") {
            $recs->whereBetween('tran_date', array($req->d_from, $req->d_to));
        }
        $recs->orderBy($sort_col, $asc_desc);
        $recs = $recs->get();
        $columns = array('Date', 'Success', 'Fail');
        $callback = function () use ($recs, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            foreach ($recs as $rec) {
                fputcsv($file, array($rec->tran_date, $rec->total_successes, $rec->total_fails));
            }
            fclose($file);
        };
        return response()->stream($callback, 200, $headers);
    }

    /*public function subscriptionByPaymentMethod($from = null, $to = null) {
        $d = array();
        $d['from'] = $from;
        $d['to'] = $to;
        return view('admin.reports.subscription_by_payment_method_report')->with($d);
    }*/

    public function getSubscriptionByPaymentMethodDataTable()
    {
        $req = request();
        if ($req->from != "" && $req->to != "") {
            $query = DB::table(DB::raw("get_payment_success_summary()"))
                ->select(DB::raw("tran_date,
                        sum(case when method='1' then total end) As credit_card,
                        sum(case when method='2' then total end) As admin,
                        sum(case when method='3' then total end) As ewallet,
                        sum(case when method='4' then total end) As bitpay,
                        sum(case when method='5' then total end) As skrill,
                        sum(case when method='6' then total end) As secondary_cc"))
                ->whereBetween('tran_date', array($req->from, $req->to))
                ->groupBy("tran_date");
        } else {
            $query = DB::table(DB::raw("get_payment_success_summary()"))
                ->select(DB::raw("tran_date,
                        sum(case when method='1' then total end) As credit_card,
                        sum(case when method='2' then total end) As admin,
                        sum(case when method='3' then total end) As ewallet,
                        sum(case when method='4' then total end) As bitpay,
                        sum(case when method='5' then total end) As skrill,
                        sum(case when method='6' then total end) As secondary_cc"))
                ->groupBy("tran_date");
        }

        return DataTables::of($query)->toJson();
    }

    public function exportSubscriptionByPaymentMethod($sort_col, $asc_desc, $q = null)
    {
        $req = request();
        $headers = array(
            "Content-type" => "text/csv",
            "Content-Disposition" => "attachment; filename=Subscription by Payment Method Report.csv",
            "Pragma" => "no-cache",
            "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
            "Expires" => "0"
        );
        $recs = DB::table(DB::raw("get_payment_success_summary()"))
            ->select(DB::raw("tran_date,
                        sum(case when method='1' then total end) As credit_card,
                        sum(case when method='2' then total end) As admin,
                        sum(case when method='3' then total end) As ewallet,
                        sum(case when method='4' then total end) As bitpay,
                        sum(case when method='5' then total end) As skrill,
                        sum(case when method='6' then total end) As secondary_cc"))
            ->groupBy("tran_date");
        if ($q != null) {
            $recs->where(function ($sq) use ($q) {
                $sq->where('tran_date', 'ilike', "%" . $q . "%");
            });
        }
        if ($req->d_from != "" && $req->d_to != "") {
            $recs->whereBetween('tran_date', array($req->d_from, $req->d_to));
        }
        $recs->orderBy($sort_col, $asc_desc);
        $recs = $recs->get();
        $columns = array('Date', 'Credit Card', 'E-Wallet', 'Admin', 'Bitpay', 'Skrill', 'Secondary CC');
        $callback = function () use ($recs, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            foreach ($recs as $rec) {
                fputcsv($file, array($rec->tran_date, $rec->credit_card, $rec->ewallet, $rec->admin, $rec->bitpay, $rec->skrill, $rec->secondary_cc));
            }
            fclose($file);
        };
        return response()->stream($callback, 200, $headers);
    }

    /*public function listSapphires() {
        $d = array();
        $countryList = DB::table(DB::raw("get_users_by_highest_achievement() as ha"))
            ->select('c.country', 'c.countrycode')
            ->join('addresses as a', 'a.userid', '=', 'ha.user_id')
            ->join('country as c', 'c.countrycode', '=', 'a.countrycode')
            ->where('ha.achieved_rank', '=', 'Sapphire')
            ->where('a.primary', '=', '1')
            ->groupBy('c.country', 'c.countrycode')
            ->get();
        $d['countryList'] = $countryList;
        return view('admin.reports.sapphires_by_country')->with($d);
    }*/

    public function getSapphiresDataTable()
    {
        $query = "";
        $req = request();
        if ($req->country_code != "") {
            $query = DB::table(DB::raw("get_users_by_highest_achievement() as ha"))
                ->select('ha.distid', 'ha.firstname', 'ha.lastname', 'c.country', 'ha.email', 'phonenumber')
                ->join('addresses as a', 'a.userid', '=', 'ha.user_id')
                ->join('country as c', 'c.countrycode', '=', 'a.countrycode')
                ->where('ha.achieved_rank', '=', 'Sapphire')
                ->where('a.primary', '=', '1')
                ->where('c.countrycode', '=', $req->country_code)
                ->whereNotIn('distid', ['TSA0707550', 'TSA5138270', 'TSA9834283']);
        } else {
            $query = DB::table(DB::raw("get_users_by_highest_achievement() as ha"))
                ->join('addresses as a', 'a.userid', '=', 'ha.user_id')
                ->join('country as c', 'c.countrycode', '=', 'a.countrycode')
                ->where('ha.achieved_rank', '=', 'Sapphire')
                ->where('a.primary', '=', '1')
                ->whereNotIn('distid', ['TSA0707550', 'TSA5138270', 'TSA9834283']);
        }

        return DataTables::of($query)->toJson();
    }

    public function exportSapphiresByCountry($sort_col, $asc_desc, $q = null)
    {
        $req = request();
        $headers = array(
            "Content-type" => "text/csv",
            "Content-Disposition" => "attachment; filename=All Sapphires By Country.csv",
            "Pragma" => "no-cache",
            "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
            "Expires" => "0"
        );
        $recs = DB::table(DB::raw("get_users_by_highest_achievement() as ha"))
            ->join('addresses as a', 'a.userid', '=', 'ha.user_id')
            ->join('country as c', 'c.countrycode', '=', 'a.countrycode')
            ->where('ha.achieved_rank', '=', 'Sapphire')
            ->where('a.primary', '=', '1')
            ->select('ha.distid', 'ha.firstname', 'ha.lastname', 'c.country', 'ha.email', 'phonenumber');
        if ($q != null) {
            $recs->where(function ($sq) use ($q) {
                $sq->where('ha.distid', 'ilike', "%" . $q . "%")
                    ->orWhere('ha.firstname', 'ilike', "%" . $q . "%")
                    ->orWhere('ha.lastname', 'ilike', "%" . $q . "%")
                    ->orWhere('c.country', 'ilike', "%" . $q . "%")
                    ->orWhere('ha.email', 'ilike', "%" . $q . "%")
                    ->orWhere('phonenumber', 'ilike', "%" . $q . "%");
            });
        }
        if ($req->country_code != "") {
            $recs->where('c.countrycode', '=', $req->country_code);
        }
        $recs->orderBy($sort_col, $asc_desc)
            ->whereNotIn('distid', ['TSA0707550', 'TSA5138270', 'TSA9834283']);
        $recs = $recs->get();
        $columns = array('Dist ID', 'First Name', 'Last Name', 'Country', 'Email', 'Phone');
        $callback = function () use ($recs, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            foreach ($recs as $rec) {
                fputcsv($file, array($rec->distid, $rec->firstname, $rec->lastname, $rec->country, $rec->email, $rec->phonenumber));
            }
            fclose($file);
        };
        return response()->stream($callback, 200, $headers);
    }

    /*public function listDiamonds() {
       $d = array();
        $countryList = DB::table(DB::raw("get_users_by_highest_achievement() as ha"))
            ->select('c.country', 'c.countrycode')
            ->join('addresses as a', 'a.userid', '=', 'ha.user_id')
            ->join('country as c', 'c.countrycode', '=', 'a.countrycode')
            ->where('ha.achieved_rank', '=', 'Diamond')
            ->where('a.primary', '=', '1')
            ->groupBy('c.country', 'c.countrycode')
            ->get();
        $d['countryList'] = $countryList;
        return view('admin.reports.diamonds_by_country')->with($d);
    }*/

    public function getDiamondsDataTable()
    {
        $query = "";
        $req = request();
        if ($req->country_code != "") {
            $query = DB::table(DB::raw("get_users_by_highest_achievement() as ha"))
                ->select('ha.distid', 'ha.firstname', 'ha.lastname', 'c.country', 'ha.email', 'phonenumber')
                ->join('addresses as a', 'a.userid', '=', 'ha.user_id')
                ->join('country as c', 'c.countrycode', '=', 'a.countrycode')
                ->where('ha.achieved_rank', '=', 'Diamond')
                ->where('c.countrycode', '=', $req->country_code)
                ->where('a.primary', '=', '1')
                ->whereNotIn('distid', ['TSA0707550', 'TSA5138270', 'TSA9834283']);
        } else {
            $query = DB::table(DB::raw("get_users_by_highest_achievement() as ha"))
                ->join('addresses as a', 'a.userid', '=', 'ha.user_id')
                ->join('country as c', 'c.countrycode', '=', 'a.countrycode')
                ->where('ha.achieved_rank', '=', 'Diamond')
                ->where('a.primary', '=', '1')
                ->whereNotIn('distid', ['TSA0707550', 'TSA5138270', 'TSA9834283']);
        }

        return DataTables::of($query)->toJson();
    }

    public function exportDiamondByCountry($sort_col, $asc_desc, $q = null)
    {
        $req = request();
        $headers = array(
            "Content-type" => "text/csv",
            "Content-Disposition" => "attachment; filename=All Diamond By Country.csv",
            "Pragma" => "no-cache",
            "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
            "Expires" => "0"
        );
        $recs = DB::table(DB::raw("get_users_by_highest_achievement() as ha"))
            ->join('addresses as a', 'a.userid', '=', 'ha.user_id')
            ->join('country as c', 'c.countrycode', '=', 'a.countrycode')
            ->where('ha.achieved_rank', '=', 'Diamond')
            ->where('a.primary', '=', '1')
            ->select('ha.distid', 'ha.firstname', 'ha.lastname', 'c.country', 'ha.email', 'phonenumber');
        if ($q != null) {
            $recs->where(function ($sq) use ($q) {
                $sq->where('ha.distid', 'ilike', "%" . $q . "%")
                    ->orWhere('ha.firstname', 'ilike', "%" . $q . "%")
                    ->orWhere('ha.lastname', 'ilike', "%" . $q . "%")
                    ->orWhere('c.country', 'ilike', "%" . $q . "%")
                    ->orWhere('ha.email', 'ilike', "%" . $q . "%")
                    ->orWhere('phonenumber', 'ilike', "%" . $q . "%");
            });
        }
        if ($req->country_code != "") {
            $recs->where('c.countrycode', '=', $req->country_code);
        }
        $recs->orderBy($sort_col, $asc_desc)
            ->whereNotIn('distid', ['TSA0707550', 'TSA5138270', 'TSA9834283']);
        $recs = $recs->get();
        $columns = array('Dist ID', 'First Name', 'Last Name', 'Country', 'Email', 'Phone');
        $callback = function () use ($recs, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            foreach ($recs as $rec) {
                fputcsv($file, array($rec->distid, $rec->firstname, $rec->lastname, $rec->country, $rec->email, $rec->phonenumber));
            }
            fclose($file);
        };
        return response()->stream($callback, 200, $headers);
    }

    /*public function listMonthlyEarnings() {
        $d = array();
        $years = DB::table("ewallet_transactions")
            ->select(DB::raw("extract(year from created_at) as year"))
            ->groupBy('year')
            ->orderBy('year', 'asc')->get();
        $d['order_years'] = $years;
        $d['order_months'] = array(
            1 => "January",
            2 => "February",
            3 => "March",
            4 => "April",
            5 => "May",
            6 => "June",
            7 => "July",
            8 => "August",
            9 => "September",
            10 => "October",
            11 => "November",
            12 => "December",
        );
        return view('admin.reports.monthly_income_earnings')->with($d);
    }*/

    public function getMonthlyEarningsDataTable()
    {
        $query = "";
        $req = request();
        if ($req->month != "" && $req->year != "") {
            $query = DB::table('v_monthly_income_earnings')
                ->where('month', $req->month)
                ->where('year', $req->year)
                ->whereNotIn('distid', ['TSA0707550', 'TSA5138270', 'TSA9834283']);
        } else {
            $query = DB::table('v_monthly_income_earnings')
                ->whereNotIn('distid', ['TSA0707550', 'TSA5138270', 'TSA9834283']);
        }
        return DataTables::of($query)->toJson();
    }

    public function exportMonthlyEarning($sort_col, $asc_desc, $q = null)
    {
        $req = request();
        $headers = array(
            "Content-type" => "text/csv",
            "Content-Disposition" => "attachment; filename=Export Monthly Income Earnings Report.csv",
            "Pragma" => "no-cache",
            "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
            "Expires" => "0"
        );
        $recs = DB::table('v_monthly_income_earnings')
            ->whereNotIn('distid', ['TSA0707550', 'TSA5138270', 'TSA9834283']);
        if ($q != null) {
            $recs->where(function ($sq) use ($q) {
                $sq->where('distid', 'ilike', "%" . $q . "%")
                    ->orWhere('firstname', 'ilike', "%" . $q . "%")
                    ->orWhere('lastname', 'ilike', "%" . $q . "%")
                    ->orWhere('monthly_total_amount', 'ilike', "%" . $q . "%")
                    ->orWhere('total_amount', 'ilike', "%" . $q . "%");
            });
        }
        if ($req->month != "" && $req->year != "") {
            $recs->where('month', $req->month)
                ->where('year', $req->year);
        }
        $recs->orderBy($sort_col, $asc_desc);
        $recs = $recs->get();
        $columns = array('Dist ID', 'First Name', 'Monthly Amount', 'Total Amount');
        $callback = function () use ($recs, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            foreach ($recs as $rec) {
                fputcsv($file, array($rec->distid, $rec->firstname, $rec->lastname, $rec->monthly_total_amount, $rec->total_amount));
            }
            fclose($file);
        };
        return response()->stream($callback, 200, $headers);
    }

    public function listMonthlyTopRecruiters()
    {
        $d = array();
        $years = DB::table('users')
            ->select(DB::raw("date_part('year', created_dt) as year"))
            ->where('usertype', '=', '2')
            ->whereNotNull('created_dt')
            ->groupBy(DB::raw("date_part('year', created_dt)"))
            ->orderBy('year', 'asc')
            ->get();
        $d['order_years'] = $years;
        $d['order_months'] = array(
            1 => "January",
            2 => "February",
            3 => "March",
            4 => "April",
            5 => "May",
            6 => "June",
            7 => "July",
            8 => "August",
            9 => "September",
            10 => "October",
            11 => "November",
            12 => "December",
        );
        return view('admin.reports.monthly_top_recruiters')->with($d);
    }

    public function getMonthlyTopRecruitersDataTable()
    {
        $query = "";
        $req = request();
        if ($req->month != "" && $req->year != "") {
            $query = DB::select(DB::raw("SELECT u.distid,u.firstname,u.lastname,u.email,sps.sponsees,ha.achieved_rank,c.country
                        FROM ( SELECT DISTINCT sp.sponsorid,
                                        count(*) AS sponsees
                                        FROM users sp
                                        WHERE extract('month' from created_dt) = :month
                                        AND extract('year' from created_dt) = :year
                                        GROUP BY sp.sponsorid order by sponsees desc limit 10) sps
                        JOIN users u on u.distid = sps.sponsorid
                        left join get_users_by_highest_achievement() as ha on u.id = ha.user_id
                        left join (select DISTINCT * from addresses where \"primary\" = 1 and addrtype = '3') as a on a.userid = u.id
                        left join country c on c.countrycode = a.countrycode
                        group by u.distid, u.firstname,u.lastname,u.email,sps.sponsees,ha.achieved_rank,c.country
                        order by sps.sponsees desc"), [
                ':month' => $req->month,
                ':year' => $req->year
            ]);
        } else {
            $query = DB::select("SELECT u.distid,u.firstname,u.lastname,u.email,sps.sponsees,ha.achieved_rank,c.country
                        FROM ( SELECT DISTINCT sp.sponsorid,
                                        count(*) AS sponsees
                                        FROM users sp
                                        GROUP BY sp.sponsorid order by sponsees desc limit 10) sps
                        JOIN users u on u.distid = sps.sponsorid
                        left join get_users_by_highest_achievement() as ha on u.id = ha.user_id
                        left join (select DISTINCT * from addresses where \"primary\" = 1 and addrtype = '3') as a on a.userid = u.id
                        left join country c on c.countrycode = a.countrycode
                        group by u.distid, u.firstname,u.lastname,u.email,sps.sponsees,ha.achieved_rank,c.country
                        order by sps.sponsees desc");
        }
        return DataTables::of($query)->toJson();
    }

    public function exportMonthlyTopRecruiters($sort_col, $asc_desc, $q = null)
    {
        $req = request();
        $headers = array(
            "Content-type" => "text/csv",
            "Content-Disposition" => "attachment; filename=Export Monthly Top Recruiters Report.csv",
            "Pragma" => "no-cache",
            "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
            "Expires" => "0"
        );
        $recs = DB::select(DB::raw("SELECT u.distid,u.firstname,u.lastname,u.email,sps.sponsees,ha.achieved_rank,c.country
                        FROM ( SELECT DISTINCT sp.sponsorid,
                                        count(*) AS sponsees
                                        FROM users sp
                                        WHERE extract('month' from created_dt) = :month
                                        AND extract('year' from created_dt) = :year
                                        GROUP BY sp.sponsorid order by sponsees desc limit 10) sps
                        JOIN users u on u.distid = sps.sponsorid
                        left join get_users_by_highest_achievement() as ha on u.id = ha.user_id
                        left join (select DISTINCT * from addresses where \"primary\" = 1 and addrtype = '3') as a on a.userid = u.id
                        left join country c on c.countrycode = a.countrycode
                        group by u.distid, u.firstname,u.lastname,u.email,sps.sponsees,ha.achieved_rank,c.country
                        order by sps.sponsees desc"), [
            ':month' => $req->month,
            ':year' => $req->year
        ]);
        $columns = array('Dist ID', 'First Name', 'Last Name', 'Email', 'Country', 'Rank', 'Recruits');
        $callback = function () use ($recs, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            foreach ($recs as $rec) {
                fputcsv($file, array($rec->distid, $rec->firstname, $rec->lastname, $rec->email, $rec->country, $rec->achieved_rank, $rec->sponsees));
            }
            fclose($file);
        };
        return response()->stream($callback, 200, $headers);
    }

    public function listMonthlyTopCustomers()
    {
        $d = array();
        $years = DB::table("users")
            ->select(DB::raw("date_part('year', created_dt) as year"))
            ->where("usertype", "=", 2)
            ->whereNotNull("created_dt")
            ->groupBy(DB::raw("date_part('year', created_dt)"))
            ->orderBy("year", "asc")->get();
        $d['order_years'] = $years;
        $d['order_months'] = array(
            1 => "January",
            2 => "February",
            3 => "March",
            4 => "April",
            5 => "May",
            6 => "June",
            7 => "July",
            8 => "August",
            9 => "September",
            10 => "October",
            11 => "November",
            12 => "December",
        );
        return view('admin.reports.monthly_top_customers')->with($d);
    }

    public function getMonthlyTopCustomersDataTable()
    {
        $query = "";
        $req = request();
        if ($req->month != "" && $req->year != "") {
            $query = DB::select(DB::raw("
            SELECT u.distid,u.firstname,u.lastname,u.email,c.country,cust.activated_customers,ha.achieved_rank
            from users u
            join (
                select count(1) as activated_customers, userid from customers
                where extract('month' from created_date) = :month
                and extract('year' from created_date) = :year
                group by userid order by activated_customers desc limit 10
            ) cust on cust.userid = u.id
            left join (select DISTINCT * from addresses where \"primary\" = 1 and addrtype = '3') as a on a.userid = u.id
            left join country c on c.countrycode = a.countrycode
            left join get_users_by_highest_achievement() as ha on u.id = ha.user_id
            group by u.distid,u.firstname,u.lastname,u.email,c.country,cust.activated_customers,ha.achieved_rank
            order by ha.achieved_rank"), [
                ':month' => $req->month,
                ':year' => $req->year
            ]);
        } else {
            $query = DB::select("
            SELECT u.distid,u.firstname,u.lastname,u.email,c.country,cust.activated_customers,ha.achieved_rank
            from users u
            join (
                select count(1) as activated_customers, userid from customers
                group by userid order by activated_customers desc limit 10
            ) cust on cust.userid = u.id
            left join (select DISTINCT * from addresses where \"primary\" = 1 and addrtype = '3') as a on a.userid = u.id
            left join country c on c.countrycode = a.countrycode
            left join get_users_by_highest_achievement() as ha on u.id = ha.user_id
            group by u.distid,u.firstname,u.lastname,u.email,c.country,cust.activated_customers,ha.achieved_rank
            order by ha.achieved_rank");
        }
        return DataTables::of($query)->toJson();
    }

    public function exportMonthlyTopCustomers($sort_col, $asc_desc, $q = null)
    {
        $req = request();
        $headers = array(
            "Content-type" => "text/csv",
            "Content-Disposition" => "attachment; filename=Export Monthly Top Customers Report.csv",
            "Pragma" => "no-cache",
            "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
            "Expires" => "0"
        );
        $recs = DB::select(DB::raw("
        SELECT u.distid,u.firstname,u.lastname,u.email,c.country,cust.activated_customers,ha.achieved_rank
            from users u
            join (
                select count(1) as activated_customers, userid from customers
                where extract('month' from created_date) = :month
                and extract('year' from created_date) = :year
                group by userid order by activated_customers desc limit 10
            ) cust on cust.userid = u.id
            left join (select DISTINCT * from addresses where \"primary\" = 1 and addrtype = '3') as a on a.userid = u.id
            left join country c on c.countrycode = a.countrycode
            left join get_users_by_highest_achievement() as ha on u.id = ha.user_id
            group by u.distid,u.firstname,u.lastname,u.email,c.country,cust.activated_customers,ha.achieved_rank
            order by ha.achieved_rank
        "), [
            ':month' => $req->month,
            ':year' => $req->year
        ]);
        $columns = array('Dist ID', 'First Name', 'Last Name', 'Email', 'Country', 'Rank', 'Recruits');
        $callback = function () use ($recs, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            foreach ($recs as $rec) {
                fputcsv($file, array($rec->distid, $rec->firstname, $rec->lastname, $rec->email, $rec->country, $rec->achieved_rank, $rec->activated_customers));
            }
            fclose($file);
        };
        return response()->stream($callback, 200, $headers);
    }

    public function rankAdvancementList()
    {
        $d = array();
        $ranks = DB::table('rank_definition')
            ->select('rankdesc', 'rankval')
            ->get();
        $years = array(2019, 2020);
        $d['ranks'] = $ranks;
        $d['years'] = $years;
        $d['months'] = array(
            1 => "January",
            2 => "February",
            3 => "March",
            4 => "April",
            5 => "May",
            6 => "June",
            7 => "July",
            8 => "August",
            9 => "September",
            10 => "October",
            11 => "November",
            12 => "December",
        );
        return view("admin.reports.rank_advancement_report")->with($d);
    }

    public function GetRankAdvancementDataTable()
    {
        $req = request();
        $query = DB::table('v_rank_advancement');
        if ($req->month != "" && $req->year != "") {
            $query->whereMonth('created_dt', $req->month);
            $query->whereYear('created_dt', $req->year);
        } elseif ($req->month == "" && $req->year != "") {
            $query->whereYear('created_dt', $req->year);
        }

        if ($req->rank != "")
            $query->where('lifetime_rank', '=', $req->rank);

        $query->whereNotIn('distid', ['TSA0707550', 'TSA5138270', 'TSA9834283']);

        return DataTables::of($query)->toJson();
    }

    public function exportRankAdvancement($sort_col, $asc_desc, $q = null)
    {
        $req = request();
        $headers = array(
            "Content-type" => "text/csv",
            "Content-Disposition" => "attachment; filename=Rank Advancement Report.csv",
            "Pragma" => "no-cache",
            "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
            "Expires" => "0"
        );
        $recs = DB::table('v_rank_advancement');
        if ($q == null) {
            $recs->select('distid', 'firstname', 'lastname', 'email', 'achieved_rank', 'country', 'created_dt')
                ->whereNotIn('distid', ['TSA0707550', 'TSA5138270', 'TSA9834283'])
                ->orderBy($sort_col, $asc_desc);
        } else {
            $recs->select('distid', 'firstname', 'lastname', 'email', 'achieved_rank', 'country', 'created_dt')
                ->whereNotIn('distid', ['TSA0707550', 'TSA5138270', 'TSA9834283'])
                ->where(function ($sq) use ($q) {
                    $sq->where('firstname', 'ilike', "%" . $q . "%")
                        ->orWhere('lastname', 'ilike', "%" . $q . "%")
                        ->orWhere('email', 'ilike', "%" . $q . "%")
                        ->orWhere('achieved_rank', 'ilike', "%" . $q . "%")
                        ->orWhere('country', 'ilike', "%" . $q . "%")
                        ->orWhere('created_dt', 'ilike', "%" . $q . "%")
                        ->orWhere('distid', 'ilike', "%" . $q . "%");
                })
                ->orderBy($sort_col, $asc_desc);
        }

        if ($req->rank != "") {
            $recs->where('lifetime_rank', '=', $req->rank);
        }
        if ($req->month != "") {
            $recs->whereMonth('created_dt', '=', $req->month);
        }
        if ($req->year != "") {
            $recs->whereYear('created_dt', '=', $req->year);
        }
        $recs = $recs->get();
        $columns = array('Dist ID', 'First Name', 'Last Name', 'Email', 'Achieved Rank', 'Country', 'Created Date');
        $callback = function () use ($recs, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            foreach ($recs as $rec) {
                fputcsv($file, array($rec->distid, $rec->firstname, $rec->lastname, $rec->email, $rec->achieved_rank, $rec->country, $rec->created_dt));
            }
            fclose($file);
        };
        return response()->stream($callback, 200, $headers);
    }

    public function subscriptionHistoryReport()
    {
        return view('admin.reports.subscription_history');
    }

    public function subscriptionHistoryDataTable()
    {
        $query = DB::table('subscription_history as sh')
            ->select('u.distid', 'sh.status', 'sh.attempted_date', 'sh.response')
            ->join('users as u', 'u.id', '=', 'sh.user_id');

        return DataTables::of($query)->toJson();
    }

    public function exportSubscriptionHistory($sort_col, $asc_desc, $q = null)
    {
        $headers = array(
            "Content-type" => "text/csv",
            "Content-Disposition" => "attachment; filename=Subscription History Report.csv",
            "Pragma" => "no-cache",
            "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
            "Expires" => "0"
        );
        $recs = DB::table('subscription_history as sh')
            ->select('u.distid', 'sh.status', 'sh.attempted_date', 'sh.response')
            ->join('users as u', 'u.id', '=', 'sh.user_id');
        if ($q != null) {
            $recs->where(function ($sq) use ($q) {
                $sq->where('u.distid', 'ilike', "%" . $q . "%")
                    ->orWhere('sh.status', 'ilike', "%" . $q . "%")
                    ->orWhere('sh.attempted_date', 'ilike', "%" . $q . "%")
                    ->orWhere('sh.response', 'ilike', "%" . $q . "%");
            });
        }
        $recs->orderBy($sort_col, $asc_desc);
        $recs = $recs->get();
        $columns = array('Dist ID', 'Status', 'Response', 'Date');
        $callback = function () use ($recs, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            foreach ($recs as $rec) {
                fputcsv($file, array($rec->distid, $rec->status, $rec->response, $rec->attempted_date));
            }
            fclose($file);
        };
        return response()->stream($callback, 200, $headers);
    }

    /**
     * @return array
     */
    private function getMonthCommissionDates()
    {
        $unilevelCommissions = DB::table('unilevel_commission')
            ->selectRaw("end_date::date as end_date")
            ->where('user_id', Auth::user()->id)
            ->whereIn('status', [UnilevelService::POSTED_STATUS, UnilevelService::PAID_STATUS])
            ->groupBy('end_date')
            ->orderBy('end_date', 'desc')
            ->get();

        $leadershipCommissions = DB::table('leadership_commission')
            ->selectRaw("end_date::date as end_date")
            ->where('user_id', Auth::user()->id)
            ->whereIn('status', [UnilevelService::POSTED_STATUS, UnilevelService::PAID_STATUS])
            ->groupBy('end_date')
            ->orderBy('end_date', 'desc')
            ->get();

        $tsbCommission = DB::table('tsb_commission')
            ->selectRaw("created_at::date as end_date")
            ->where('user_id', Auth::user()->id)
            ->whereIn('status', [TsbCommissionService::POSTED_STATUS, TsbCommissionService::PAID_STATUS])
            ->groupBy('created_at')
            ->orderBy('created_at', 'desc')
            ->get();


        $select = [];
        foreach ($unilevelCommissions as $commission) {
            $select[] = $commission->end_date;
        }

        foreach ($leadershipCommissions as $commission) {
            $select[] = $commission->end_date;
        }

        foreach ($tsbCommission as $commission) {
            $select[] = $commission->end_date;
        }

        $select = array_unique($select);
        rsort($select);

        return $select;
    }

    /**
     * @return bool
     */
    public function getWeeklyCommissionDates()
    {
        $date = Carbon::now()->endOfDay();

        /** @var Collection $fsbWeeks */
        $fsbWeeks = DB::table('week_summary')
            ->select('week_ending')
            ->orderBy('week_ending', 'desc')
            ->groupBy('week_ending')
            ->get();

        /** @var Collection $binaryWeeks */
        $binaryWeeks = DB::table('binary_commission')
            ->select('week_ending')
            ->where('week_ending', '<=', $date)
            ->whereIn('status', [BinaryCommission::PAID_STATUS, BinaryCommission::POSTED_STATUS])
            ->orderBy('week_ending', 'desc')
            ->groupBy('week_ending')
            ->get();

        $allWeeks = $fsbWeeks->merge($binaryWeeks->toArray())->map(function ($week) {
            return $week->week_ending;
        })->toArray();

        $select = array_unique($allWeeks);
        rsort($select);

        return $select;
    }

    public function getSalesByCountryDataTable()
    {
        $req = request();

        if ($req->from != "" && $req->to != "") {
            $query = DB::select(
                DB::raw("select c.country, (case when sum(o.ordertotal) is not null then sum(o.ordertotal) else 0 end) as total_sales
                        from country c
                        left join users u on (c.countrycode = u.country_code)
                        left join orders o on (o.userid = u.id)
                        where o.created_dt >= :from and o.created_dt <= :to
                        group by c.country
                        order by country asc;"),
                [':from' => $req->from, ':to' => $req->to,]
            );
        } else {
            $query = DB::select(
                DB::raw("select c.country, (case when sum(o.ordertotal) is not null then sum(o.ordertotal) else 0 end) as total_sales
                        from country c
                        left join users u on (c.countrycode = u.country_code)
                        left join orders o on (o.userid = u.id)
                        group by c.country
                        order by country asc;")
            );
        }

        return DataTables::of($query)->toJson();
    }

    public function salesByCountryReportList($from = null, $to = null)
    {
        $d = array();
        $d['from'] = $from;
        $d['to'] = $to;
        $total_amount = '';

        if ($from != null && $to != null) {
            $total_sales = DB::table('orders')
                ->select(DB::raw('SUM(ordertotal) AS total_sales'))
                ->whereDate('created_dt', '>=', $from)
                ->whereDate('created_dt', '<=', $to)->first();
        } else {
            $total_sales = DB::table('orders')
                ->select(DB::raw('SUM(ordertotal) AS total_sales'))->first();
        }

        $d['total_sales'] = $total_sales->total_sales;

        return view('admin.reports.sales_by_country')->with($d);
    }

    public function exportSalesByCountry($sort_col, $asc_desc, $q = null)
    {
        $req = request();
        $headers = array(
            "Content-type" => "text/csv",
            "Content-Disposition" => "attachment; filename=Sales by Country Report.csv",
            "Pragma" => "no-cache",
            "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
            "Expires" => "0"
        );

        $recs = DB::table('country')
            ->select([
                DB::raw('country.country'),
                DB::raw('COALESCE(SUM(orders.ordertotal), 0) as total_sales')
            ]);

        $recs->leftJoin('users', 'users.country_code', '=', 'country.countrycode');
        $recs->leftJoin('orders', 'orders.userid', '=', 'users.id');

        $recs->groupBy('country.country');

        if ($q != null) {
            $recs->where(function ($sq) use ($q) {
                $sq->where('country', 'ilike', "%" . $q . "%");
            });
        }

        if ($req->d_from) {
            $recs->where(DB::raw("date(orders.created_dt)"), ">=", $req->d_from);
        }

        if ($req->d_to) {
            $recs->where(DB::raw("date(orders.created_dt)"), "<=", $req->d_to);
        }

        $recs->orderBy($sort_col, $asc_desc);
        $recs = $recs->get();
        $columns = array('Country', 'Total Sales');
        $callback = function () use ($recs, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            foreach ($recs as $rec) {
                fputcsv($file, array($rec->country, $rec->total_sales));
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
