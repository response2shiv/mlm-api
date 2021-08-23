<?php

namespace App\Http\Controllers\Affiliates;
use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use App\Models\BinaryPlanNode;
use App\Models\User;
use App\Facades\BinaryPlanManager;
use App\Facades\HoldingTank;
use App\Models\Product;
use App\Models\RankInterface;
use App\Helpers\Util;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Auth;

class BinaryViewerController extends Controller
{
    const BINARY_LIST_ITEMS = 10;
    const BINARY_SEARCH_ITEMS = 10;
    const INIT_LIMIT = 5;

    private function addExtraInformationToNode($node){
        $node->user->current_active_status = $node->user->getCurrentActiveStatus();
        $node->user->rank = $node->user->rank();
        $node->user->paid_rank = $node->user->getPaidRank();
        $node->user->binary_paid_percent = $node->user->getBinaryPaidPercent();
        $node->enrollment_date = $node->getEnrollmentDate();

        if(!empty($node->user->sponsor())){
            $node->user->sponsor = $node->user->sponsor;
        }

        return $node;
    }

    /**
     * Get the initial binary viewer report
     *
     */
    public function getBinaryViewerData($id = null)
    {
        //
        HoldingTank::getOrCreateRootNode(Auth::user());
        $rootNode = BinaryPlanManager::getRootBinaryNode(Auth::user());
        if ($id !== null) {
            $idInTree = DB::select("WITH RECURSIVE bucket_tree AS ("
                ."    SELECT tid, uid, sid, pid, pbid, sbid"
                ."    FROM bucket_tree_plan"
                ."    WHERE uid = ".$rootNode->user_id
                ."    UNION SELECT bt.tid, bt.uid, bt.sid, bt.pid, bt.pbid, bt.sbid"
                ."    FROM bucket_tree_plan bt"
                ."    INNER JOIN bucket_tree t ON t.uid = bt.pid"
                ." ) SELECT u.distid, u.firstname, u.lastname, bt.*"
                ." FROM bucket_tree bt"
                ." JOIN users AS u ON bt.uid = u.id"
                ." WHERE bt.uid = ".$id);
            if(count($idInTree) == 0){
                return redirect()->route('binaryViewer');
            }
            $existingUser = User::getById($id);
            HoldingTank::getOrCreateRootNode($existingUser);
            $currentNode = BinaryPlanManager::getRootBinaryNode($existingUser);
            $parentId = DB::select("select uid from bucket_tree_plan where auid = '".$id."' OR buid = '".$id."' OR cuid = '".$id."'");
            if(count($parentId) > 0){
                $currentNode->parent_node_id = $parentId[0]->uid;
            }else{
                $currentNode->parent_node_id = null;    
            }        
            if (!$currentNode) {
                return redirect()->route('binaryViewer');
            }
        } else {
            $currentNode = $rootNode;
            $currentNode->parent_node_id = null;
        }
        $currentNode->id = $currentNode->user_id;
        $mondayDate = Carbon::now(Util::USER_TIME_ZONE)->startOfWeek()->toDateTimeString();

        $mainTid = 0;

        $currentNoteTids = DB::select("select * from bucket_tree_plan where uid = '".$currentNode->user_id."'");
        
        $leftLeg = null;
        $centerLeg = null;
        $rightLeg = null;
        if(count($currentNoteTids) > 0){
            if(isset($currentNoteTids[0]->auid)){
                $leftLeg = new BinaryPlanNode;
                $leftLeg->user = User::find($currentNoteTids[0]->auid);
                $leftLeg->id = $currentNoteTids[0]->auid;
            }    
            if(isset($currentNoteTids[0]->buid)){
                $centerLeg = new BinaryPlanNode;
                $centerLeg->user = User::find($currentNoteTids[0]->buid);
                $centerLeg->id = $currentNoteTids[0]->buid;
            }    
            if(isset($currentNoteTids[0]->cuid)){
                $rightLeg = new BinaryPlanNode;
                $rightLeg->user = User::find($currentNoteTids[0]->cuid);
                $rightLeg->id = $currentNoteTids[0]->cuid;
            }    
        }

        //$leftLeg = BinaryPlanManager::getLeftLeg($currentNode);
        //$rightLeg = BinaryPlanManager::getRightLeg($currentNode);
        $currentLeftAmount = 0;
        $currentRightAmount = 0;

        if ($leftLeg) {
            //$currentLeftAmount = BinaryPlanManager::getNodeTotal($leftLeg, $mondayDate);
        }

        if ($rightLeg) {
            //$currentRightAmount = BinaryPlanManager::getNodeTotal($rightLeg, $mondayDate);
        }

        $lastRight = BinaryPlanManager::getLastRightNode($currentNode);
        $lastLeft = BinaryPlanManager::getLastLeftNode($currentNode);

        $distributors = $distributorsEnd = '[]';
        $distCount = 0;

        if ($currentNode->user_id != Auth::user()->id) {
            $distributors = BinaryPlanManager::getDistributorsInTree(
                Auth::user(),
                self::BINARY_LIST_ITEMS,
                0,
                null,
                $id ? $currentNode : null
            );

            $distCount = BinaryPlanManager::getDistributorsCount(Auth::user(), null, $id ? $currentNode : null);

            if (($offset = $distCount - count($distributors)) > 0) {
                $distributorsEnd = BinaryPlanManager::getDistributorsInTree(
                    Auth::user(),
                    self::BINARY_LIST_ITEMS,
                    $offset,
                    null,
                    $id ? $currentNode : null
                );
            }
        }

        $currentNode = $this->addExtraInformationToNode($currentNode);

        // $currentNode->user->current_active_status = $currentNode->user->getCurrentActiveStatus();
        // $currentNode->user->rank = $currentNode->user->rank();
        // $currentNode->user->paid_rank = $currentNode->user->getPaidRank();
        // $currentNode->user->binary_paid_percent = $currentNode->user->getBinaryPaidPercent();
        //$currentNode->user->full_name = $currentNode->user->getFullName();
        // if(empty($currentNode->user->sponsor())){
        //     $currentNode->user->sponsor = $currentNode->user->sponsor();
        // }
      
        //$currentNode->enrollment_date = $currentNode->getEnrollmentDate();
        // $currentNode->legs = [
        //     'left' => $currentNode->getLeftLeg(),
        //     'right' => $currentNode->getRightLeg(),
        // ];


        $n2 = !empty($leftLeg) ? $leftLeg : null;
        $n3 = !empty($rightLeg) ? $rightLeg : null;
        $nc = !empty($centerLeg) ? $centerLeg : null;
        $l1Nodes = [$n2, $nc, $n3];

        foreach($l1Nodes as $node){
            if(isset($node)){
                $node = $this->addExtraInformationToNode($node);
            } 
        }

        $n4 = null;
        $nlc = null;
        $n5 = null;
        if($leftLeg != null){
            $currentNoteTidL = DB::select("select * from bucket_tree_plan where uid = '".$leftLeg->id."'");
            if(count($currentNoteTidL) > 0){
                if(isset($currentNoteTidL[0]->auid)){
                    $n4 = new BinaryPlanNode;
                    $n4->user = User::find($currentNoteTidL[0]->auid);
                    $n4->id = $currentNoteTidL[0]->auid;
                }    
                if(isset($currentNoteTidL[0]->buid)){
                    $nlc = new BinaryPlanNode;
                    $nlc->user = User::find($currentNoteTidL[0]->buid);
                    $nlc->id = $currentNoteTidL[0]->buid;
                }    
                if(isset($currentNoteTidL[0]->cuid)){
                    $n5 = new BinaryPlanNode;
                    $n5->user = User::find($currentNoteTidL[0]->cuid);
                    $n5->id = $currentNoteTidL[0]->cuid;
                }    
            }
        }

        $ncl = null;
        $ncc = null;
        $ncr = null;
        if($centerLeg != null){
            $currentNoteTidC = DB::select("select * from bucket_tree_plan where uid = '".$centerLeg->id."'");
            if(count($currentNoteTidC) > 0){
                if(isset($currentNoteTidC[0]->auid)){
                    $ncl = new BinaryPlanNode;
                    $ncl->user = User::find($currentNoteTidC[0]->auid);
                    $ncl->id = $currentNoteTidC[0]->auid;
                }    
                if(isset($currentNoteTidC[0]->buid)){
                    $ncc = new BinaryPlanNode;
                    $ncc->user = User::find($currentNoteTidC[0]->buid);
                    $ncc->id = $currentNoteTidC[0]->buid;
                }    
                if(isset($currentNoteTidC[0]->cuid)){
                    $ncr = new BinaryPlanNode;
                    $ncr->user = User::find($currentNoteTidC[0]->cuid);
                    $ncr->id = $currentNoteTidC[0]->cuid;
                }    
            }
        }
        
        $n6 = null;
        $nrc = null;
        $n7 = null;
        if($rightLeg != null){
            $currentNoteTidR = DB::select("select * from bucket_tree_plan where uid = '".$rightLeg->id."'");
            if(count($currentNoteTidR) > 0){
                if(isset($currentNoteTidR[0]->auid)){
                    $n6 = new BinaryPlanNode;
                    $n6->user = User::find($currentNoteTidR[0]->auid);
                    $n6->id = $currentNoteTidR[0]->auid;
                }    
                if(isset($currentNoteTidR[0]->buid)){
                    $nrc = new BinaryPlanNode;
                    $nrc->user = User::find($currentNoteTidR[0]->buid);
                    $nrc->id = $currentNoteTidR[0]->buid;
                }    
                if(isset($currentNoteTidR[0]->cuid)){
                    $n7 = new BinaryPlanNode;
                    $n7->user = User::find($currentNoteTidR[0]->cuid);
                    $n7->id = $currentNoteTidR[0]->cuid;
                }    
            }
        }

        $l2Nodes = [$n4, $nlc, $n5, $ncl, $ncc, $ncr, $n6, $nrc, $n7];

        foreach($l2Nodes as $node){
            if(isset($node)){
                $node = $this->addExtraInformationToNode($node);
            }
        }

        // End of tree

        $n8 = !empty($n4) ? $n4->getLeftLeg() : null;
        $n9 = !empty($n4) ? $n4->getRightLeg() : null;
        $n10 = !empty($n5) ? $n5->getLeftLeg() : null;
        $n11 = !empty($n5) ? $n5->getRightLeg() : null;
        $l3Nodes1 = [$n8, $n9, $n10, $n11];
        
        foreach($l3Nodes1 as $node){
            if(isset($node)){
                $node = $this->addExtraInformationToNode($node);
            }
        }

        $n12 = !empty($n6) ? $n6->getLeftLeg() : null;
        $n13 = !empty($n6) ? $n6->getRightLeg() : null;
        $n14 = !empty($n7) ? $n7->getLeftLeg() : null;
        $n15 = !empty($n7) ? $n7->getRightLeg() : null;
        $l3Nodes2 = [$n12, $n13, $n14, $n15];

        foreach($l3Nodes2 as $node){
            if(isset($node)){
                $node = $this->addExtraInformationToNode($node);
            }
        }

        // START VOLUME CALCULATION
        $aISBO = 0;
        $bISBO = 0;
        $cISBO = 0;
        /*$aISBOCount = DB::select("select * from get_bucket_pers_enrolled_count(".$currentNode->user_id.", 1)");
        $aISBO = count($aISBOCount); 
        
        $bISBOCount =  DB::select("select * from get_bucket_pers_enrolled_count(".$currentNode->user_id.", 2)");
        $bISBO = count($bISBOCount);
        
        $cISBOCount =  DB::select("select * from get_bucket_pers_enrolled_count(".$currentNode->user_id.", 3)");
        $cISBO = count($cISBOCount);*/
        if(count($currentNoteTids) > 0){
            if(isset($currentNoteTids[0]->auid)){
                $aISBOCount = DB::select("WITH RECURSIVE bucket_tree AS ("
                    ."    SELECT tid, uid, sid, pid, pbid, sbid "
                    ."    FROM bucket_tree_plan "
                    ."    WHERE uid = '".$currentNoteTids[0]->auid."' "
                    ."    UNION SELECT bt.tid, bt.uid, bt.sid, bt.pid, bt.pbid, bt.sbid "
                    ."    FROM bucket_tree_plan bt INNER JOIN bucket_tree T ON T.uid = bt.pid "
                    ."    ) SELECT count(*) "
                    ."    FROM bucket_tree bt JOIN users AS u ON bt.uid = u.ID");
                if(count($aISBOCount) > 0){
                    if(isset($aISBOCount[0]->count)){
                        $aISBO = $aISBOCount[0]->count; 
                    }
                }        
            }
            if(isset($currentNoteTids[0]->buid)){
                $bISBOCount =  DB::select("WITH RECURSIVE bucket_tree AS ("
                ."    SELECT tid, uid, sid, pid, pbid, sbid "
                ."    FROM bucket_tree_plan "
                ."    WHERE uid = '".$currentNoteTids[0]->buid."' "
                ."    UNION SELECT bt.tid, bt.uid, bt.sid, bt.pid, bt.pbid, bt.sbid "
                ."    FROM bucket_tree_plan bt INNER JOIN bucket_tree T ON T.uid = bt.pid "
                ."    ) SELECT count(*) "
                ."    FROM bucket_tree bt JOIN users AS u ON bt.uid = u.ID");
                if(count($bISBOCount) > 0){
                    if(isset($bISBOCount[0]->count)){
                        $bISBO = $bISBOCount[0]->count; 
                    }
                }        
            }
            if(isset($currentNoteTids[0]->cuid)){
                $cISBOCount =  DB::select("WITH RECURSIVE bucket_tree AS ("
                ."    SELECT tid, uid, sid, pid, pbid, sbid "
                ."    FROM bucket_tree_plan "
                ."    WHERE uid = '".$currentNoteTids[0]->cuid."' "
                ."    UNION SELECT bt.tid, bt.uid, bt.sid, bt.pid, bt.pbid, bt.sbid "
                ."    FROM bucket_tree_plan bt INNER JOIN bucket_tree T ON T.uid = bt.pid "
                ."    ) SELECT count(*) "
                ."    FROM bucket_tree bt JOIN users AS u ON bt.uid = u.ID");
                if(count($cISBOCount) > 0){
                    if(isset($cISBOCount[0]->count)){
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
        $currentMonday = date("Y-m-d", strtotime("monday this week"));
        $currentSunday = date("Y-m-d", strtotime("sunday this week"));
        $volumeDetails = DB::select("SELECT * FROM user_bucket_volumes WHERE user_id = '".$currentNode->user_id."' AND start_of_week = '".$currentMonday."' AND end_of_week = '".$currentSunday."'");        
        if(count($volumeDetails) > 0){
            if(isset($volumeDetails[0]->bv_a)){
                $aCV = $volumeDetails[0]->bv_a;
                $aFourWV = $volumeDetails[0]->bv_a;
            }
            if(isset($volumeDetails[0]->bv_b)){
                $bCV = $volumeDetails[0]->bv_b;
                $bFourWV = $volumeDetails[0]->bv_b;
            }
            if(isset($volumeDetails[0]->bv_c)){
                $cCV = $volumeDetails[0]->bv_c;
                $cFourWV = $volumeDetails[0]->bv_c;
            }
            if(isset($volumeDetails[0]->pev)){
                $fourWeekPEV = $volumeDetails[0]->pev;
            }    
        }

        $mondayWeek2 = date("Y-m-d", strtotime('-7 Days', strtotime($currentMonday)));
        $sundayWeek2 = date("Y-m-d", strtotime('-7 Days', strtotime($currentSunday)));
        $volumeDetailsWeek2 = DB::select("SELECT * FROM user_bucket_volumes WHERE user_id = '".$currentNode->user_id."' AND start_of_week = '".$mondayWeek2."' AND end_of_week = '".$sundayWeek2."'");        
        if(count($volumeDetailsWeek2) > 0){
            if(isset($volumeDetailsWeek2[0]->bv_a)){
                $aFourWV += $volumeDetailsWeek2[0]->bv_a;
            }
            if(isset($volumeDetailsWeek2[0]->bv_b)){
                $bFourWV += $volumeDetailsWeek2[0]->bv_b;
            }
            if(isset($volumeDetailsWeek2[0]->bv_c)){
                $cFourWV += $volumeDetailsWeek2[0]->bv_c;
            }    
            if(isset($volumeDetailsWeek2[0]->pev)){
                $fourWeekPEV += $volumeDetailsWeek2[0]->pev;
            }    
        }

        $mondayWeek3 = date("Y-m-d", strtotime('-14 Days', strtotime($currentMonday)));
        $sundayWeek3 = date("Y-m-d", strtotime('-14 Days', strtotime($currentSunday)));
        $volumeDetailsWeek3 = DB::select("SELECT * FROM user_bucket_volumes WHERE user_id = '".$currentNode->user_id."' AND start_of_week = '".$mondayWeek3."' AND end_of_week = '".$sundayWeek3."'");        
        if(count($volumeDetailsWeek3) > 0){
            if(isset($volumeDetailsWeek3[0]->bv_a)){
                $aFourWV += $volumeDetailsWeek3[0]->bv_a;
            }
            if(isset($volumeDetailsWeek3[0]->bv_b)){
                $bFourWV += $volumeDetailsWeek3[0]->bv_b;
            }
            if(isset($volumeDetailsWeek3[0]->bv_c)){
                $cFourWV += $volumeDetailsWeek3[0]->bv_c;
            }    
            if(isset($volumeDetailsWeek3[0]->pev)){
                $fourWeekPEV += $volumeDetailsWeek3[0]->pev;
            }    
        }

        $mondayWeek4 = date("Y-m-d", strtotime('-21 Days', strtotime($currentMonday)));
        $sundayWeek4 = date("Y-m-d", strtotime('-21 Days', strtotime($currentSunday)));
        $volumeDetailsWeek4 = DB::select("SELECT * FROM user_bucket_volumes WHERE user_id = '".$currentNode->user_id."' AND start_of_week = '".$mondayWeek4."' AND end_of_week = '".$sundayWeek4."'");        
        if(count($volumeDetailsWeek4) > 0){
            if(isset($volumeDetailsWeek4[0]->bv_a)){
                $aFourWV += $volumeDetailsWeek4[0]->bv_a;
            }
            if(isset($volumeDetailsWeek4[0]->bv_b)){
                $bFourWV += $volumeDetailsWeek4[0]->bv_b;
            }
            if(isset($volumeDetailsWeek4[0]->bv_c)){
                $cFourWV += $volumeDetailsWeek4[0]->bv_c;
            }    
            if(isset($volumeDetailsWeek4[0]->pev)){
                $fourWeekPEV += $volumeDetailsWeek4[0]->pev;
            }    
        }

        // END VOLUME CALCULATION

        $user = $currentNode->user;

        $previousWeekTotal      = BinaryPlanManager::getPreviousWeekTotal($user);
        $previousWeekCarryOvers = BinaryPlanManager::getPreviousWeekCarryOvers($user);
        
        return response()->json([
            'rightCurrentWeek' => $currentRightAmount,
            'leftCurrentWeek' => $currentLeftAmount,
            'currentNode' => $currentNode,
            'l1Nodes' => $l1Nodes,
            'l2Nodes' => $l2Nodes,
            'l3Nodes1' => $l3Nodes1,
            'l3Nodes2' => $l3Nodes2,            
            'rootNode' => $rootNode,
            'aISBO' => $aISBO,
            'bISBO' => $bISBO,
            'cISBO' => $cISBO,
            'volumes' => [
                'aCV' => $aCV,
                'bCV' => $bCV,
                'cCV' => $cCV,
                'aFourWV' => $aFourWV,
                'bFourWV' => $bFourWV,
                'cFourWV' => $cFourWV,
                'fourWeekPEV' => $fourWeekPEV
            ],
            'legend' => [
                'left' => BinaryPlanManager::leftLegNodes($currentNode),
                'countLeft' => BinaryPlanManager::leftLegNodes($currentNode)->count(),
                'right' => BinaryPlanManager::rightLegNodes($currentNode),
                'countRight' => BinaryPlanManager::rightLegNodes($currentNode)->count(),
            ],
            'distributors' => $distributors,
            'distributorsEnd' => $distributorsEnd,
            'distCount' => $distCount,
            'lastRightNode' => $lastRight->id !== $currentNode->id ? $lastRight : null,
            'lastLeftNode' => $lastLeft->id !== $currentNode->id ? $lastLeft : null,
            'previousWeekTotal' => (object) $previousWeekTotal,
            'previousWeekCarryOvers' => $previousWeekCarryOvers,
            'ranks' => [
                RankInterface::RANK_AMBASSADOR => 'ambassador',
                RankInterface::RANK_DIRECTOR => 'director',
                RankInterface::RANK_SENIOR_DIRECTOR => 'senior-director',
                RankInterface::RANK_EXECUTIVE => 'executive',
                RankInterface::RANK_SAPPHIRE_AMBASSADOR => 'sapphire-ambassador',
                RankInterface::RANK_RUBY => 'ruby',
                RankInterface::RANK_EMERALD => 'emerald',
                RankInterface::RANK_DIAMOND => 'diamond',
                RankInterface::RANK_BLUE_DIAMOND => 'blue-diamond',
                RankInterface::RANK_BLACK_DIAMOND => 'black-diamond',
                RankInterface::RANK_PRESIDENTIAL_DIAMOND => 'presidential-diamond',
                RankInterface::RANK_CROWN_DIAMOND => 'crown-diamond',
                RankInterface::RANK_DOUBLE_CROWN_DIAMOND => 'double-crown-diamond',
                RankInterface::RANK_TRIPLE_CROWN_DIAMOND => 'triple-crown-diamond',
            ],
            'packs' => array(
                Product::ID_NCREASE_NSBO => 'ncrease-nsbo-class',
                Product::ID_VISIONARY_PACK => 'visionary-pack-class',
                Product::ID_BASIC_PACK => 'basic-pack-class',
            ),
            'legKey' => $id && $currentNode->user_id != Auth::user()->id ? BinaryPlanManager::getLegKey(Auth::user(), $currentNode, $rootNode) : 0,
        ]);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAjaxDistributors(Request $request)
    {
        $params = $request->json()->all();
        $search = isset($params['search']) ? $params['search'] : null;
        $limit = isset($params['limit']) ? $params['limit'] : self::BINARY_SEARCH_ITEMS;
        $offset = isset($params['offset']) ? $params['offset'] : 0;
        $leg = isset($params['leg']) ? $params['leg'] : null;
        $currentNode = isset($params['currentNode']) && !isset($params['search']) ? $params['currentNode'] : null;

        if ($currentNode) {
            $currentNode = BinaryPlanManager::getNodeById($currentNode, Auth::user());
        }

        $distCount = BinaryPlanManager::getDistributorsCount(Auth::user(), $search, $currentNode, $leg);

        if ($params['offset'] + $limit > $distCount - self::INIT_LIMIT) {
                $limit = $distCount - $offset - self::INIT_LIMIT;
        }

        $distributors = BinaryPlanManager::getDistributorsInTree(
            Auth::user(),
            $limit,
            $offset,
            $search,
            $currentNode,
            $leg
        );

        return response()->json([
            'distributors' => $distributors,
            'total' => $distCount,
        ]);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getInitSearchDistributors(Request $request)
    {
        $params = $request->json()->all();
        $search = isset($params['search']) ? $params['search'] : null;
        $limit = isset($params['limit']) ? $params['limit'] : self::INIT_LIMIT;
        $offset = 0;
        $leg = isset($params['leg']) ? $params['leg'] : null;
        
        $user = Auth::user();
        $query = "WITH RECURSIVE bucket_tree AS ("
            ."    SELECT tid, uid, sid, pid, pbid, sbid"
            ."    FROM bucket_tree_plan"
            ."    WHERE uid = '".$user->id."'"
            ."    UNION"
            ."        SELECT bt.tid, bt.uid, bt.sid, bt.pid, bt.pbid, bt.sbid"
            ."        FROM bucket_tree_plan bt"
            ."        INNER JOIN bucket_tree t ON t.uid = bt.pid"
            ." ) SELECT u.distid, u.firstname, u.lastname, bt.*"
            ." FROM bucket_tree bt JOIN users AS u ON bt.uid = u.id";
        if($params['search'] && $params['search'] != null && !empty($params['search'])){
            $query = $query." WHERE (u.distid ILIKE '%".$params['search']."%' OR u.firstname ILIKE '%".$params['search']."%' OR u.lastname ILIKE '%".$params['search']."%' OR concat(u.firstname, ' ', u.lastname) ILIKE '%".$params['search']."%')";
        }
        $query = $query." ORDER BY bt.tid";
        $distributors = DB::select($query);
        $distCount = count($distributors);
        if(count($distributors) > 0){
            foreach($distributors as $distributor){
                $distributor->user = User::find($distributor->uid);
                $position = DB::select("select * from bucket_tree_plan where auid = '".$distributor->uid."' OR buid = '".$distributor->uid."' OR cuid = '".$distributor->uid."'");
                if(count($position) > 0){
                    if($distributor->uid == $position[0]->auid){
                        $distributor->leg = 'A';
                    }elseif($distributor->uid == $position[0]->buid){
                        $distributor->leg = 'B';
                    }elseif($distributor->uid == $position[0]->cuid){
                        $distributor->leg = 'C';
                    }else{
                        $distributor->leg = '';
                    }
                }else{
                    $distributor->leg = '';
                }
            }
        }

        $distributorsEnd = [];
        return response()->json([
            'distributors' => $distributors,
            'distributorsEnd' => $distributorsEnd,
            'total' => $distCount,
        ]);
    }
}
