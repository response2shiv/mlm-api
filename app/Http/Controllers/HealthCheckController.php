<?php

namespace App\Http\Controllers;


use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Bogardo\Mailgun\Facades\Mailgun;

class HealthCheckController extends Controller
{

    public function check()
    {
        $statusCode = 200;
        try {
            DB::select(DB::raw("select version()"));
            $response['db'] = 'ok';
        } catch (\Exception $e) {
            report($e);
            $response['db'] = $e->getMessage();
            $statusCode = 500;
        }

        try {
            Redis::set('redis-health-check', Carbon::now());
            $response['cache'] = 'ok';
        } catch (\Exception $e) {
            report($e);
            $response['cache'] = $e->getMessage();
            $statusCode = 500;
        }

        return response()->json($response, $statusCode);
    }

    public function mailgun()
    {
        $statusCode = 200;
        try {
            Mailgun::api()->get('/domains');
            $response['mailgun'] = 'ok';
        } catch (\Exception $e) {
            report($e);
            $response['mailgun'] = $e->getMessage();
            $statusCode = 500;
        }

        return response()->json($response, $statusCode);
    }
}