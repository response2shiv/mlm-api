<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class UserBucketVolume extends Model
{

    protected $fillable = [
        'user_id',
        'bv_a',
        'bv_b',
        'bv_c',
        'week_no',
        'start_of_week',
        'end_of_week',
        'cv',
        'qv',
        'pv',
        'pev',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function getUsersInReverseTree($user_id)
    {
        info("Get Tree of User:" . $user_id);

        $query = "WITH RECURSIVE bucket_tree AS (
            SELECT
                tid,
                uid,
                sid,
                pid,
                ptid,
                stid,
                pbid,
                sbid
            FROM
                bucket_tree_plan
            WHERE
                uid = $user_id
            UNION
                SELECT
                    bt.tid,
                    bt.uid,
                    bt.sid,
                    bt.pid,
                    bt.ptid,
                    bt.stid,            
                    bt.pbid,
                    bt.sbid
                FROM
                    bucket_tree_plan bt
                INNER JOIN bucket_tree t ON t.ptid = bt.tid
        ) SELECT
            u.distid,
            u.firstname,
            u.lastname,
            u.id,
            bt.*
        FROM
            bucket_tree bt
        JOIN users as u on u.id = bt.uid
        ORDER BY bt.tid DESC;";

        return DB::select(DB::raw($query));
    }

    public static function getUserBucketVolumes($user_id)
    {
        $end_date = now();
        $start_date = now()->subWeeks(4);

        $result = UserBucketVolume::where('user_id', $user_id)
            ->select(DB::raw('sum(bv_a) as bv_a ,sum(bv_b) as bv_b, sum(bv_c) as bv_c , sum(total_bv) as total'))
            ->whereBetween('start_of_week', [$start_date->format('Y-m-d'), $end_date->format('Y-m-d')]);


        return $result->get();
    }

    public static function sponsorshipUsersTree($sponsor_id)
    {
        $query = "WITH RECURSIVE bucket_tree AS (
            SELECT
                tid,
                uid,
                sid,
                pid,
            pbid,
            sbid
            FROM
                bucket_tree_plan
            WHERE
                uid = $sponsor_id
            UNION
                SELECT
                    bt.tid,
                    bt.uid,
                    bt.sid,
                    bt.pid,
              bt.pbid,
              bt.sbid
                FROM
                    bucket_tree_plan bt
                INNER JOIN bucket_tree t ON t.uid = bt.sid
        ) SELECT
            u.distid,
            u.firstname,
            u.lastname,
            bt.*,
            ubv.pv
        FROM
            bucket_tree bt
        JOIN users as u on u.id = bt.uid
        join user_bucket_volumes ubv on ubv.user_id = u.id 
        ORDER BY bt.tid;";

        return DB::select(DB::raw($query));
    }

    public static function getCalculatePEV($user_id)
    {
        $query = "WITH RECURSIVE bucket_tree AS (
            SELECT
                tid,
                uid,
                sid,
                pid,
            pbid,
            sbid
            FROM
                bucket_tree_plan
            WHERE
                uid = $user_id
            UNION
                SELECT
                    bt.tid,
                    bt.uid,
                    bt.sid,
                    bt.pid,
              bt.pbid,
              bt.sbid
                FROM
                    bucket_tree_plan bt
                INNER JOIN bucket_tree t ON t.uid = bt.sid
        ) SELECT
            u.distid,
            u.firstname,
            u.lastname,
            bt.*,
            ubv.pv
        FROM
            bucket_tree bt
        JOIN users as u on u.id = bt.uid
        join user_bucket_volumes ubv on ubv.user_id = u.id 
        ORDER BY bt.tid;";
    }

    public static function getUserBucketCurrentWeek($user_id)
    {
        $start_date = now()->startOfWeek();
        $end_date = now()->endOfWeek();

        $result = UserBucketVolume::where('user_id', $user_id)
            ->select(DB::raw('sum(bv_a) as bv_a ,sum(bv_b) as bv_b, sum(bv_c) as bv_c , sum(total_bv) as total'))
            ->whereBetween('start_of_week', [$start_date->format('Y-m-d'), $end_date->format('Y-m-d')]);

        // dd($result->toSql(), $result->getBindings());
        return $result->get();
    }

    public static function countDistribuitors()
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

        return [
            'a' => $aISBO,
            'b' => $bISBO,
            'c' => $cISBO,
            'total' => $aISBO + $bISBO + $cISBO
        ];
    }
}
