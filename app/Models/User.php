<?php

namespace App\Models;

use App\Facades\BinaryPlanManager;
use App\Models\ForceRank;
use App\Models\UserActivityLog;
use App\Models\UserSettings;
use App\Models\GeoIP;
use App\Services\AchievedRankService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\HasApiTokens;
use Illuminate\Http\Response;


use App\Models\Address;
use App\Models\PaymentMethod;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderConversion;
use App\Models\PreOrder;
use App\Models\PreOrderItem;
use App\Models\BinaryCommissionCarryoverHistory;
use App\Models\UserActivityHistory;
use App\Jobs\BinaryTreePlacement;


use Carbon\Carbon;
use App\Helpers\Util;
use Auth;
use Exception;
use Log;

class User extends Authenticatable
{

    use HasApiTokens, Notifiable;

    public $timestamps = false;

    const USER_TYPE_ADMIN       = 1;
    const USER_TYPE_DISTRIBUTOR = 2;
    const USER_TYPE_CUSTOMER    = 3;
    const USER_TYPE_LEAD        = 4;

    //
    const REC_PER_PAGE = 20;
    // affiliate account status
    const ACC_STATUS_PENDING_APPROVAL = "PENDING APPROVAL";
    const ACC_STATUS_PENDING = "PENDING REVIEW";
    const ACC_STATUS_APPROVED = "APPROVED";
    const ACC_STATUS_SUSPENDED = "SUSPENDED";
    const ACC_STATUS_TERMINATED = "TERMINATED";
    const ACC_STATUS_CHARGEBACK_REVIEW = "CHARGEBACK REVIEW";

    const MIN_QV_MONTH_VALUE = 100;
    const MIN_QV_WITHOUT_COMMISSIONS = 200;
    const RANK_PERCENTS = [
        RankInterface::RANK_AMBASSADOR => 0,
        RankInterface::RANK_DIRECTOR => 12,
        RankInterface::RANK_SENIOR_DIRECTOR => 12,
        RankInterface::RANK_EXECUTIVE => 12,
        RankInterface::RANK_SAPPHIRE_AMBASSADOR => 15,
        RankInterface::RANK_RUBY => 16,
        RankInterface::RANK_EMERALD => 17,
        RankInterface::RANK_DIAMOND => 18,
        RankInterface::RANK_BLUE_DIAMOND => 19,
        RankInterface::RANK_BLACK_DIAMOND => 20,
        RankInterface::RANK_PRESIDENTIAL_DIAMOND => 20,
        RankInterface::RANK_CROWN_DIAMOND => 20,
        RankInterface::RANK_DOUBLE_CROWN_DIAMOND => 20,
        RankInterface::RANK_TRIPLE_CROWN_DIAMOND => 20,
    ];

