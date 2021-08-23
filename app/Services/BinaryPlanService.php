<?php

namespace App\Services;

use App\Exceptions\BinaryNodeInUseException;
use App\Facades\BinaryPlanManager;
use App\Models\BinaryPlanNode;
use App\Models\Product;
use App\Models\ProductType;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use utill;
use Log;

/**
 * Class BinaryPlanTreeService
 * @package App\Services
 */
class BinaryPlanService
{
    const RIGHT_LEG_KEY = '2';
    const LEFT_LEG_KEY = '1';

    /** @var CarryoverHistoryService */
    private $carryoverHistoryService;

    /**
     * BinaryPlanService constructor.
     * @param CarryoverHistoryService $carryoverHistoryService
     */
    public function __construct(CarryoverHistoryService $carryoverHistoryService)
    {
        $this->carryoverHistoryService = $carryoverHistoryService;
    }

    /**
     * @param User $user
     * @return mixed
     */
    public function getRootBinaryNode(User $user)
    {
        return BinaryPlanNode::where('user_id', $user->id)->first();
    }

    public function getNodeById($nodeId, User $user)
    {
        // get current user root node
        $rootNode = $this->getRootBinaryNode($user);

        // make sure the user cannot fetch nodes of top sponsors
        return BinaryPlanNode::where('id', $nodeId)
            ->where('_lft', '>=', $rootNode->_lft)
            ->where('_rgt', '<=', $rootNode->_rgt)
            ->first();
    }

    public function createRootNode(User $user, $direction = BinaryPlanNode::DIRECTION_RIGHT)
    {
        if (!in_array($direction, [
            BinaryPlanNode::DIRECTION_LEFT,
            BinaryPlanNode::DIRECTION_RIGHT
        ])) {
            throw new \Exception(sprintf('Invalid direction value `%s`', $direction));
        }

        $sponsor = User::where('distid', $user->sponsorid)->first();

        $rootNode = new BinaryPlanNode();
        $rootNode->user_id = $user->id;
        $rootNode->sponsor_id = $sponsor ? $sponsor->id : null;
        $rootNode->depth = 1;
        $rootNode->enrolled_at = \DateTime::createFromFormat(
            "!Y-m-d",
            $user->created_date,
            new \DateTimeZone( 'UTC' )
        );
        // just for test goals
        $rootNode->direction = $direction;
        $rootNode
            ->makeRoot()
            ->save();

        return $rootNode;
    }

    public function createNode(User $user)
    {
        $sponsor = User::where('distid', $user->sponsorid)->first();

        $node = new BinaryPlanNode();
        $node->user_id = $user->id;
        $node->sponsor_id = $sponsor ? $sponsor->id : null;
        $node->enrolled_at = \DateTime::createFromFormat(
            "!Y-m-d",
            $user->created_date,
            new \DateTimeZone( 'UTC' )
        );

        return $node;
    }

    public function addLeftLeg($rootNode, $newNode)
    {
        $isNodeExists = BinaryPlanNode::where('user_id', $newNode->user_id)->count();

        if ($isNodeExists > 0) {
            throw new BinaryNodeInUseException($rootNode);
        }
           // Set the default direction of next node insertions
        $newNode->setLeftDirection();
        $newNode->depth = $rootNode->depth + 1;

        // TODO: Add enrolled_at value

        $rootNode->appendNode($newNode);
    }

    public function addRightLeg($rootNode, $newNode)
    {
        $isNodeExists = BinaryPlanNode::where('user_id', $newNode->user_id)->count();

        if ($isNodeExists > 0) {
            throw new BinaryNodeInUseException($rootNode);
        }

        // Set the default direction of next node insertions
        $newNode->setRightDirection();
        $newNode->depth = $rootNode->depth + 1;

        // TODO: Add enrolled_at value

        $rootNode->appendNode($newNode);
    }

