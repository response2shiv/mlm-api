<?php

namespace App\Http\Controllers\Affiliates;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use DataTables;
use DB;
use Auth;

class CustomerController extends Controller
{
    /*
    *
    */
    public function getCustomerDistData(){
        $query = DB::table('customers')
                ->where('userid', Auth::user()->id);
        return DataTables::of($query)->toJson();
    }
}
