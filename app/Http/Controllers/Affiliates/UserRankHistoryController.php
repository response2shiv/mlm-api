<?php

namespace App\Http\Controllers\Affiliates;
use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use App\Models\UserRankHistory;
use Auth;

class UserRankHistoryController extends Controller
{
    /**
     * Get rank insights for the dashboard
     *
     */
    public function getRankValues() {
        $req = request();
        $rank = $req->rank - 10;
        $qv = UserRankHistory::getQV(Auth::user()->distid, $rank);
        $tsa = UserRankHistory::getTSA(Auth::user()->distid, $rank);
        //
        $rank_matric = UserRankHistory::getRankMatrics(Auth::user()->distid, $rank);
        $qualification = "-";
        $current_monthly_qv = 0;
        if ($rank_matric != null) {
            $qualification = strtoupper($rank_matric->nextlevel_rankdesc);
            $current_monthly_qv = number_format($rank_matric->nextlevel_qv);
            $active_tsa_needed = number_format($rank_matric->nextlevel_tsa);
            $percentage = $rank_matric->nextlevel_percentage;
            $qcVolume = $rank_matric->nextlevel_qc;
            $qcPercent = $rank_matric->next_qc_percentage;
            $binaryCount = $rank_matric->binary_limit;
        }

        $limit = Auth::user()->getRankLimit($rank);
        $qcTopUsers = Auth::user()->getTopQCLegs($limit);

        // top 3 contributors
        $d['contributors'] = UserRankHistory::getTopContributors(Auth::user()->distid, $rank);
        $d['font'] = ['brand', 'success', 'info', 'warning', 'danger'];
        // $v_contributors = (string) view('affiliate.dashboard.top_contributors')->with($d);
        // $qc_contributors = (string) view('affiliate.dashboard.top_qc_contributors')->with([
        //     'qcContributors' => $qcTopUsers,
        //     'limit' => $limit,
        //     'font' => $d['font'],
        // ]);

        $v_contributors = $d;
        $qc_contributors = [
            'qcContributors' => $qcTopUsers,
            'limit' => $limit,
            'font' => $d['font'],
        ];
        //
        $response = ['error' => '0',
                    'qv' => number_format($qv),
                    'tsa' => $tsa,
                    'qualification' => $qualification,
                    'current_monthly_qv' => $current_monthly_qv,
                    'active_tsa_needed' => $active_tsa_needed,
                    'active_qc' => number_format(Auth::user()->getActiveQC(), 2),
                    'percentage' => $percentage,
                    'v_contributors' => $v_contributors,
                    'qc_contributors' => $qc_contributors,
                    'qc_volume' => number_format($qcVolume),
                    'qc_percent' => $qcPercent,
                    'binary_count' => $binaryCount,
                    'qualifying_qc' => number_format(Auth::user()->getQualifyingQC($rank), 2),
        ];

        $this->setResponseCode(200);
        $this->setResponse($response);
        return $this->showResponse();
    }
}