    /**
     * Multiple insert of nodes with specific direction.
     *
     * @param $rootNode
     * @param array $nodes
     * @param $direction
     * @throws BinaryNodeInUseException
     */
    public function placeLegs($rootNode, array $nodes, $direction)
    {
        foreach ($nodes as $node) {
            switch ($direction) {
                case BinaryPlanManager::DIRECTION_LEFT:
                    // this is time consuming and should be optimized
                    $this->addLeftLeg($this->getLastLeftNode($rootNode), $node);
                    break;
                case BinaryPlanManager::DIRECTION_RIGHT:
                    // this is time consuming and should be optimized
                    $this->addRightLeg($this->getLastRightNode($rootNode), $node);
                    break;
            }
        }
    }

    /**
     * Multiple insert of nodes with auto-direction.
     *
     * @param $rootNode
     * @param array $nodes
     * @param $defaultDirection
     * @throws BinaryNodeInUseException
     */
    public function autoPlaceLegs($rootNode, array $nodes, $defaultDirection): void
    {
        $direction = $defaultDirection;

        foreach ($nodes as $node) {
            switch ($direction) {
                case BinaryPlanNode::DIRECTION_LEFT:
                    // this is time consuming and should be optimized
                    $this->addLeftLeg($this->getLastLeftNode($rootNode), $node);
                    break;
                case BinaryPlanNode::DIRECTION_RIGHT:
                    // this is time consuming and should be optimized
                    $this->addRightLeg($this->getLastRightNode($rootNode), $node);
                    break;
            }

            // toggle direction
            $direction = $direction === BinaryPlanNode::DIRECTION_LEFT
                ? BinaryPlanNode::DIRECTION_RIGHT
                : BinaryPlanNode::DIRECTION_LEFT;
        }
    }

    public function getLastLeftNode($node)
    {
        $lastLeg = $node;
        while ($processedNode = $this->getLeftLeg($lastLeg)) {
            $lastLeg = $processedNode;
        }

        return $lastLeg;

    }

    public function getLastRightNode($node)
    {
        $lastLeg = $node;
        while ($processedNode = $this->getRightLeg($lastLeg)) {
            $lastLeg = $processedNode;
        }

        return $lastLeg;
    }

    public function getLastPlacedNode($rootNode)
    {
        if ($this->isLeaf($rootNode)) {
            $lastNode = $rootNode;
        } else {
            $lastNode  = $rootNode->direction === BinaryPlanNode::DIRECTION_LEFT
                ? $this->getLastLeftNode($rootNode)
                : $this->getLastRightNode($rootNode);
        }

        return $lastNode;
    }

    private function isLeaf($node)
    {
        return BinaryPlanNode::where('parent_id', $node->id)->count() === 0;
    }

    public function getRightLeg($node)
    {
        return BinaryPlanNode::where('parent_id', $node->id)
            ->where('direction', BinaryPlanNode::DIRECTION_RIGHT)
            ->first();
    }

    public function getLeftLeg($node)
    {
        return BinaryPlanNode::where('parent_id', $node->id)
            ->where('direction', BinaryPlanNode::DIRECTION_LEFT)
            ->first();
    }

    public function createNodesByUsers($users)
    {
        $nodes = [];

        foreach ($users as $user) {
            $nodes[] = $this->createNode($user);
        }

        return $nodes;
    }

    public function leftLegNodes($node)
    {
        $result = new Collection();

        $leftLeg = $this->getLeftLeg($node);

        if ($leftLeg) {
            $result = BinaryPlanNode::descendantsAndSelf($leftLeg->id);
        }

        return $result;
    }

    public function rightLegNodes($node)
    {
        $result = new Collection();

        $rightLeg = $this->getRightLeg($node);

        if ($rightLeg) {
            $result = BinaryPlanNode::descendantsAndSelf($rightLeg->id);
        }

        return $result;
    }

    /**
     * @param $leg
     * @param Collection $result
     */
    public function countFourLevelNodes($leg, Collection $result): void
    {
        if ($leg) {
            $result->add($leg);

            $l2l = $this->getLeftLeg($leg);
            $l2r = $this->getRightLeg($leg);

            if ($l2l) {
                $result->add($l2l);

                $l3l = $this->getLeftLeg($l2l);
                $l3r = $this->getRightLeg($l2l);

                if ($l3l) {
                    $result->add($l3l);
                }

                if ($l3r) {
                    $result->add($l3r);
                }
            }

            if ($l2r) {
                $result->add($l2r);

                $l3l = $this->getLeftLeg($l2r);
                $l3r = $this->getRightLeg($l2r);

                if ($l3l) {
                    $result->add($l3l);
                }

                if ($l3r) {
                    $result->add($l3r);
                }
            }
        }
    }