    //
    use Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'firstname',
        'lastname',
        'email',
        'phonenumber',
        'username',
        'usertype',
        'is_business',
        'mobilenumber',
        'sync_with_mailgun',
        'password',
        'current_month_qv',
        'current_month_pqv',
        'country_code',
        'current_month_tsa',
        'current_month_cv',
        'binary_q_l',
        'binary_q_r',
        'is_activate',
        'is_bc_active',
        'has_agreed_vibe',
        'refname',
        'sponsorid',
        'created_time',
        'created_dt',
        'created_date',
        'subscription_product',
        'current_product_id'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'current_month_qv',
        // 'current_month_pqv',
        'current_month_cv',
        'is_activate',
        'is_bc_active',
        'password',
        'remember_token',
        'current_month_tsa',
        'binary_q_l',
        'binary_q_r'
    ];

    /**
     * @return HasMany
     */
    public function paymentMethods()
    {
        return $this->hasMany('App\Models\PaymentMethod', 'userID');
    }

    /**
     * @return HasMany
     */
    public function carryovers()
    {
        return $this->hasMany('App\Models\BinaryCommissionCarryoverHistory');
    }

    /**
     * @return HasMany
     */
    public function addresses()
    {
        return $this->hasMany('App\Models\Address', 'userid', 'id');
    }

    /**
     * @return HasMany
     */
    public function binaryCommissions()
    {
        return $this->hasMany('App\Models\BinaryCommission');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function userStatistic()
    {
        return $this->hasOne('App\Models\UserStatistic');
    }

    public function customer()
    {
        return $this->hasOne('App\Models\Customer', 'userid');
    }

    public function billgeniusToken()
    {
        return $this->hasOne('App\Models\BillgeniusTokens');
    }
    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function replicatedPreferences()
    {
        return $this->hasOne('App\Models\ReplicatedPreferences');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function rankDefinition()
    {
        return $this->hasOne('App\Models\RankDefinition', 'rankval', 'current_month_rank');
    }

    /**
     * @return HasMany
     */
    public function activity()
    {
        return $this->hasMany('App\Models\UserActivityHistory');
    }

    public static function isAdmin()
    {
        if (Auth::check() && Auth::user()->usertype == UserType::TYPE_ADMIN) {
            return true;
        }
        return false;
    }

    public static function isAffiliateUser()
    {
        if (Auth::check() && Auth::user()->usertype == UserType::TYPE_DISTRIBUTOR && Auth::user()->account_status != self::ACC_STATUS_SUSPENDED) {
            return true;
        }
        return false;
    }

    public static function isVibeImportUser()
    {
        return  Auth::check() &&
            Auth::user()->usertype == UserType::TYPE_DISTRIBUTOR &&
            Auth::user()->legacyid == 'VIBE';
    }

    public static function admin_super_admin()
    {
        if (Auth::check()) {
            $u = Auth::user();
            if ($u->usertype == UserType::TYPE_ADMIN && $u->admin_role == UserType::ADMIN_SUPER_ADMIN) {
                return true;
            }
        }
        return false;
    }

    public static function admin_super_exec()
    {
        if (Auth::check()) {
            $u = Auth::user();
            if ($u->usertype == UserType::TYPE_ADMIN && $u->admin_role == UserType::ADMIN_SUPER_EXEC) {
                return true;
            }
        }
        return false;
    }

    public static function admin_sales()
    {
        if (Auth::check()) {
            $u = Auth::user();
            if ($u->usertype == UserType::TYPE_ADMIN && $u->admin_role == UserType::ADMIN_SALES) {
                return true;
            }
        }
        return false;
    }

    public static function admin_cs_manager()
    {
        if (Auth::check()) {
            $u = Auth::user();
            if ($u->usertype == UserType::TYPE_ADMIN && $u->admin_role == UserType::ADMIN_CS_MGR) {
                return true;
            }
        }
        return false;
    }

    public static function admin_cs()
    {
        if (Auth::check()) {
            $u = Auth::user();
            if ($u->usertype == UserType::TYPE_ADMIN && $u->admin_role == UserType::ADMIN_CS) {
                return true;
            }
        }
        return false;
    }

    public static function admin_cs_exec()
    {
        if (Auth::check()) {
            $u = Auth::user();
            if ($u->usertype == UserType::TYPE_ADMIN && $u->admin_role == UserType::ADMIN_CS_EXEC) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function currentProduct()
    {
        return $this->belongsTo('App\Models\Product', 'current_product_id')->withDefault();;
    }


    public static function addNew($req)
    {
        $rec = new User();
        $rec->firstname = $req->firstname;
        $rec->lastname = $req->lastname;
        $rec->distid = $req->distid;
        //
        $rec->account_status = $req->account_status;
        $rec->usertype = UserType::TYPE_DISTRIBUTOR;
        $rec->email = strtolower($req->email);
        $rec->email_verified = $req->email_verified;
        //
        $rec->phonenumber = $req->phonenumber;
        $rec->mobilenumber = $req->mobilenumber;
        $rec->business_name = $req->business_name;
        //
        $rec->username = strtolower($req->username);
        //
        $rec->entered_by = Auth::user()->id;
        $rec->sponsorid = $req->sponsorid;
        $rec->remarks = $req->remarks;
        if (!Util::isNullOrEmpty($req->default_password)) {
            $rec->default_password = $req->default_password;
            $rec->password = password_hash($req->default_password, PASSWORD_BCRYPT);
        }
        $rec->current_product_id = $req->current_product_id;
        //
        $rec->created_date = Util::getCurrentDate();
        $rec->created_time = Util::getCurrentTime();
        $rec->created_dt = Util::getCurrentDateTime();
        $rec->save();
        //
        return $rec->id;
    }

    public static function updatePlacements($userId, $req)
    {
        $rec = User::find($userId);

        $rec_old = clone $rec;

        $rec->binary_placement = $req->binary_placement;
        $rec->save();

        $userActivityLog = new UserActivityLog;
        $row = UserSettings::getByUserId(Auth::user()->id);

        // $response = GeoIP::getInformationFromIP($row->current_ip);

        $userActivityLog->ip_address = $row->current_ip ?: '127.0.0.1';
        $userActivityLog->user_id = Auth::user()->id;
        $userActivityLog->ip_details = ' ';
        $userActivityLog->action = "UPDATE Binary Placement";
        $userActivityLog->old_data = "Placement was (" . $rec->binary_placement . ")";
        $userActivityLog->new_data = "Placement has been updated(" . $req->binary_placement . ")";
        $userActivityLog->save();

        $rec->binary_placement = $req->binary_placement;
        $rec->save();
    }

    public static function updateRec($userId, $req, $updatedByAdmin = true)
    {
        $rec = User::find($userId);
        $rec_old = clone $rec;
        //
        $rec->firstname = $req->firstname;
        $rec->lastname = $req->lastname;
        //
        $rec->phonenumber = $req->phonenumber;
        $rec->mobilenumber = $req->mobilenumber;
        //
        $rec->business_name = $req->business_name;
        $rec->beneficiary = $req->beneficiary;
        //
        if ($updatedByAdmin) {
            $rec->username = strtolower($req->username);
            $rec->account_status = $req->account_status;
            $rec->sponsorid = $req->sponsorid;
            $rec->email = strtolower($req->email);
            $rec->email_verified = $req->email_verified;
            $rec->remarks = $req->remarks;

            if (!Util::isNullOrEmpty($req->default_password)) {
                if ($rec->default_password != $req->default_password) {
                    $rec->default_password = $req->default_password;
                    $rec->password = password_hash($req->default_password, PASSWORD_BCRYPT);
                }
            }

            $rec->co_applicant_name = $req->co_applicant_name;
            $rec->co_applicant_email = $req->co_applicant_email;
            $rec->co_applicant_phone_number = $req->co_applicant_phone_number;
            $rec->co_applicant_mobile_number = $req->co_applicant_mobile_number;
            $rec->co_applicant_add_date = Carbon::today()->toDateString();
        } else {
            $rec->display_name = $req->display_name;
            $rec->recognition_name = $req->recognition_name;
        }

        $userActivityLog = new UserActivityLog;
        $row = UserSettings::getByUserId(Auth::user()->id);
        $response = GeoIP::getInformationFromIP($row->current_ip);
        $userActivityLog->ip_address = $row->current_ip;
        $userActivityLog->user_id = Auth::user()->id;
        $userActivityLog->ip_details = $response;
        $userActivityLog->action = "UPDATE user Basic Information";
        $userActivityLog->old_data = json_encode($rec_old);
        $userActivityLog->new_data = json_encode($rec);
        $userActivityLog->save();


        //
        $rec->save();
    }

    public static function getUserByUserType($usertype)
    {
        return DB::table('users')
            ->select('id', 'firstname', 'lastname', 'email', 'account_status', 'email_verified', 'entered_by')
            ->where('usertype', $usertype)
            ->paginate(self::REC_PER_PAGE);
    }

    public static function getLoginUserName()
    {
        if (Auth::check())
            return Auth::user()->firstname . " " . Auth::user()->lastname;
        else
            return "";
    }

    public static function getLoginUserTSA()
    {
        if (Auth::check())
            return Auth::user()->distid;
        else
            return "";
    }

    public static function getLoginUserEmail()
    {
        if (Auth::check())
            return Auth::user()->email;
        else
            return "";
    }

    public static function getRememberTokenForEvents()
    {
        if (Auth::check())
            return Auth::user()->remember_token;
        else
            return "";
    }

    public static function getById($userId)
    {
        return self::where('id', $userId)
            ->first();
    }

    public static function getByDistId($distId)
    {
        return DB::table('users')
            ->where('distid', $distId)
            ->first();
    }

    //    public static function updateTaxFlag($distId) {
    //        DB::table('users')
    //            ->where('distid', $distId)
    //            ->update([
    //                'is_tax_confirmed' => 1
    //            ]);
    //    }

    public static function getByUsername($username)
    {
        return DB::table('users')
            ->where('username', $username)
            ->first();
    }

    public static function getByDistIdOrUsername($q)
    {
        return DB::table('users')
            ->where('distid', $q)
            ->orWhere('username', $q)
            ->first();
    }

    public static function getBasicInfo($userId)
    {
        return DB::table('users')
            ->select('firstname', 'lastname', 'email', 'username')
            ->where('id', $userId)
            ->first();
    }

    public static function getMonthPQV($userId, $month, $year)
    {
        $start = Carbon::parse($year . '-' . $month . '-01')->format('Y-m-d');
        $end = Carbon::parse($year . '-' . $month . '-01')->endOfMonth()->format('Y-m-d');
        $pqv = DB::table('orders')
            ->select('orderqv', DB::raw('sum(orderqv) as pqv'))
            ->where('userid', $userId)
            ->whereBetween('created_date', [$start, $end])
            ->groupBy('orderqv')
            ->first();

        return $pqv->pqv;
    }

    public static function getRecByEmail($email, $usertype = null)
    {
        $q = DB::table('users');
        $q->select('firstname', 'lastname');
        $q->where('email', $email);
        if ($usertype != null)
            $q->where('usertype', $usertype);
        return $q->first();
    }

    public static function getEmailVerificationCode($email, $userId)
    {
        return md5(strlen($email) . "evc" . $email . "ui" . $userId);
    }

    public static function getReferrorInfoBySiteName($siteName)
    {
        return DB::table('users')
            ->select('id', 'level')
            ->where('username', $siteName)
            ->first();
    }

    private static function getLevelById($userId)
    {
        $rec = DB::table('users')
            ->select('level')
            ->where('id', $userId)
            ->first();
        if (empty($rec))
            return -1;
        else
            return $rec->level;
    }

    public static function getRandomTSA()
    {
        $tsa = 'TSA' . Util::getRandomString(7, "0123456789");
        //
        $count = DB::table('users')
            ->where('distid', $tsa)
            ->count();

        if ($count > 0) {
            return self::getRandomTSA();
        } else {
            return $tsa;
        }
    }

    public static function updateLegacyId($iqId)
    {
        DB::table('users')
            ->where('id', Auth::user()->id)
            ->update([
                'legacyid' => $iqId
            ]);
    }

    public static function getLegacyId($id)
    {
        $rec = DB::table('users')
            ->select('legacyid')
            ->where('id', $id)
            ->first();
        if (empty($rec)) {
            return null;
        }
        if (Util::isNullOrEmpty($rec->legacyid))
            return null;
        else
            return $rec->legacyid;
    }

    public static function getTotalEnrollments($distid = null)
    {
        $q = DB::table('users');
        $q->where('usertype', UserType::TYPE_DISTRIBUTOR);
        if ($distid != null)
            $q->where('sponsorid', $distid);
        return $q->count();
    }

    private static function determineSubscriptionProduct($userId, $productId)
    {
        $countryCodeResult = static::query()
            ->select(['country_code'])
            ->where('id', '=', $userId)->first();

        $userCountryIsTier3 = $countryCodeResult != null && Country::isTier3($countryCodeResult->country_code);

        switch ($productId) {
            case 1: // standby
                return 33;
                break;
            case 2: // coach class
                return $userCountryIsTier3 === true ? 26 : 11;
                break;
            case 3: // business class
            case 4: // first class
                return 11;
                break;
        }

        return null;
    }

    public static function upgrateProduct($productId)
    {
        switch ($productId) {
            case Product::UPG_ISBO_TO_FX:
                return Product::ID_FX_PACK;
                break;
            case Product::UPG_ISBO_TO_VISIONARY:
                return Product::ID_VISIONARY_PACK;
                break;
            case Product::UPG_ISBO_TO_BASIC:
                return Product::ID_BASIC_PACK;
                break;
            case Product::UPG_FX_TO_VISIONARY:
                return Product::ID_VISIONARY_PACK;
                break;
            case Product::UPG_BASIC_TO_VISIONARY:
                return Product::ID_VISIONARY_PACK;
                break;
            default:
                return $productId;
                break;
        }
    }

    public static function setCurrentProductId($userId, $productId)
    {
        $product = Product::getProduct($productId);

        if ($product->producttype == 2) {
            $productId = static::upgrateProduct($productId);
        }

        $subscriptionProduct = static::determineSubscriptionProduct($userId, $productId);

        $data = [
            'current_product_id' => $productId
        ];

        if ($subscriptionProduct != null) {
            $data['subscription_product'] = $subscriptionProduct;
        }

        DB::table('users')
            ->where('id', $userId)
            ->update($data);
        //
        self::resetSyncWithMailgun($userId);
    }

    // import "TraVerus" users
    public static function importFromTV($rec)
    {
        $agentId = $rec[0];
        $agentName = $rec[1]; // firstname, last name
        $webAlias = $rec[2]; // username
        $email = $rec[3];
        $sponsor = $rec[4];
        $appdate = $rec[5];

        $names = explode(" ", $agentName);

        //$isEmailExist = self::isEmailExist($email);
        //if (!$isEmailExist) {
        $n = new User();
        $n->firstname = $names[0];
        $n->lastname = isset($names[1]) ? $names[1] : "";
        $n->legacyid = $agentId;
        $n->username = self::getUniqueTVusername($webAlias);
        $n->email = $email;
        $n->distid = $agentId;
        $n->created_date = Util::getFormatedDate($appdate);
        $n->sponsorid = $sponsor;
        $n->is_tv_user = 1;
        $n->save();
        //} else {
        //    echo "email exist: " . $email . "<br/>";
        //}
    }

    // import "TraVerus" users
    public static function assignTVsponsor($rec)
    {
        $agentId = $rec[0];
        $sponsor = $rec[4];
        //
        $x = DB::table('users')
            ->select('id')
            ->where('distid', $sponsor)
            ->where('is_tv_user', 1)
            ->first();
        if (empty($x)) {
            //
            $y = DB::table('users')
                ->select('id')
                ->where('distid', $agentId)
                ->where('is_tv_user', 1)
                ->first();
            //
            TVBrokenSponsors::addNew($y->id, $rec);
            //
            // set default sponsor 'A1637504'
            DB::table('users')
                ->where('id', $y->id)
                ->update([
                    'sponsorid' => 'A1637504'
                ]);
        }
    }

    // check if the username is already exist for TV users
    // if exist, change the existing username
    private static function getUniqueTVusername($username)
    {
        $rec = DB::table('users')
            ->select('id')
            ->where('username', $username)
            ->first();
        if (!empty($rec)) {
            echo $rec->id . "xx" . $username . "<br/>";
            self::setNewUsername($rec->id, $username, 1);
        }
        return $username;
    }

    private static function setNewUsername($userId, $username, $suffix)
    {
        $newUsername = $username . "" . $suffix;
        $count = DB::table('users')
            ->where('username', $newUsername)
            ->count();
        if ($count > 0) {
            $suffix++;
            self::setNewUsername($userId, $username, $suffix);
        } else {
            DB::table('users')
                ->where('id', $userId)
                ->update([
                    'username' => $newUsername
                ]);
        }
    }

    private static function isEmailExist($email)
    {
        $count = DB::table('users')
            ->where('email', $email)
            ->count();
        return $count > 0;
    }

    private static function getEnrollmentOrders($email)
    {
        $count = DB::table('users')
            ->where('email', $email)
            ->count();
        return $count > 0;
    }

    public static function getCurrentProductId($userId)
    {
        $rec = DB::table('users')
            ->select('current_product_id')
            ->where('id', $userId)
            ->first();
        if (empty($rec))
            return 0;
        else
            return $rec->current_product_id;
    }

    public function product()
    {
        return $this->hasOne('App\Product', 'id', 'current_product_id');
    }

    public function rank()
    {
        $rank = null;
        $rank = DB::select("SELECT * FROM get_rank_metrice('" . $this->distid . "','" . $this->current_month_rank . "')");

        if (is_array($rank) && count($rank)) {
            $rank = $rank[0];
        }

        return $rank;
    }

    public static function makeTVuserFirstClass($distid)
    {
        $userRec = DB::table('users')
            ->select('id')
            ->where('distid', $distid)
            ->where('is_tv_user', 1)
            ->first();
        if (!empty($userRec)) {
            //
            $product = \App\Product::getProduct(\App\Product::ID_EB_FIRST_CLASS);
            \App\PreEnrollmentSelection::addNewRec($userRec->id, \App\Product::ID_EB_FIRST_CLASS);
            //
            $authorization = "";
            // create new order
            $orderId = \App\Order::addNew($userRec->id, 0, 0, $product->bv, $product->qv, $product->cv, $authorization, null, null, null);
            // create new order item
            \App\OrderItem::addNew($orderId, $product->id, 1, 0, $product->bv, $product->qv, $product->cv);
            // set package info
            \App\User::setCurrentProductId($userRec->id, $product->id);
        } else {
            echo $distid . "<br/>";
        }
    }

    public static function isTvUser($userId)
    {
        $count = DB::table('users')
            ->where('id', $userId)
            ->where('is_tv_user', 1)
            ->count();
        return $count > 0;
    }

    public static function getGroupCount()
    {
        $q = DB::table('users');
        $q->select('current_product_id', DB::raw('count(1) as total'));
        $q->where('usertype', UserType::TYPE_DISTRIBUTOR);
        $q->where('account_status', User::ACC_STATUS_APPROVED);
        $q->groupBy('current_product_id');
        return $q->get();
    }

    public static function getGroupCountByUser($distid)
    {
        $q = DB::table('users');
        $q->select('current_product_id', DB::raw('count(1) as total'));
        $q->where('sponsorid', $distid);
        $q->groupBy('current_product_id');
        return $q->get();
    }

    public function getFullName()
    {
        return sprintf('%s %s', $this->firstname, $this->lastname);
    }

    public function isActiveStatus()
    {
        return !in_array($this->account_status, [
            self::ACC_STATUS_TERMINATED,
            self::ACC_STATUS_SUSPENDED
        ]);
    }

    public function getEnrolledDate()
    {
        //return date('d/m/Y', strtotime($this->created_date));
        return date('d/m/Y', strtotime($this->created_dt));
    }

    /**
     * @return int
     */
    public function getBinaryPaidPercent()
    {
        $rankPercent = 0;
        $packPercent = 0;

        $packageId = $this->current_product_id;

        $data = \Illuminate\Support\Facades\DB::select(
            sprintf(
                '
                SELECT COALESCE(MAX(rankval), 0) as rank_val FROM rank_history rh
                        JOIN rank_definition rd
                        ON rh.lifetime_rank = rd.rankval
                        WHERE rh.users_id = %d
                        AND created_dt >= date_trunc(\'month\', now()::date) - interval \'1 month\';
                ',
                $this->id
            )
        );

        $rankId = $this->current_month_rank;
        if (is_array($data) && isset($data[0]->rank_val)) {
            $rankId = $data[0]->rank_val;
        }

        $data = \Illuminate\Support\Facades\DB::select(
            sprintf(
                'SELECT get_binary_commission_percent(%d, %d, %d) as percent',
                $rankId,
                $packageId,
                $this->id
            )
        );

        if (is_array($data) && isset($data[0]->percent)) {
            $packPercent = $data[0]->percent;
        }

        return $rankPercent > $packPercent ? $rankPercent : $packPercent;
    }

    public static function canUpgrade()
    {
        //        $enrollmentDate = date('Y-m-d', strtotime(Auth::user()->created_dt));
        //        $startDate = "2019-03-11";
        //        $countDownDays = 63;
        //        if ($enrollmentDate == null || $enrollmentDate < $startDate) {
        //            $enrollmentDate = $startDate;
        //        }
        //        $endDate = date('Y-m-d', strtotime($enrollmentDate . ' + ' . $countDownDays . ' days'));
        $endDate = date('Y-m-d', strtotime(Auth::user()->coundown_expire_on . ' + ' . 1 . ' days'));
        $today = Util::getCurrentDate();
        //
        $res = array();
        $res['can_upgrade'] = $today < $endDate;
        $res['end_date'] = $endDate;
        return $res;
    }

    public static function resetSyncWithMailgun($userId)
    {
        DB::table('users')
            ->where('id', $userId)
            ->update([
                'sync_with_mailgun' => -1
            ]);
    }

    public static function updateUserSitesStatus($userId, $status, $subscriptionAttempt, $paymentFailCount)
    {
        DB::table('users')
            ->where('id', $userId)
            ->update([
                'is_sites_deactivate' => $status,
                'subscription_attempts' => $subscriptionAttempt,
                'payment_fail_count' => $paymentFailCount
            ]);
    }

    public static function updateNextSubscriptionDate($userId, $nextSubscriptionDate)
    {
        DB::table('users')
            ->where('id', $userId)
            ->update([
                'original_subscription_date' => $nextSubscriptionDate,
                'next_subscription_date' => $nextSubscriptionDate
            ]);
    }

    public static function terminateUserDetailsAfterSubscriptionFail()
    {
        //        $last3Month = date("Y-m", strtotime("-3 months"));
        return DB::table('users')
            ->select('*')
            //            ->where('original_subscription_date', '=', date('Y-m-d', $last3Month))
            ->where('payment_fail_count', '>=', 6)
            ->get();
    }

    public static function gracePeriodUsers()
    {
        $last7Day = strtotime("-7 day");
        return DB::table('users')
            ->select('*')
            ->where('original_subscription_date', '>', date('Y-m-d', $last7Day))
            ->where('gflag', 1)
            ->get();
    }

    public static function getRecordsForAPI($recPerPage)
    {
        return DB::table('users')
            ->select('distid', 'firstname', 'lastname', 'username', 'email')
            ->where('usertype', UserType::TYPE_DISTRIBUTOR)
            ->where('account_status', self::ACC_STATUS_APPROVED)
            ->orderBy('id', 'asc')
            ->paginate($recPerPage);
    }

    public static function getByDistIdForAPI($distid)
    {
        return DB::table('users')
            ->select('distid', 'firstname', 'lastname', 'username', 'email')
            ->where('usertype', UserType::TYPE_DISTRIBUTOR)
            ->where('account_status', self::ACC_STATUS_APPROVED)
            ->where('distid', strtoupper($distid))
            ->first();
    }

    public static function getByEnrollmentDateForAPI($date)
    {
        return DB::table('users')
            ->select('distid', 'firstname', 'lastname', 'username', 'email')
            ->where('usertype', UserType::TYPE_DISTRIBUTOR)
            ->where('account_status', self::ACC_STATUS_APPROVED)
            ->whereDate('created_dt', $date)
            ->get();
    }

    public static function updateCountdownExpiryDate($req)
    {
        DB::table('users')
            ->where('distid', $req->distid)
            ->update([
                'coundown_expire_on' => $req->coundown_expire_on
            ]);
    }

    public static function updateCountdownExpiryDateBulk($req)
    {
        $now = Util::getCurrentDate();
        DB::table('users')
            ->where('coundown_expire_on', '<', $now)
            ->update([
                'coundown_expire_on' => $req->coundown_expire_on
            ]);
    }

    public static function getAuthyInfo($email)
    {
        return DB::table('users')
            ->select('id', 'authy_id')
            ->where('email', $email)
            ->first();
    }

    /**
     * @param $date
     * @return mixed
     */
    public function getUserActivityByDate($date)
    {
        if (!is_string($date)) {
            $date = $date->format('Y-m-d');
        }

        return $this->activity->filter(function ($activity) use ($date) {
            return $activity->created_at == $date;
        })->first();
    }

    /**
     * @param null $date
     * @return bool
     */
    public function isUserActive($date = null)
    {
        if ($date && $activityByDate = $this->getUserActivityByDate($date)) {
            return $activityByDate->is_active;
        }

        $lastActivity = $this->activity->sortBy('created_at')->last();

        return $lastActivity->is_active;
    }

    /**
     * @param null $date
     * @return bool
     */
    public function isBinaryUserActive($date = null)
    {
        if ($date && $activityByDate = $this->getUserActivityByDate($date)) {
            return $activityByDate->is_bc_active;
        }

        $lastActivity = $this->activity->sortBy('created_at')->last();

        return $lastActivity->is_bc_active;
    }

    /**
     * @param null $date
     * @return bool
     */
    public function isUserActivate($date = null)
    {
        if ($date && $activityByDate = $this->getUserActivityByDate($date)) {
            return $activityByDate->is_activate;
        }

        $lastActivity = $this->activity->sortBy('created_at')->last();

        return $lastActivity->is_activate;
    }

    /**
     * @return bool
     */
    public function getCurrentActiveStatus()
    {
        return true;
        // get the range with UTC timezone because the DB works using it as the default timezone
        $monthAgo = Carbon::now('UTC')->subDays(30)->format('Y-m-d');
        // for premium FC activate for 12 months from enrollment date
        $yearAgo = date('Y-m-d', strtotime("-1 Year"));
        //now
        $now = Carbon::now('UTC');
        // calculate status on the fly (will be optimize later)

        $result = DB::select(
            "
            select count(u.id) from users u
            where (
                u.id in (select userid from orders where date(created_dt) >= :monthAgo group by userid having sum(orderqv) >= :minPqvValue)
                
            )
            and u.account_status not in ('TERMINATED', 'SUSPENDED')
            and u.id = :userId;",
            [
                'monthAgo' => $monthAgo,
                'yearAgo' => $yearAgo,
                'minPqvValue' => self::MIN_QV_MONTH_VALUE,
                'userId' => $this->id
            ]
        );
        // $result = DB::select(
        //     "
        //     select count(u.id) from users u
        //     where (
        //         u.id in (select userid from orders where date(created_dt) >= :monthAgo group by userid having sum(orderqv) >= :minPqvValue)
        //         or (u.current_product_id = :idPremiumFirstClass and u.created_dt > :yearAgo)
        //     )
        //     and u.account_status not in ('TERMINATED', 'SUSPENDED')
        //     and u.id = :userId;",
        //     [
        //         'monthAgo' => $monthAgo,
        //         'yearAgo' => $yearAgo,
        //         'minPqvValue' => self::MIN_QV_MONTH_VALUE,
        //         'userId' => $this->id,
        //         'idPremiumFirstClass' => Product::ID_PREMIUM_FIRST_CLASS,
        //     ]
        // );

        $isActive = $result[0]->count > 0;

        // $alwaysActiveUsers = [
        //     'A1357703',
        //     'A1637504',
        //     'TSA9846698',
        //     'TSA3564970',
        //     'TSA9714195',
        //     'TSA8905585',
        //     'TSA2593082',
        //     'TSA0707550',
        //     'TSA9834283',
        //     'TSA5138270',
        //     'TSA8715163',
        //     'TSA3516402',
        //     'TSA8192292',
        //     'TSA0002566',
        //     'TSA9856404'
        // ];

        // if (in_array($this->distid, $alwaysActiveUsers)) {
        //     $isActive = true;
        // }

        // // Rafael - Seams this functions was used for some period and now that the period pass, I think we should remove it.
        // if ($this->isTurkeyInActivePeriod($now)) {
        //     $isActive = true;
        // }

        return $isActive;
    }

    /**
     * Get the sponsor user entity by the dist ID relation.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function sponsor()
    {
        return $this->hasOne('App\Models\User', 'distid', 'sponsorid');
    }

    /**
     * Re-activate current user (set approved status)
     */
    public function reactivate()
    {
        $this->account_status = self::ACC_STATUS_APPROVED;
    }

    /**
     * Inactivate current user (set terminated status)
     */
    public function inactivate()
    {
        $this->account_status = self::ACC_STATUS_TERMINATED;
    }

    /**
     * @param Carbon $endDate
     * @param $commissionKey
     * @return int
     */
    public function getCommissionRank($endDate, $commissionKey = null)
    {
        if ($commissionKey) {
            $rank = ForceRank::where('user_distid', $this->distid)
                ->where('commission_type', $commissionKey)
                ->first();

            if ($rank) {
                return $rank->rank_id;
            }
        }

        $rankId = $this->rank()->rankid;

        $data = \Illuminate\Support\Facades\DB::select(
            sprintf(
                '
                SELECT COALESCE(MAX(rd.id), 0) as rank_id FROM rank_history rh
                        JOIN rank_definition rd
                        ON rh.lifetime_rank = rd.rankval
                        WHERE rh.users_id = %d
                        AND created_dt >= date_trunc(\'month\', date(\'%s\')) - interval \'1 month\'
                        AND created_dt <= \'%s\'
                ',
                $this->id,
                $endDate->format('Y-m-d'),
                $endDate->format('Y-m-d H:i:s')
            )
        );

        if (is_array($data) && isset($data[0]->rank_id)) {
            $rankId = $data[0]->rank_id;
        }

        return $rankId ?: $this->rank()->rankid;
    }

    private function getRootRank()
    {
        return ForceRank::where('user_distid', $this->distid)
            ->whereNull('commission_type')
            ->first();
    }

    /**
     * @return mixed
     */
    public function getCurrentLeftCarryover()
    {
        $lastCarryover = $this->carryovers->sortBy('bc_history_id')->last();

        return $lastCarryover->left_carryover ?? $this->current_left_carryover;
    }

    /**
     * @return mixed
     */
    public function getCurrentRightCarryover()
    {
        $lastCarryover = $this->carryovers->sortBy('bc_history_id')->last();

        return $lastCarryover->right_carryover ?? $this->current_right_carryover;
    }

    /**
     * @param Carbon $date
     * @return bool
     */
    public function isTurkeyInActivePeriod(Carbon $date)
    {
        $startActivePeriod = Carbon::parse('2019-06-25 00:00:00');
        $endActivePeriod = Carbon::parse('2019-09-01 00:00:00');

        $isActiveDay = $date->between($startActivePeriod, $endActivePeriod);

        if (empty($this->country_code)) {
            $isActive = $isActiveDay && $this->addresses()->where('countrycode', 'TR')->count() > 0;
        } else {
            $isActive = $isActiveDay && $this->country_code == 'TR';
        }

        return $isActive;
    }

    /**
     * @return float|int
     */
    public function getActiveQC()
    {
        $userStatisticQC = $this->userStatistic ? json_decode($this->userStatistic->current_month_qc, true) : [];

        if (!$userStatisticQC) {
            return 0;
        }

        return array_sum($userStatisticQC);
    }

    /**
     * @param int|null $rankLimit
     * @return float|int
     */
    public function getQualifyingQC($rankLimit = null)
    {
        $userStatisticQC = $this->userStatistic ? json_decode($this->userStatistic->current_month_qc, true) : [];

        if (!$userStatisticQC) {
            return 0;
        }

        $this->applyRankLimits($userStatisticQC, $this->getRankLimit($rankLimit));

        return array_sum($userStatisticQC);
    }

    /**
     * @param int|null $rankLimit
     * @return array
     */
    public function getTopQCLegs($rankLimit = null)
    {
        $userStatisticQC = $this->userStatistic ? json_decode($this->userStatistic->current_month_qc, true) : [];

        if (!$userStatisticQC) {
            return [];
        }

        if (!$rankLimit) {
            $this->applyRankLimits($userStatisticQC, $this->getRankLimit());
        } else {
            $this->applyRankLimits($userStatisticQC, $rankLimit);
        }

        arsort($userStatisticQC);

        $i = 0;
        $result = [];

        foreach ($userStatisticQC as $key => $item) {
            if ($i >= AchievedRankService::TOP_LEG_COUNT) {
                break;
            }

            $result[$i]['total'] = number_format($item, 2);
            $user = User::where('distid', $key)->first();

            if ($user) {
                $result[$i]['user_id'] = $user->id;
                $result[$i]['firstname'] = $user->firstname;
                $result[$i]['lastname'] = $user->lastname;
            }

            $i++;
        }

        return $result;
    }

    /**
     * @param $currentRank
     * @return float|int
     */
    public function getRankLimit($currentRank = null)
    {
        if (!$currentRank) {
            $currentRank = $this->current_month_rank;
        }

        $rank = DB::table('rank_definition')
            ->where('rankval', '>', $currentRank)
            ->orderBy('rankval', 'asc')
            ->first();

        if (!$rank) {
            return 0;
        }

        return $rank->min_qc * $rank->qc_percent;
    }

    /**
     * @param $userStatisticQC
     * @param $limit
     */
    public function applyRankLimits(&$userStatisticQC, $limit)
    {
        foreach ($userStatisticQC as $key => $value) {
            if ($value > $limit) {
                $userStatisticQC[$key] = $limit;
            }
        }
    }

    /**
     * @return array
     */
    public function getBinaryQualifiedValues()
    {
        $rootNode = BinaryPlanManager::getRootBinaryNode($this);

        if (!$rootNode) {
            return [
                'left' => 0,
                'right' => 0,
            ];
        }

        $leftLeg = BinaryPlanManager::getLeftLeg($rootNode);
        $rightLeg = BinaryPlanManager::getRightLeg($rootNode);

        if (!$leftLeg) {
            $left = 0;
        } else {
            $activeLeft = BinaryPlanManager::getActiveUserCount($leftLeg->_lft, $leftLeg->_rgt, $this);
            $left = $activeLeft[0]->count;
        }

        if (!$rightLeg) {
            $right = 0;
        } else {
            $activeRight = BinaryPlanManager::getActiveUserCount($rightLeg->_lft, $rightLeg->_rgt, $this);
            $right = $activeRight[0]->count;
        }

        return [
            'left' => $left,
            'right' => $right,
        ];
    }

    /**
     * @return array
     */
    public function getPersonallyEnrolledCount()
    {

        $count = DB::select("select
        (select count(*) from users u
        join binary_plan bp on u.id=bp.user_id
        where u.sponsorid='" . Auth::user()->distid . "' and direction='R') as right,
        (select count(*) from users u
        join binary_plan bp on u.id=bp.user_id
        where u.sponsorid='" . Auth::user()->distid . "' and direction='L') as left");

        return $count[0];

        /*return [
            'left' => $left,
            'right' => $right,
        ];*/
    }

    /**
     * @return RankDefinition|null
     */
    public function getPaidRank()
    {
        $now = Carbon::now();
        $rankId = $this->getCommissionRank($now);

        return RankDefinition::find($rankId) ?? RankDefinition::find(RankInterface::RANK_AMBASSADOR);
    }


    public static function updateAccountStatusByUserId($userId, $accountStatus)
    {
        DB::table('users')
            ->where('id', $userId)
            ->update([
                'account_status' => $accountStatus
            ]);
    }

    /**
     * @return int
     */
    public function hasActiveCreditCard()
    {
        return  PaymentMethod::query()
            ->where('userID', $this->id)
            ->where(function (Builder $query) {
                $query->whereNull('is_deleted')
                    ->orWhere('is_deleted', 0);
            })
            ->whereIn('pay_method_type', PaymentMethod::$creditCards)
            ->count();
    }

    public static function getProfilePicture($id)
    {
        return DB::table('users')
            ->select('profile_image_url')
            ->where('id', $id)
            ->first();
    }

    public static function setProfilePicture($id, $profile_image_url)
    {
        $user = User::find($id);
        $user_old = clone $user;

        $user->profile_image_url = $profile_image_url;
        $user->save();

        $userActivityLog = new UserActivityLog;
        $row = UserSettings::getByUserId(Auth::user()->id);
        $response = GeoIP::getInformationFromIP($row->current_ip);
        $userActivityLog->ip_address = $row->current_ip;
        $userActivityLog->user_id = Auth::user()->id;
        $userActivityLog->ip_details = $response;
        $userActivityLog->action = "UPDATE user Profile Picture";
        $userActivityLog->old_data = json_encode($user_old);
        $userActivityLog->new_data = json_encode($user);
        $userActivityLog->save();
    }

    /*
    * Load monthly projected qv information for the dashboard
    */
    public static function getUserMonthQvTotals()
    {
        // $totals = DB::select("select sum(gt.cv) as total_cv, sum(qv) as total_qv, sum(qc) as total_qc
        // from get_distributors_tree_subscription('".Auth::user()->distid."') as gt
        // where gt.account_status='APPROVED'
        // and gt.subscription_product in (11,12,26,33);");

        $sql = "
            SELECT sum(cv) as total_cv, sum(qv) as total_qv, sum(qc) as total_qc
              FROM get_distributors_tree_subscription('" . Auth::user()->distid . "') AS dt
             WHERE dt.account_status='APPROVED'
               AND dt.subscription_product IN (11,12,26,33)
               AND date_part('day',original_subscription_date) <= 27";

        $totals = DB::select($sql);
        return $totals;
    }

    /* FIX IT
     * Load monthly projected qv information for the dashboard
     */
    public static function getUserMonthQvTotalsSeptember()
    {
        $sql = "
            SELECT sum(cv) as total_cv, sum(qv) as total_qv, sum(qc) as total_qc
              FROM get_distributors_tree_subscription('" . Auth::user()->distid . "') AS dt
             WHERE dt.account_status = 'APPROVED'
               AND EXISTS (
                    SELECT 1
                      FROM orders o
                     WHERE o.userid = dt.id
                       AND date_part('month', o.created_date) = 7
                    ) ";

        $totals = DB::select($sql);

        return $totals;
    }

    /* FIX IT
     * Load monthly qv already processed
     */
    public static function getUserMonthQvPaidSeptember()
    {
        //Getting the start and end of the month
        $month_start = Carbon::today()->firstOfMonth()->format('Y-m-d');
        $month_end   = Carbon::today()->endOfMonth()->format('Y-m-d');
        $day         = Carbon::today()->format('d');

        Log::info("Start - " . $month_start . " - end " . $month_end);

        //Get the sum of all records that should have been processed until now

        $sql = "
            SELECT sum(gt.cv) as proccessed_cv, sum(qv) as proccessed_qv, sum(qc) as proccessed_qc
              FROM (
                      SELECT *, EXTRACT(DAY FROM original_subscription_date) as sub_day
                        FROM get_distributors_tree_subscription('" . Auth::user()->distid . "')
                   ) AS gt
             WHERE gt.account_status = 'APPROVED'
               AND EXISTS (
                       SELECT 1
                         FROM orders o
                   INNER JOIN \"orderItem\" oi on oi.orderid = o.id
                          AND productid IN (11, 12, 26, 33, 72, 80, 81, 82, 83)
                        WHERE o.userid = gt.id
                          AND date_part('month', o.created_date) = 9
                   ) ";

        $processed = DB::select($sql);

        Log::info("processed cv_processed " . $processed[0]->proccessed_cv);
        $response['cv_processed'] = 0;
        $response['qv_processed'] = 0;
        $response['qc_processed'] = 0;
        $response['cv_success'] = 0;
        $response['qv_success'] = 0;
        $response['qc_success'] = 0;
        $response['cv_fail']    = 0;
        $response['qv_fail']    = 0;
        $response['qc_fail']    = 0;

        if ($processed) {
            $response['cv_processed'] = $processed[0]->proccessed_cv;
            $response['qv_processed'] = $processed[0]->proccessed_qv;
            $response['qc_processed'] = $processed[0]->proccessed_qc;
        }

        $day = Carbon::today()->format('d');
        //Get the sum of all records that we can find orders
        $success = DB::select("select sum(gt.cv) as success_cv, sum(gt.qv) as success_qv, sum(gt.qc) as success_qc
            FROM (
                SELECT *, EXTRACT(DAY FROM original_subscription_date) as sub_day FROM get_distributors_tree_subscription('" . Auth::user()->distid . "')
            ) AS gt
            join orders ord on ord.userid=gt.id
            join \"orderItem\" oi on ord.id=oi.orderid
            where gt.account_status='APPROVED'
            and ord.created_date between '2020-03-01' and '2020-03-31'
            and oi.productid in (11,12,26,33)
            and sub_day<=" . $day);

        Log::info("success", $success);
        if ($success) {
            $response['cv_success'] = $success[0]->success_cv;
            $response['qv_success'] = $success[0]->success_qv;
            $response['qc_success'] = $success[0]->success_qc;
        }

        if ($processed || $success) {
            $response['cv_fail']    = $processed[0]->proccessed_cv - $success[0]->success_cv;
            $response['qv_fail']    = $processed[0]->proccessed_qv - $success[0]->success_qv;
            $response['qc_fail']    = $processed[0]->proccessed_qc - $success[0]->success_qc;
        }

        return $response;
    }

    /*
    * Load monthly qv already processed
    */
    public static function getUserMonthQvPaid()
    {
        //Getting the start and end of the month
        $month_start = Carbon::today()->firstOfMonth()->format('Y-m-d');
        $month_end   = Carbon::today()->endOfMonth()->format('Y-m-d');
        $day         = Carbon::today()->format('d');

        Log::info("Start - " . $month_start . " - end " . $month_end);

        //Get the sum of all records that should have been processed until now

        $sql = "
            SELECT sum(gt.cv) as proccessed_cv, sum(qv) as proccessed_qv, sum(qc) as proccessed_qc
              FROM (
                      SELECT *, EXTRACT(DAY FROM original_subscription_date) as sub_day
                        FROM get_distributors_tree_subscription('" . Auth::user()->distid . "')
                   ) AS gt
             WHERE gt.account_status='APPROVED'
               AND gt.subscription_product in (11,12,26,33)
               AND sub_day <= " . $day;

        $processed = DB::select($sql);

        Log::info("processed cv_processed " . $processed[0]->proccessed_cv);
        $response['cv_processed'] = 0;
        $response['qv_processed'] = 0;
        $response['qc_processed'] = 0;
        $response['cv_success'] = 0;
        $response['qv_success'] = 0;
        $response['qc_success'] = 0;
        $response['cv_fail']    = 0;
        $response['qv_fail']    = 0;
        $response['qc_fail']    = 0;

        if ($processed) {
            $response['cv_processed'] = $processed[0]->proccessed_cv;
            $response['qv_processed'] = $processed[0]->proccessed_qv;
            $response['qc_processed'] = $processed[0]->proccessed_qc;
        }

        $day = Carbon::today()->format('d');
        //Get the sum of all records that we can find orders
        $success = DB::select("select sum(gt.cv) as success_cv, sum(gt.qv) as success_qv, sum(gt.qc) as success_qc
            FROM (
                SELECT *, EXTRACT(DAY FROM original_subscription_date) as sub_day FROM get_distributors_tree_subscription('" . Auth::user()->distid . "')
            ) AS gt
            join orders ord on ord.userid=gt.id
            join \"orderItem\" oi on ord.id=oi.orderid
            where gt.account_status='APPROVED'
            and ord.created_date between '2020-03-01' and '2020-03-31'
            and oi.productid in (11,12,26,33)
            and sub_day<=" . $day);

        Log::info("success", $success);
        if ($success) {
            $response['cv_success'] = $success[0]->success_cv;
            $response['qv_success'] = $success[0]->success_qv;
            $response['qc_success'] = $success[0]->success_qc;
        }

        if ($processed || $success) {
            $response['cv_fail']    = $processed[0]->proccessed_cv - $success[0]->success_cv;
            $response['qv_fail']    = $processed[0]->proccessed_qv - $success[0]->success_qv;
            $response['qc_fail']    = $processed[0]->proccessed_qc - $success[0]->success_qc;
        }

        return $response;
    }

    /*
    * Load monthly qv already processed
    */
    public static function getUserMonthQvList()
    {

        $day            = Carbon::today()->format('d');
        $month_start    = Carbon::today()->firstOfMonth()->format('Y-m-d');
        $month_end      = Carbon::today()->format('Y-m-d');
        $processed = DB::select("
            SELECT
                concat(u.firstname,' ',u.lastname) as full_name,
                CASE
                    WHEN v.order_id IS NOT null THEN 'SUCCESS'
                    WHEN date_part('month',v.created_date) >= date_part('month',now()) THEN 'SUCCESS'
                    ELSE '---'
                END processed,
                CASE
                    WHEN v.order_id IS NOT null THEN v.created_date::varchar
                    ELSE concat(to_char(now(),'YYYY'),'-',to_char(now(),'MM'),'-',to_char(original_subscription_date,'DD'))
                END date_processed,
                *,
                v.created_date as user_created
            FROM (
                    SELECT
                        *
                    FROM get_distributors_tree_subscription('" . Auth::user()->distid . "') AS dt
                    WHERE dt.account_status='APPROVED'
                    AND dt.subscription_product IN (11, 12, 26, 33, 72, 80, 81, 82, 83)
                    -- AND date_part('day',original_subscription_date) <= " . $day . "
                    AND dt.original_subscription_date between '2020-09-01' and '2020-10-31'
                ) AS u
            LEFT JOIN (
                    SELECT * FROM vorder_orderitem_widget v
                    WHERE v.product_id IN (11, 12, 26, 33, 72, 80, 81, 82, 83)
                    -- AND v.created_date BETWEEN '" . $month_start . "T00:00:00' AND '" . $month_end . "T23:59:59'
                    AND v.created_date BETWEEN '2020-09-01 T00:00:00' AND '2020-10-31 T23:59:59'
                    ) AS v
            ON v.user_id = u.id");
        return $processed;
    }

    public static function getUserForVibeApi($input)
    {

        return DB::table('users')
            ->select('id', 'distid', 'firstname', 'lastname', 'username', 'email', 'mobilenumber', 'sponsorid', 'current_product_id')
            // ->where('usertype', UserType::TYPE_DISTRIBUTOR)
            ->where('account_status', self::ACC_STATUS_APPROVED)
            ->where(function ($query) use ($input) {
                $query->when(!empty($input['email']), function ($query) use ($input) {
                    return $query->where('email', $input['email']);
                });
                $query->when(!empty($input['mobile']), function ($query) use ($input) {
                    $mobile = preg_replace('/[^0-9]|^1/', '', $input['mobile']);
                    return $query->orWhereRaw("REGEXP_REPLACE(mobilenumber, '[^0-9]|^1', '', 'g') = ?", [$mobile]);
                });
                $query->when(!empty($input['distid']), function ($query) use ($input) {
                    return $query->orWhere('distid', $input['distid']);
                });
            })
            ->first();
    }

    public static function updateRemeberToken($id, $token)
    {

        $user = User::find($id);

        if (!$user->remember_token) {

            $user->remember_token = $token;
            $user->save();

            return $token;
        } else {
            return $user->remember_token;
        }
    }

    public static function getUserPaymentMethods($user)
    {
        $paymentMethods = PaymentMethod::getAllRec($user->id);

        $pMDrop = array();

        $ignoredPaymentMethods = [
            PaymentMethodType::TYPE_ADMIN,
            PaymentMethodType::TYPE_BITPAY,
            PaymentMethodType::TYPE_SKRILL,
            PaymentMethodType::TYPE_COUPON_CODE
        ];

        foreach ($paymentMethods as $p) {
            $selected = 0;
            if ($p->is_deleted == true || in_array($p->pay_method_type, $ignoredPaymentMethods)) {
                continue;
            }

            if ($p->pay_method_type == PaymentMethodType::TYPE_E_WALET) {
                $paymentMethodName = 'E-WALLET';
            } else {
                if (empty($p->token)) {
                    continue;
                }

                $paymentMethodName = PaymentMethod::getFormatedCardNo($p->token);
            }

            if ($user->subscription_payment_method_id == $p->id) {
                $selected = 1;
            }

            if (!empty($paymentMethodName)) {
                $pMDrop[] = [
                    'id' => $p->id,
                    'paymentMethodName' => $paymentMethodName,
                    'expiry_date' => $p->expMonth . '/' . $p->expYear,
                    'name'      => $p->firstname . ' ' . $p->lastname,
                    'primary' => $p->primary,
                    'billingAddress' => $p->billingAddress
                ];
            }
        }
        return $pMDrop;
    }


    public static function getTerminatedForRank()
    {
        return DB::table('users')
            ->select('id', 'distid', 'sponsorid')
            ->whereNotIn('account_status', ['APPROVED'])
            ->get();
    }

    public static function getRankUsers()
    {
        return DB::table('users')
            ->select('id', 'distid', 'sponsorid')
            ->whereBetween('id', [8360, 8370])
            ->get();
    }

    public static function getPersonallyEnrolledActive($userId, $start, $end)
    {
        $processed = DB::select("select bp.direction, count(*) from binary_plan bp
        join orders o on o.userid=bp.user_id
        where sponsor_id=" . $userId . "
        and o.orderqv>=100
        and o.created_date between '" . $start . "' and '" . $end . "'
        group by bp.direction");

        return $processed;
    }

    public static function getRankRQVLimit($userId, $end)
    {
        return DB::select("select * from get_rank_limit_for_sponsored_legs(" . $userId . ", '" . $end . "');");
    }

    public static function getQualifiedRankLimits($userId, $end)
    {

        $paid_rank_id = DB::select("SELECT COALESCE(MAX(rd.id), 0) as rank_id
    	FROM rank_history rh
        JOIN rank_definition rd
        ON rh.lifetime_rank = rd.rankval
        WHERE rh.users_id = '" . $userId . "'
        AND created_dt >= date_trunc('month', date('" . $end . "')) - interval '1 month'
        AND created_dt <= '" . $end . "'");

        $limits = DB::select("SELECT min_qv, min_qc, rank_limit
        FROM rank_definition WHERE id > " . $paid_rank_id[0]->rank_id . " ORDER BY id LIMIT 1");

        return $limits;
    }

    public static function getRootUserPQV($distid, $start, $end)
    {
        return DB::select("select * from calculate_pqv('" . $distid . "', '" . $start . "', '" . $end . "')");
    }

    public function userPaymentMethods()
    {
        return $this->hasMany(UserPaymentMethod::class);
    }

    public function userPaymentAddress()
    {
        return $this->hasMany(UserPaymentAddress::class);
    }

    public static function getUserPrimaryAddress($user)
    {
        return Address::getRec($user->id, Address::TYPE_REGISTRATION, 1);
    }

    public static function getUserShippingAddress($user)
    {
        return Address::getRec($user->id, Address::TYPE_SHIPPING, 1);
    }

    public static function deleteUserShippingAddress($user, $addressId)
    {
        try {
            return Address::deleteAddress($user->id, $addressId);
        } catch (\Exception $exception) {
            return null;
        }
    }

    public static function getRootUserPQC($distid, $start, $end)
    {
        return DB::select("select * from calculate_pqc('" . $distid . "', '" . $start . "', '" . $end . "')");
    }


    public static function activateUnicryptAccount($user_id)
    {
        $user = self::where('id', $user_id)->first();
        if ($user->account_status == 'PENDING APPROVAL') {
            $user->account_status = 'APPROVED';
            $user->save();
        }
    }

    public function loungeQueue()
    {
        return $this->hasMany('App\Models\LoungeQueue', 'user_id', 'id');
    }

    public static function checkIfUsersInTree($userId)
    {
        return DB::table('bucket_tree_plan')->where('uid', $userId)->get();
    }
}
