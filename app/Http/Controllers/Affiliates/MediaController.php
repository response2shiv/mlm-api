<?php

namespace App\Http\Controllers\Affiliates;
use App\Http\Controllers\Controller;


use Illuminate\Http\Request;
use App\Models\Media;
use App\Models\PromoInfo;
use Auth;
use DB;

class MediaController extends Controller
{
    //
    public function getAllMedia(){
        $d['videos'] = Media::getRecs(Media::TYPE_VIDEO);
        $d['images'] = Media::getRecs(Media::TYPE_IMAGE);
        $d['documents'] = Media::getRecs(Media::TYPE_DOCUMENT);
        $d['presentations'] = Media::getRecs(Media::TYPE_PRESENTATION);

        $this->setResponse($d);
        $this->setResponseCode(200);
        return $this->showResponse();
    }

    public function checkVideoAccess() {
        $userId = Auth::user()->id;
        $promo = PromoInfo::getPromoAll(2);
        $order = DB::select(DB::raw("SELECT
                        oi.orderid,
                        oi.productid,
                        oi.quantity,
                        users.id,
                        users.username,
                        users.firstname,
                        users.lastname,
                        users.email,
                        users.distid
                    FROM \"orderItem\" AS oi
                    JOIN orders ord on ord.id = oi.orderid
                    JOIN users ON users.id = ord.userid
                    WHERE
                        oi.productid = 56
                        AND users.id = ".$userId." "));

        if($order){
            $response['order'] = $order[0];
        }else{
            $response['order'] = null;
        }

        $response['promo'] = $promo;
        $this->setResponse($response);
        $this->setResponseCode(200);
        return $this->showResponse();
    }

    public function downloads() {
        $userId = Auth::user()->id;
        $downloads = DB::select(DB::raw('
                select count(*) from orders o
                left join "orderItem" oi on o.id=oi.orderid
                where o.userid='.$userId.'
                and oi.productid=53;'));

        $response['downloads'] = $downloads;

        $this->setResponse($response);
        $this->setResponseCode(200);
        return $this->showResponse();
    }

}