    /**
     * @param User $user
     * @param int $limit
     * @param int $offset
     * @param null $search
     * @param null $currentNode
     * @param null $leg
     * @return mixed
     */
    public function getDistributorsInTree(User $user, $limit = 100, $offset = 0, $search = null, $currentNode = null, $leg = null)
    {
        $rootNode = $this->getRootBinaryNode($user);

        /** @var Collection $result */
        $qb = BinaryPlanNode::where('_lft', '>', $rootNode->_lft)
            ->select(DB::raw('users.*, binary_plan.id as binary_id, binary_plan.depth as level'))
            ->where('_rgt', '<', $rootNode->_rgt)
            ->leftJoin('users', 'binary_plan.user_id', '=', 'users.id')
            ->leftJoin('rank_definition', 'users.current_month_rank', '=', 'rank_definition.rankval')
            ->orderBY('binary_plan.id', 'asc')
            ->limit($limit)
            ->offset($offset);

        if ($search) {
            $qb = $qb->where(function ($query) use ($search) {
                foreach(explode(' ', $search) as $key => $value){
                    $query
                    ->orWhere('firstname', 'ilike', '%' . $value . '%')
                    ->orWhere('lastname', 'ilike', '%' . $value . '%')
                    ->orWhere('distid', 'ilike', '%' . $value . '%')
                    ->orWhere('rankdesc', 'ilike', '%' . $value . '%')
                ;
                }
            });
        }

        if ($currentNode) {
            $qb->where('_lft', '<', $currentNode->_lft)
            ->where('_rgt', '>', $currentNode->_rgt);
        }

        if ($leg) {
            $legNode = $leg == self::LEFT_LEG_KEY ? $this->getLeftLeg($rootNode) : $this->getRightLeg($rootNode);

            if ($legNode) {
                $qb->where('_lft', '>=', $legNode->_lft)
                    ->where('_rgt', '<=', $legNode->_rgt);
            }
        }

        // TODO: improve speed of adding to the list
        //$result->prepend($rootNode);

        return $qb->get();
    }

    /**
     * @param $rootNode
     * @param $date
     * @return mixed
     */
    public function getNodeTotal($rootNode, $date)
    {
        $result = DB::table('binary_plan')
            ->leftJoin('orders', 'binary_plan.user_id', '=', 'orders.userid')
            ->leftJoin('orderItem', 'orders.id', '=', 'orderItem.orderid')
            ->leftJoin('products', 'products.id', '=', 'orderItem.productid')
            ->select(DB::raw("COALESCE(SUM(\"orderItem\".cv), 0) as sum_orders"))
            ->where('binary_plan._lft', '>=', $rootNode->_lft)
            ->where('binary_plan._rgt', '<=', $rootNode->_rgt)
            ->whereIn('products.producttype', [
                ProductType::TYPE_ENROLLMENT,
                ProductType::TYPE_UPGRADE
            ])
            ->whereDate('orders.created_dt', '>=', $date)
            ->value('sum_orders');

        return $result;
    }

    /**
     * @param User $user
     * @param null $search
     * @param null $currentNode
     * @param null $leg
     * @return mixed
     */
    public function getDistributorsCount(User $user, $search = null, $currentNode = null, $leg = null)
    {
        $rootNode = $this->getRootBinaryNode($user);

        $qb = DB::table('binary_plan')
            ->select(DB::raw('count(binary_plan.id) as dist_count'))
            ->leftJoin('users', 'binary_plan.user_id', '=', 'users.id')
            ->leftJoin('rank_definition', 'users.current_month_rank', '=', 'rank_definition.rankval')
            ->where('_rgt', '<', $rootNode->_rgt)
            ->where('_lft', '>', $rootNode->_lft);

        if ($search) {
            $qb = $qb->where(function ($query) use ($search) {
                foreach(explode(' ', $search) as $key => $value){
                    $query
                    ->orWhere('firstname', 'ilike', '%' . $value . '%')
                    ->orWhere('lastname', 'ilike', '%' . $value . '%')
                    ->orWhere('distid', 'ilike', '%' . $value . '%')
                    ->orWhere('rankdesc', 'ilike', '%' . $value . '%')
                ;
                }
            });
        }

        if ($currentNode) {
            $qb->where('_lft', '<', $currentNode->_lft)
                ->where('_rgt', '>', $currentNode->_rgt);
        }

        if ($leg) {
            $legNode = $leg == self::LEFT_LEG_KEY ? $this->getLeftLeg($rootNode) : $this->getRightLeg($rootNode);

            if ($legNode) {
                $qb->where('_lft', '>=', $legNode->_lft)
                    ->where('_rgt', '<=', $legNode->_rgt);
            }
        }

        return $qb->value('dist_count');
    }

