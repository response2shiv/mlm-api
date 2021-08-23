<?php

namespace App\Services;

use App\Exceptions\BinaryNodeIsChangedException;
use App\Facades\BinaryPlanManager;
use App\Facades\HoldingTank;
use App\Models\BinaryPlanNode;
use App\Models\Order;
use App\Models\User;
use DB;
use Auth;

/**
 * Class HoldingTankService
 * @package App\Services
 */
class HoldingTankService {

    /**
     * @param User $user
     * @return mixed
     */
    public function getRootBinaryNode(User $user) {
        return BinaryPlanNode::where('user_id', $user->id)->first();
    }

    /**
     * @param User $user
     * @return mixed
     * @throws \Exception
     */
    public function createRootBinaryNode(User $user) {
        $hasRootNode = BinaryPlanNode::where('user_id', $user->id)->count() === 0;

        if (!$hasRootNode) {
            throw new \Exception(
            sprintf('Cannot create a root node cause it exists for user#%s', $user->id)
            );
        }

        return BinaryPlanManager::createRootNode($user);
    }

    /**
     * @param User $user
     * @return mixed
     * @throws \Exception
     */
    public function getOrCreateRootNode(User $user) {
        $result = $this->getRootBinaryNode($user);

        if (!$result) {
            $result = $this->createRootBinaryNode($user);
        }

        return $result;
    }

    public function filterDistributors($distIds, User $user) {
        $result = User::whereIn('id', $distIds)
                ->where('sponsorid', $user->distid)
                ->get();

        return $result;
    }

    public function filterTargetNode($nodeId) {
        $result = BinaryPlanNode::find($nodeId);
        return $result;
    }

    /**
     *  Show free to place to the binary tree distributors.
     *
     * @param User $user
     * @param null $search
     * @return mixed
     */
    public function getFreeDistributorsList(User $user, $search = null)
    {
        // prepare default settings
        $qb = DB::table('users')
                ->leftJoin('binary_plan', 'users.id', '=', 'binary_plan.user_id')
                ->select('users.id')
                ->where('users.sponsorid', $user->distid)
                ->whereNull('binary_plan.id')
                ->orderBy('users.created_dt', 'desc')
        ;

        if ($search) {
            $qb = $qb->where(function ($result) use ($search) {
                $result
                        ->where('firstname', 'ilike', '%' . $search . '%')
                        ->orWhere('lastname', 'ilike', '%' . $search . '%')
                        ->orWhere('distid', 'ilike', '%' . $search . '%')
                        ->orWhere('username', 'ilike', '%' . $search . '%')
                ;
            });
        }

        $userIds = $qb
                ->orderBy('users.created_dt', 'desc')
                ->pluck('id')
                ->toArray();

        $result = User::whereIn('id', $userIds)->limit(300)->get();

        return $result;
    }

    public function getLastNodes($rootNode) {
        $lastPlacedNode = BinaryPlanManager::getLastPlacedNode($rootNode);
        $lastLeftNode = BinaryPlanManager::getLastLeftNode($rootNode);
        $lastRightNode = BinaryPlanManager::getLastRightNode($rootNode);

        return [
            BinaryPlanManager::DIRECTION_RIGHT => [
                "id" => $lastRightNode->id,
                "label" => $lastRightNode->getLabel()
            ],
            BinaryPlanManager::DIRECTION_LEFT => [
                "id" => $lastLeftNode->id,
                "label" => $lastLeftNode->getLabel()
            ],
            BinaryPlanManager::DIRECTION_AUTO => [
                "id" => $lastPlacedNode->id,
                "label" => $lastPlacedNode->getLabel()
            ],
        ];
    }

