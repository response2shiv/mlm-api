<?php

namespace App\Services;

use App\Models\Product;
use App\Models\User;
use App\Models\UserType;
use Illuminate\Support\Facades\DB;
use utill;

/**
 * Class SubscriptionGroupService
 * @package App\Services
 */
class SubscriptionGroupService
{
    const STANDBY__ALIAS = 'standby';
    const TIER3_COACH__ALIAS = 'tier-coach';
    const TRAVERUSGF__ALIAS = 'traverus-gf';
    const STANDARD_ALIAS = 'standard';

    const SUBSCRIPTION_MAP = [
        self::STANDBY__ALIAS => [Product::MONTHLY_MEMBERSHIP_STAND_BY_USER],
        self::TIER3_COACH__ALIAS => [Product::ID_TIER3_COACH],
        self::TRAVERUSGF__ALIAS => [Product::ID_Traverus_Grandfathering, Product::ID_MONTHLY_MEMBERSHIP],
        self::STANDARD_ALIAS => [Product::MONTHLY_MEMBERSHIP]
    ];

    /**
     * @param $user
     * @return array
     */
    public function getSubscriptionTypes($user)
    {
        $data = [];

        foreach (self::SUBSCRIPTION_MAP as $alias => $productIds) {
            $data[$alias] = [
                'url' => route('subscription-details', ['subscriptionType' => $alias, 'sponsorId' => $user->id]),
                'count' => 0, // $this->getCountDistributorsByProduct($user, $productIds),
                'title' => $this->getSubscriptionTitle($alias)
            ];
        }

        return $data;
    }

    /**
     * @param User $sponsor
     * @param array $productIds
     * @return array
     */
    protected function getCountDistributorsByProduct(User $sponsor, array $productIds)
    {
        $criteria = $this->getCriteria($productIds);

        $result = DB::select(
            sprintf("
                select count(d.id)
                from get_distributors_tree(:distId) d
                join products p
                ON d.current_product_id = p.id
                JOIN orders o
                ON d.id = o.userid
                JOIN \"orderItem\" oi
                ON o.id = oi.orderid
                %s
            ", $criteria), [
                'fromDate' => utill::getFirstDayCurrentMonth(),
                'distId' => $sponsor->distid,
                'distributorType' => UserType::TYPE_DISTRIBUTOR,
                'activeStatus' => 1
            ]
        );

        return array_pop($result)->count;
    }

    /**
     * @param User $sponsor
     * @param array $productId
     * @param string $search
     * @return array
     */
    public function getDistributorsByProduct(User $sponsor, array $productId, $search)
    {
        $criteria = $this->getCriteria($productId, $search);

        $result = DB::select(
            sprintf("
                select d.id, d.firstname, d.lastname, d.username, d.distid, oi.productid as current_product_id, oi.created_dt
                from get_distributors_tree(:distId) d
                join products p
                ON d.current_product_id = p.id
                JOIN orders o
                ON d.id = o.userid
                JOIN \"orderItem\" oi
                ON o.id = oi.orderid
                %s
                order by oi.created_dt asc
            ", $criteria), [
                'fromDate' => utill::getFirstDayCurrentMonth(),
                'distId' => $sponsor->distid,
                'distributorType' => UserType::TYPE_DISTRIBUTOR,
                'activeStatus' => 1
            ]
        );

        return $result;
    }

    /**
     * @param array $productIds
     * @param string $search
     * @return string
     */
    private function getCriteria(array $productIds, $search = null)
    {
        $criteria = '';

        if (count($productIds) > 1) {
            foreach ($productIds as $key => $id) {
                if ($key > 0) {
                    $criteria .= sprintf(' or oi.productid = %s)', $id);
                    continue;
                }
                $criteria .= sprintf('where (oi.productid = %s ', $id);
            }
        } else {
            $id = array_pop($productIds);
            $criteria .= sprintf('where oi.productid = %s', $id);
        }

        $criteria .= " and oi.created_dt >= :fromDate
            and d.usertype = :distributorType
            and d.is_active = :activeStatus"
        ;

        if ($search) {
            $criteria .= sprintf(' and d.distid LIKE \'%%%s%%\'', $search);
        }

        return $criteria;
    }

    /**
     * @param $subscriptionAlias
     * @return mixed
     */
    public function getSubscriptionTitle($subscriptionAlias)
    {
        $titles = [
            self::STANDBY__ALIAS => 'Standby',
            self::TIER3_COACH__ALIAS => 'Tier 3 Coach',
            self::TRAVERUSGF__ALIAS => 'Traverus GF',
            self::STANDARD_ALIAS => 'Standard'
        ];

        return $titles[$subscriptionAlias];
    }
}