    public function insertAfter($parentNode, $newNode, $direction)
    {
        if (!array_key_exists($direction, BinaryPlanNode::MAP_DIRECTION)) {
            throw new \Exception('Invalid direction value');
        }

        // just in case set the direction to the new node
        $newNode->direction = BinaryPlanNode::MAP_DIRECTION[$direction];

        DB::transaction(function () use ($parentNode, $newNode) {
            // add leg to the parent node
            $parentNode->appendNode($newNode);
            $legs = $this->getDescendants($parentNode);
            foreach ($legs as $leg) {
                // the default append behavior add more than two legs, so we have to shift another one down
                if ($leg->id !== $newNode->id && $leg->direction === $newNode->direction) {
                    $leg->down();
                    $leg->parent_id = $newNode->id;
                    $leg->save();
                }
            }

            $newNode->depth = $parentNode->depth + 1;
            $newNode->save();

            // re-calculate depth of the binary tree
            $this->quickCalculateOfDepth(
                $this->refreshNode($newNode)
            );
        }, 5);
    }

    /**
     * @param $fromNode
     * @param $toNode
     * @param $direction
     * @param bool $isMirror
     * @param bool $isDownline
     * @throws \Exception
     */
    public function moveNode($fromNode, $toNode, $direction, $isMirror, $isDownline)
    {
        if (!array_key_exists($direction, BinaryPlanNode::MAP_DIRECTION)) {
            throw new \Exception('Invalid direction value');
        }

        DB::transaction(function () use ($fromNode, $toNode, $direction, $isMirror, $isDownline) {
            // at fist let's mirror the downline nodes
            if ($isMirror === true && $isDownline === true) {
                // switch a sponsor leg with an inner leg (R -> L or L -> R)
                $this->mirrorNodes($fromNode);
                $fromNode = $this->refreshNode($fromNode);
            }

            // set the new direction for the working node
            $fromNode->direction = BinaryPlanNode::MAP_DIRECTION[$direction];
            $fromNode->save();

            $parentFrom = BinaryPlanNode::where('id', $fromNode->parent_id)->first();

            if ($isDownline) {
                // determine the placement direction
                // move to the last left node and attach the from node as a third leg to the left
                $toNode->appendNode($fromNode);
                $fromNode = $this->refreshNode($fromNode);

                $siblings = $this->getDescendants($toNode);
                foreach ($siblings as $sibling) {
                    // the worst case if we have two new right legs (it's wrong we should have only one right leg)
                    // the last one should be move on the end of the working node
                    if ($sibling->id !== $fromNode->id && $sibling->direction === $fromNode->direction) {
                        if ($direction === BinaryPlanManager::DIRECTION_LEFT) {
                            $lastNode = $this->getLastLeftNode($fromNode);
                        } else {
                            $lastNode = $this->getLastRightNode($fromNode);
                        }

                        // add next nodes at the end of from nodes downline
                        $lastNode->appendNode($sibling);
                    }
                }
            } else {
                $innerLeg = $this->getInnerLegFirstNode($fromNode);

                // If inner leg exists we'll should stop the move process
                if ($innerLeg) {
                    throw new \Exception(
                        'The Distributor you wish to move has an inside leg that must be moved with its sponsor.'
                    );
                }

                // attach from node to the new place
                $toNode->appendNode($fromNode);

                // return sponsor leg back
                $fromNode = $this->refreshNode($fromNode);
                $sponsorLeg = $this->getOneLegOrThrowException($fromNode);

                if ($sponsorLeg) {
                    if ($parentFrom) {
                        $parentFrom->appendNode($sponsorLeg);
                    } else {
                        // if we move the root node the sponsor leg first node will became a new root one
                        $sponsorLeg->parent_id = null;
                        $sponsorLeg->save();
                    }
                }

                $siblings = $this->getDescendants($toNode);
                foreach ($siblings as $sibling) {
                    if ($sibling->id !== $fromNode->id && $sibling->direction === $fromNode->direction) {
                        $fromNode->appendNode($sibling);
                    }
                }
            }

            // Calculates new depth values from moved nodes (exclude the whole rebuild of binary tree)
            $this->calculateOfDepthByNode($fromNode);

            if ($parentFrom) {
                $this->calculateOfDepthByNode($parentFrom);
            }
        });
    }