    public function getLastNode($rootNode, $direction) {
        switch ($direction) {
            case BinaryPlanManager::DIRECTION_RIGHT:
                $lastNode = BinaryPlanManager::getLastRightNode($rootNode);
                break;
            case BinaryPlanManager::DIRECTION_LEFT:
                $lastNode = BinaryPlanManager::getLastLeftNode($rootNode);
                break;
            case BinaryPlanManager::DIRECTION_AUTO:
                $lastNode = BinaryPlanManager::getLastPlacedNode($rootNode);
                break;
            default:
                throw new \Exception('Invalid placement option.');
        }

        return $lastNode;
    }

    /**
     * Place agents to the binary tree viewer.
     *
     * @param $targetNode
     * @param $distributors
     * @param $direction
     * @throws \Exception
     */
    public function placeAgentsToBinaryViewer($targetNode, $distributors, $direction): void {
        $siblings = BinaryPlanNode::where('parent_id', $targetNode->id)->get();
        $isSinglePlacement = $distributors->count() === 1;

        if (Auth::check()) {
            $rootNode = HoldingTank::getRootBinaryNode(Auth::user());
        } else {
            $rootNode = $targetNode;
        }

        // update orders create dates to NOW()
        foreach ($distributors as $distributor) {
            $this->updateOrdersCreateDate($distributor);
        }

        switch ($direction) {
            case BinaryPlanManager::DIRECTION_RIGHT:
                if ($isSinglePlacement) {
                    BinaryPlanManager::addRightLeg(
                            BinaryPlanManager::getLastRightNode($targetNode), BinaryPlanManager::createNode($distributors->first())
                    );
                } else {
                    $newNodes = BinaryPlanManager::createNodesByUsers($distributors);
                    BinaryPlanManager::placeLegs($rootNode, $newNodes, $direction);
                }

                $this->checkIfNodeIsAlreadyInUse($siblings, BinaryPlanNode::DIRECTION_RIGHT);
                break;
            case BinaryPlanManager::DIRECTION_LEFT:
                if ($isSinglePlacement) {
                    BinaryPlanManager::addLeftLeg(
                            BinaryPlanManager::getLastLeftNode($targetNode), BinaryPlanManager::createNode($distributors->first())
                    );
                } else {
                    $newNodes = BinaryPlanManager::createNodesByUsers($distributors);
                    BinaryPlanManager::placeLegs($rootNode, $newNodes, $direction);
                }

                $this->checkIfNodeIsAlreadyInUse($siblings, BinaryPlanNode::DIRECTION_LEFT);
                break;
            case BinaryPlanManager::DIRECTION_AUTO:
                $newNodes = BinaryPlanManager::createNodesByUsers($distributors);
                // generate new nodes and auto-place it to the structure
                BinaryPlanManager::autoPlaceLegs($rootNode, $newNodes, $targetNode->direction);
                break;
            default:
                throw new \Exception('Invalid direction for the single node placement.');
        }
    }

    /**
     * @param $siblings
     * @param $direction
     * @throws BinaryNodeIsChangedException
     */
    public function checkIfNodeIsAlreadyInUse($siblings, $direction): void {
        foreach ($siblings as $sibling) {
            if ($sibling->direction === $direction) {
                throw new BinaryNodeIsChangedException($sibling);
            }
        }
    }

    /**
     * Just update orders for the following node.
     *
     * @param $distributor
     */
    private function updateOrdersCreateDate($distributor)
    {
        $nodeOrders = Order::where('userid', $distributor->id)->get();

        foreach ($nodeOrders as $order) {
            DB::transaction(function () use ($order) {
                $order->created_dt = \utill::getCurrentDateTime();
                $order->created_date = \utill::getCurrentDate();
                $order->created_time = \utill::getCurrentTime();
                $order->save();

                $orderItems = $order->orderItems;

                foreach ($orderItems as $orderItem) {
                    $orderItem->created_dt = \utill::getCurrentDateTime();
                    $orderItem->created_date = \utill::getCurrentDate();
                    $orderItem->created_time = \utill::getCurrentTime();
                    $orderItem->save();
                }
            });
        }
    }
}
