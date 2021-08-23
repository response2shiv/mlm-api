<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Class BinaryPlanTree
 * @package App\Facades
 */
class BinaryPlanManager extends Facade
{
    const DIRECTION_LEFT = 'left';
    const DIRECTION_RIGHT = 'right';
    const DIRECTION_AUTO = 'auto';

    /**
     * {@inheritdoc}
     */
    protected static function getFacadeAccessor()
    {
        return 'ibu.service.binary_plan_tree';
    }
}