    public function replaceWith($fromNode, $toNode)
    {
        $isNewInsert = $fromNode->id === null;

        if ($this->checkIfNodeHasEnrolledDistributors($toNode)) {
            throw new \Exception(
                sprintf('TSA#%s cannot be removed from his current position.', $toNode->user->distid)
            );
        }

        // validate enrollments only for existing nodes
        if (!$isNewInsert) {
            if ($this->checkIfNodeHasEnrolledDistributors($fromNode)) {
                throw new \Exception(
                    sprintf('TSA#%s cannot be removed from his current position.', $fromNode->user->distid)
                );
            }
        }

        if ($isNewInsert) {
            // set the new user for the node
            // the previous user will release to the holding tank (exclude from the binary tree)
            $toNode->user_id = $fromNode->user_id;
            $toNode->save();
        } else {
            DB::transaction(function () use ($fromNode, $toNode) {
                $fromUserId = $fromNode->user_id;
                $toUserId = $toNode->user_id;

                // just swap user
                DB::statement('update binary_plan
                    set user_id = case user_id
                        when :fromUser then (select user_id from binary_plan where user_id = :toUser)
                        when :toUser then (select user_id from binary_plan where user_id = :fromUser)
                    end
                    where user_id in (:fromUser,:toUser);
                    ',
                    [
                        'fromUser' => $fromUserId,
                        'toUser' => $toUserId,
                    ]
                );
            });
        }
    }

    /**
     * Delete an existing node.
     *
     * @param $node
     * @throws \Exception
     */
    public function deleteNode($node, $exception = true)
    {
        if ($this->checkIfNodeHasEnrolledDistributors($node)) {
            // Log::info('The Distributor you wish to delete has an inside leg and/or personally enrolled users');
            if($exception){
                throw new \Exception(
                    'The Distributor you wish to delete has an inside leg and/or personally enrolled users'
                );
            }else{
                return false;
            }
            
        }

        if ($node->isRoot()) {
            // Log::info('Cannot delete the root agent');
            if($exception){
                throw new \Exception(
                    'Cannot delete the root agent'
                );
            }else{
                return false;
            }
        }

        DB::transaction(function () use($node) {
            $root = BinaryPlanNode::where('id', $node->parent_id)->first();

            if (!$root) {
                // Log::info('Cannot find the parent agent node');
                if($exception){
                    throw new \Exception(
                        'Cannot find the parent agent node'
                    );
                }else{
                    return false;
                }
            }

            if ($this->isLeaf($node)) {
                $node->delete();
            } else {
                $childs = BinaryPlanNode::where('parent_id', $node->id)->get();

                // validate if childs has more than one
                if ($childs->count() !== 1) {
                    // Log::info('Cannot delete the target agent node');
                    if($exception){
                        throw new \Exception(
                            'Cannot delete the target agent node'
                        );
                    }else{
                        return false;
                    }
                }

                // get the child node
                $child = $childs->first();

                // 1. Make child temporary root node
                $child->saveAsRoot();
                // 2. Delete the node (now it has no child nodes)
                $node->delete();
                // 3. Append the temporary node to the new node (go up to one step)
                $root->appendNode($child);
            }

            // 4. Re-calculate the depth
            $this->calculateOfDepthByNode($root);
            // done.
        });
    }

