<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use DB;

class BoomerangInv extends Model {
    const MAX_BUUMERANGS_ALLOWED = 200;

    protected $table = "boomerang_inv";
    public $timestamps = false;

    protected $fillable = [
        'pending_tot',
        'available_tot'
    ];

    public static function getInventory($userId) {
        return DB::table('boomerang_inv')
                        ->where('userid', $userId)
                        ->first();
    }

    public static function updateInventory($userId, $noOfUses, $buumerangProduct) {
        $rec = BoomerangInv::where('userid', $userId)->first();
        if (!empty($rec)) {
            $rec->pending_tot = $rec->pending_tot + $noOfUses;

            if ($buumerangProduct == 'igo' || $buumerangProduct == 'bill-genius') {
                $rec->available_tot = $rec->available_tot - $noOfUses;
            }

            $rec->save();
        }
        return $rec;
    }

    public static function addToInventory($userId, $newCount) {
        $rec = BoomerangInv::where('userid', $userId)->first();

        if (!empty($rec)) {
            $rec->available_tot = $rec->available_tot + $newCount;
            $rec->save();
        } else {
            $n = new BoomerangInv();
            $n->userid = $userId;
            $n->pending_tot = 0;
            $n->available_tot = $newCount == null ? 0 : $newCount;
            $n->save();
        }
    }

    public static function addBackToInventory($userId, $count) {
        $rec = BoomerangInv::where('userid', $userId)->first();
        if (!empty($rec)) {
            $rec->available_tot = $rec->available_tot + $count;
            $rec->pending_tot = $rec->pending_tot - $count;
            $rec->save();
        }
    }

    public static function getById($boomerangInvId) {
        return DB::table('boomerang_inv')
                        ->where('id', $boomerangInvId)
                        ->first();
    }

    /**
     * @return int
     */
    public function getBoomerangTotal()
    {
        return (int)$this->pending_tot + (int)$this->available_tot;
    }

}
