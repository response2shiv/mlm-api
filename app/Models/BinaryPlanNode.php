<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Kalnoy\Nestedset\NodeTrait;
use DB;

/**
 * Model for Sponsor Tree Nested Set.
 *
 * @package App\Models
 */
class BinaryPlanNode extends Model {
    use NodeTrait;

    const TABLE_NAME = 'binary_plan';

    const DIRECTION_LEFT = 'L';
    const DIRECTION_RIGHT = 'R';

    const MAP_DIRECTION = [
        'left' => self::DIRECTION_LEFT,
        'right' => self::DIRECTION_RIGHT,
    ];

    /**
     * Disable timestamps for this model.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = self::TABLE_NAME;

    /**
     * Get the phone record associated with the user.
     */
    public function user()
    {
        return $this->hasOne('App\Models\User', 'id', 'user_id');
    }

    public function hasLeftLeg()
    {
        return $this->getLeftLeg() !== null;
    }

    public function hasRightLeg()
    {
        return $this->getRightLeg() !== null;
    }

    public function getLeftLeg()
    {
        return BinaryPlanNode::where('parent_id', $this->id)
            ->where('direction', BinaryPlanNode::DIRECTION_LEFT)
            ->first();
    }

    public function getRightLeg()
    {
        return BinaryPlanNode::where('parent_id', $this->id)
            ->where('direction', BinaryPlanNode::DIRECTION_RIGHT)
            ->first();
    }

    public function setLeftDirection()
    {
        $this->direction = static::DIRECTION_LEFT;
    }

    public function setRightDirection()
    {
        $this->direction = static::DIRECTION_RIGHT;
    }

    public function getDefaultDirection()
    {
        switch ($this->direction) {
            case static::DIRECTION_LEFT:
                $direction = static::DIRECTION_LEFT;
                break;
            case static::DIRECTION_RIGHT:
                $direction = static::DIRECTION_RIGHT;
                break;
            default:
                // will return only for the root node
                $direction = null;
        }

        return $direction;
    }

    public function isLeaf() {
        return $this->descendants()->count() === 0;
    }

    public function validateNode()
    {
        if ($this->siblings()->count() > 2) {
            throw new \Exception(
                sprintf('Node #%s has more than two legs', $this->id)
            );
        }
    }

    public function getLabel()
    {
        $label = '';
        if (!$this->id) {
            return $label;
        }

        $label = sprintf('%s %s - %s', $this->user->firstname, $this->user->lastname, $this->user->distid);

        return $label;
    }

    public function getEnrollmentDate()
    {
        // should be replaced with decorators
        return date('d/m/Y', strtotime($this->enrolled_at));
    }
}