    /**
     * Inactivate the user of an existing node.
     *
     * @param $node
     * @throws \Exception
     */
    public function inactivateNode($node)
    {
        $agent = User::where('id', $node->user_id)->first();

        if (!$agent) {
            throw new \Exception('Selected agent does not found in the tree');
        }

        $agent->inactivate();
        $agent->save();
    }

    /**
     * Re-activate the user of an existing node.
     *
     * @param $node
     * @throws \Exception
     */
    public function reactivateNode($node)
    {
        $agent = User::where('id', $node->user_id)->first();

        if (!$agent) {
            throw new \Exception('Selected agent does not found in the tree');
        }

        $agent->reactivate();
        $agent->save();
    }

    private function quickCalculateOfDepth($newNode)
    {
        // Just increase the depth value for all included nodes
        DB::statement('
            UPDATE binary_plan bp
            SET depth = depth + 1
            WHERE _lft > :leftValue AND _rgt < :rightValue;',
            ['leftValue' => $newNode->_lft, 'rightValue' => $newNode->_rgt]
        );
    }

    /**
     * @param $user
     * @param $currentNode
     * @return string
     */
    public function getLegKey($user, $currentNode)
    {
        $rootNode = $this->getRootBinaryNode($user);

        $leftLegNode = $this->getLeftLeg($rootNode);

        if ($leftLegNode && $leftLegNode->_lft <= $currentNode->_lft && $leftLegNode->_rgt >= $currentNode->_rgt) {
            return self::LEFT_LEG_KEY;
        }

        return self::RIGHT_LEG_KEY;
    }

    /**
     * Calculates binary tree levels from the target node.     *
     * The time of execution depends on the node placement (root nodes can take more time to calculate than leaves)
     *
     * @param $node
     */
    private function calculateOfDepthByNode($node)
    {
        DB::statement('
            UPDATE binary_plan bp
            SET depth = (SELECT (COUNT(parent.id) - 1)
                FROM binary_plan AS node,
                    binary_plan AS parent
                 WHERE node._lft BETWEEN parent._lft AND parent._rgt
                AND node.id = bp.id
                 GROUP BY node.id
                 ORDER BY node._lft
            )
            WHERE bp._lft > :leftValue
            AND bp._rgt < :rightValue;
            ',
            [
                'leftValue' => $node->_lft,
                'rightValue' => $node->_rgt,
            ]
        );
    }

    /**
     * Calculates binary tree levels.
     * It takes the a lot of time.
     */
    private function deepCalculateOfDepth()
    {
        DB::statement('
            UPDATE binary_plan bp
            SET depth = (SELECT (COUNT(parent.id) - 1)
                FROM binary_plan AS node,
                    binary_plan AS parent
                 WHERE node._lft BETWEEN parent._lft AND parent._rgt
                AND node.id = bp.id
                 GROUP BY node.id
                 ORDER BY node._lft
            );
        ');
    }


    public function getDescendants($node)
    {
        return BinaryPlanNode::where('parent_id', $node->id)->get();
    }

    public function getNodeByAgentTsa($tsaNumber)
    {
        $node = null;

        $recordId = DB::table('binary_plan')
            ->select('binary_plan.id')
            ->join('users', 'binary_plan.user_id', '=', 'users.id')
            ->where('users.distid', $tsaNumber)
            ->pluck('id')
            ->first();

        if ($recordId) {
            $node = BinaryPlanNode::where('id', $recordId)->first();
        }

        return $node;
    }

    /**
     * Get the actual node info.
     *
     * @param $newNode
     * @return mixed
     */
    private function refreshNode($newNode)
    {
        return BinaryPlanNode::where('id', $newNode->id)->first();
    }

    /**
     * Mirror the bunch of nodes from the target node id
     * @param $node
     */
    private function mirrorNodes($node)
    {
        // just flip R->L or L->R direction
        DB::statement("
                UPDATE binary_plan bp
                SET direction = CASE WHEN bp.direction = 'R' THEN 'L' ELSE 'R' END
                WHERE bp._lft > :leftValue AND bp._rgt < :rightValue;
            ",
            [
                'leftValue' => $node->_lft,
                'rightValue' => $node->_rgt,
            ]
        );
    }

    private function getInnerLegFirstNode($targetNode)
    {
        $parentNode = BinaryPlanNode::where('id', $targetNode->parent_id)->first();
        $sponsorLegDirection = $parentNode->direction;

        // detect an inner leg
        $innerLeg = null;
        if ($sponsorLegDirection === BinaryPlanNode::DIRECTION_LEFT) {
            $innerLeg = $this->getRightLeg($targetNode);
        } else {
            $innerLeg = $this->getLeftLeg($targetNode);
        }

        return $innerLeg;
    }

    private function getOneLegOrThrowException($targetNode)
    {
        $legs = BinaryPlanNode::where('parent_id', $targetNode->id)->get();

        if ($legs->count() > 1) {
            throw new \Exception('Invalid count of possible legs for single node during the move process.');
        }
        return $legs->first();
    }

    public function checkIfNodeHasEnrolledDistributors($node)
    {
        $hasPersonalEnrolledAgents = false;

        $nodeAgent = User::where('id', $node->user_id)->first();

        if (!$nodeAgent) {
            throw new \Exception(
                sprintf('Agent #%s does not exist in the system', $node->user_id)
            );
        }

        $enrolledAgents = User::where('sponsorid', $nodeAgent->distid)->pluck('id')->toArray();

        if (count($enrolledAgents)) {
            // determine sub node visible scope for the target user
            // and try to compare user ids with existing in the tree
            $sponsorsInBinaryTree = BinaryPlanNode::where('_lft', '>', $node->_lft)
                ->where('_rgt', '<', $node->_rgt)
                ->whereIn('user_id', $enrolledAgents)
                ->get()
            ;

            $hasPersonalEnrolledAgents = $sponsorsInBinaryTree->count() > 0;
        }

        return $hasPersonalEnrolledAgents;
    }

    /**
     * @param User $user
     * @return array
     */
    public function getPreviousWeekTotal(User $user)
    {
        $binaryNode = BinaryPlanNode::where('user_id', $user->id)->first();

        $rightChild = $this->getClosestChildNode($binaryNode, BinaryPlanNode::DIRECTION_RIGHT);
        $leftChild = $this->getClosestChildNode($binaryNode, BinaryPlanNode::DIRECTION_LEFT);

        $mondayPreviousWeek = Carbon::now(utill::USER_TIME_ZONE)->startOfWeek()->subDay(7);
        $sundayPreviousWeek = Carbon::now(utill::USER_TIME_ZONE)->startOfWeek()->subDay(1)->endOfDay();

        $rightTotal = $this->getSubtreeTotalVolume($rightChild, $mondayPreviousWeek, $sundayPreviousWeek);
        $leftTotal = $this->getSubtreeTotalVolume($leftChild, $mondayPreviousWeek, $sundayPreviousWeek);

        $previousCommissionEndDate = $mondayPreviousWeek->copy()->subDay()->endOfDay();

        $carryover = $this->carryoverHistoryService->getCarryoverForUserByCommission($user, $previousCommissionEndDate);

        return [
            'left' => ($leftTotal ? $leftTotal->sum : 0) + $carryover['left'],
            'right' => ($rightTotal ? $rightTotal->sum : 0) + $carryover['right']
        ];
    }

    /**
     * @param User $user
     * @return array
     */
    public function getPreviousWeekCarryOvers(User $user)
    {
        $mondayPreviousWeek        = Carbon::now(utill::USER_TIME_ZONE)->startOfWeek()->subDay(7);
        $previousCommissionEndDate = $mondayPreviousWeek->copy()->subDay()->endOfDay();

        return $this->carryoverHistoryService->getCarryoverForUserByCommission($user, $previousCommissionEndDate);
    }

    /**
     * @param $node
     * @param $fromDate
     * @param $toDate
     * @return array|mixed
     */
    public function getSubtreeTotalVolume($node, $fromDate, $toDate)
    {
        if (!$node instanceof BinaryPlanNode) {
            return [];
        }

        $result =  DB::select(
            'SELECT COALESCE(SUM(oi.cv), 0) as sum
            FROM binary_plan bp
            JOIN orders o
            ON bp.user_id = o.userid
            JOIN "orderItem" oi
            ON o.id = oi.orderid
            JOIN products p
            ON p.id = oi.productid
            WHERE o.created_dt >= :from_date
                AND o.created_dt <= :to_date
                AND (o.statuscode = 1 OR o.statuscode = 6)
                AND _lft >= :left_key AND _rgt <= :right_key
                AND (p.producttype = 1 OR p.producttype = 2)
            ;', [
                'from_date' => $fromDate,
                'to_date' => $toDate,
                'left_key' => $node->_lft,
                'right_key' => $node->_rgt
            ]
        );

        return array_pop($result);
    }

    /**
     * @param BinaryPlanNode $parentNode
     * @param string $treeDirection
     * @return mixed
     */
    public function getClosestChildNode(BinaryPlanNode $parentNode, string $treeDirection)
    {
        return BinaryPlanNode::where('parent_id', $parentNode->id)
            ->where('direction', $treeDirection)
            ->first();
    }

    /**
     * @param $leftKey
     * @param $rightKey
     * @param $user
     * @return array
     */
    public function getActiveUserCount($leftKey, $rightKey, $user)
    {
        return DB::select(
            '
            select count(u.id) from users u
            join binary_plan bp
            on bp.user_id = u.id
            where (
                    u.id in (
                      select userid from orders
                      where date(created_dt) >= :monthAgo group by userid having sum(orderqv) >= :minPqvValue
                      )
                    or (u.current_product_id = :idPremiumFirstClass and u.created_dt >= :yearAgo)
                    or u.distid in (:alwaysActive)
                   )
                   and u.account_status not in (\'TERMINATED\', \'SUSPENDED\')
                   and _lft >= :leftKey
                   AND _rgt <= :rightKey
                   AND u.sponsorid = :sponsorId
                   ;
            ',
            [
                'monthAgo' => Carbon::now('UTC')->subDays(30)->format('Y-m-d'),
                'yearAgo' => Carbon::now('UTC')->subYear()->format('Y-m-d'),
                'minPqvValue' => User::MIN_QV_MONTH_VALUE,
                'alwaysActive' => implode(',', [
                    '\'A1357703\'',
                    '\'A1637504\'',
                    '\'TSA9846698\'',
                    '\'TSA3564970\'',
                    '\'TSA9714195\'',
                    '\'TSA8905585\'',
                    '\'TSA2593082\'',
                    '\'TSA0707550\'',
                    '\'TSA9834283\'',
                    '\'TSA5138270\'',
                    '\'TSA8715163\'',
                    '\'TSA3516402\'',
                    '\'TSA8192292\'',
                    '\'TSA9856404\'',
                    '\'TSA1047539\''
                ]),
                'idPremiumFirstClass' => Product::ID_PREMIUM_FIRST_CLASS,
                'leftKey' => $leftKey,
                'rightKey' => $rightKey,
                'sponsorId' => $user->distid,
            ]
        );
    }

    /**
     * @param BinaryPlanNode $targetNode
     * @return array
     */
    public function getActiveDirections(BinaryPlanNode $targetNode)
    {
        $leftLeg = $this->getLeftLeg($targetNode);
        $rightLeg = $this->getRightLeg($targetNode);

        $user = User::find($targetNode->user_id);

        $result = [];
        $result['left'] = $leftLeg ? $this->hasActiveDistributorInLeg($leftLeg, $user) : 0;
        $result['right'] = $rightLeg ? $this->hasActiveDistributorInLeg($rightLeg, $user) : 0;

        return $result;
    }

    /**
     * @param BinaryPlanNode $legNode
     * @param User $user
     * @return bool
     */
    public function hasActiveDistributorInLeg(BinaryPlanNode $legNode, User $user)
    {
        $activeDistributors = $this->getActiveUserCount($legNode->_lft, $legNode->_rgt, $user);

        return array_pop($activeDistributors)->count;
    }

    public function isSingleDetached($node)
    {
        if (!$node->isRoot() || !$node->isLeaf() || $node->depth > 1) {
            return false;
        }

        return true;
    }
}
