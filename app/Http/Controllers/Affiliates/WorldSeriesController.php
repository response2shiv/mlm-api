<?php

namespace App\Http\Controllers\Affiliates;

use App\Helpers\TSA;
use App\Http\Controllers\Affiliates\WorldSeriesEventsController;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Models\WorldSeriesEvents;
use App\Models\WorldSeriesOverviews;
use Faker\Factory as Faker;
use Illuminate\Http\Request;
use Illuminate\Routing\redirect;
use GuzzleHttp\Client;
use Validator;
use Log;

class WorldSeriesController extends Controller
{

    public function __construct(WorldSeriesEventsController $events) 
    {
        $this->events = $events;
    }
    
    public function formData(Request $request) 
    {
        $overview = WorldSeriesOverviews::with(['firstBaseUser', 'secondBaseUser', 'thirdBaseUser', 'sponsor'])
            ->orderBy('sponsor_id', 'ASC')
            ->orderBy('runs', 'DESC')
            ->get();

        return view('world-series.index')->with('overview', $overview);
    }

    public function fakeData(Request $request)
    {
        $overview = [];

        if ($request->get('action') == 'reset') {

            $events = WorldSeriesEvents::distinct('user_id')->get();
            
            WorldSeriesOverviews::truncate();
            WorldSeriesEvents::truncate();

            # delete fake users
            foreach ($events as $event){
                User::whereId($event->user_id)->delete();
            }


            return redirect('/worldseries')->with([
                'warning'=> 'All data are clear from World Series',
            ]);
        } elseif ($request->get('action') == 'fill') {
            $this->fillFakeData($request);
        }

        $overview = WorldSeriesOverviews::with(['firstBaseUser', 'secondBaseUser', 'thirdBaseUser', 'sponsor'])
            ->orderBy('sponsor_id', 'ASC')
            ->orderBy('runs', 'DESC')
            ->get();

        return view('world-series.index')->with('overview', $overview);
    }

    # Sponsor: 'TSA8715163' 
    public function fillFakeData(Request $request)
    {
        $sponsor  = $request->get('sponsor'); 
        $username = $request->get('username');
        $product  = $request->get('product');
        $ticket   = $request->get('ticket');

        if (empty($sponsor) || empty($username)) {
            return redirect()->back()->withInput()->with('error', 'Fill the Sponsor and Username.');
        }
        
        $sponsor = User::whereDistid($sponsor)->first();
        if (is_null($sponsor)) {
            return redirect()->back()->withInput()->with('error', 'Sponsor Not Found!');
        }

        $verify_username = User::whereUsername($username)->first();
        if (!is_null($verify_username) && $product != 5) {
            return redirect()->back()->withInput()->with('error', 'Username already exists.');
        }

        $faker = Faker::create();

        if (!$verify_username) {

            $user = User::create([
                'firstname' => $faker->firstname,
                'lastname' => $faker->lastname,
                'username' => $username,
                'refname' => $username,
                'email' => $faker->unique()->safeEmail,
                'email_verified' => 1,
                'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', // password
                'remember_token' => \Str::random(10),
                'usertype' => 2,
                'statuscode' => 1,
                'password' => bcrypt('1234567'),
                'created_time' => date('H:i:s'),
                'created_dt' => date('Y-m-d H:i:s'),
                'created_date' => date("Y-m-d"),
                'sponsorid' => $sponsor->distid,
                'usertype' => 2,
                'statuscode' => 1,
                'subscription_product' => $product,
                'current_product_id' => $product,
            ]);

            $distId = TSA::generate($user->id);
            $user->distid = $distId;

            # Save the new distId
            $user->save();
        } else {
            $user = $verify_username;
        }

        $order = Order::create([
            'userid' => $user->id,
            'statuscode' => 1,
            'trasnactionid' => 'DEV',
 //           'payment_methods_id' => $paymentMethodId,
            'payment_type_id' => 9,
            'created_date' => date('Y-m-d'),
            'created_time' => date('H:i:s'),
            'created_dt' => date('Y-m-d H:i:s'),
        ]);

        # Always inserts StandbyClass
        $standby = Product::find(1);

        $orderItem = OrderItem::create([
            'orderid' => $order->id,
            'productid' => $standby->id,
            'quantity' => 1,
            'itemprice' => (string)$standby->price,
            'bv' => !empty($standby->bv) ? (string)$standby->bv : 0,
            'qv' => !empty($standby->qv) ? (string)$standby->qv : 0,
            'cv' => !empty($standby->cv) ? (string)$standby->cv : 0,
            'created_date' => date('Y-m-d'),
            'created_time' => date('H:i:s'),
            'created_dt' => date('Y-m-d H:i:s')
        ]);

        # Insert the product choosed by user
        if (!empty($product)) {
            $productdb = Product::find($product);
            $orderItem = OrderItem::create([
                'orderid' => $order->id,
                'productid' => $productdb->id,
                'quantity' => 1,
                'itemprice' => (string)$productdb->price,
                'bv' => !empty($productdb->bv) ? (string)$productdb->bv : 0,
                'qv' => !empty($productdb->qv) ? (string)$productdb->qv : 0,
                'cv' => !empty($productdb->cv) ? (string)$productdb->cv : 0,
                'created_date' => date('Y-m-d'),
                'created_time' => date('H:i:s'),
                'created_dt' => date('Y-m-d H:i:s')
            ]);
        }

        # Insert the ticket choosed by user
        if ($ticket) {
            $ticket = Product::find(38);
            $orderItem = OrderItem::create([
                'orderid' => $order->id,
                'productid' => $ticket->id,
                'quantity' => 1,
                'itemprice' => (string)$ticket->price,
                'bv' => !empty($ticket->bv) ? (string)$ticket->bv : 0,
                'qv' => !empty($ticket->qv) ? (string)$ticket->qv : 0,
                'cv' => !empty($ticket->cv) ? (string)$ticket->cv : 0,
                'created_date' => date('Y-m-d'),
                'created_time' => date('H:i:s'),
                'created_dt' => date('Y-m-d H:i:s')
            ]);
        }

        $request = new Request([
            'user_id'    => $user->id,
            'order_id'   => $order->id,
            'event_type' => ($product == 5) ? 'upgrade' : 'enrollment'
        ]);

        if ($overview = $this->events->create($request)) {

            return redirect('/worldseries')->with([
                'success'  => 'The username: '.$username.' with distid: '.$user->distid.' from the sponsor: '.$sponsor->distid.' is in the game, with order: '.$order->id,
                'sponsor'  => $sponsor->distid,
            ]);
        }
    }


    # Retrieving the data by the Owner for fill the Diamond and Owner Score
    public function resumeOwner(Request $request) 
    {
        $overview = WorldSeriesOverviews::whereSponsorId($request->get('sponsorId'))
            ->whereMonth('season_period', $this->checkMonth($request->date))
            ->with([
                'firstBaseUser.currentProduct', 
                'secondBaseUser.currentProduct', 
                'thirdBaseUser.currentProduct',
                'firstUserEvent.hasTicket',
                'secondUserEvent.hasTicket',
                'thirdUserEvent.hasTicket'
            ])
            ->first();
        
        # If dont' have score, returns blank.
        if (is_null($overview)) {
            $overview = [
                'runs' => 0,
                'hits' => 0,
                'errors' => 0,
                'total' => 0,
                'first_base_user_id' => null,
                'second_base_user_id' => null,
                'third_base_user_id' => null,
            ];
        }

        $this->setResponse(['resume' => $overview]);
        $this->setResponseCode(200);

        return $this->showResponse();
    }


    # Retrieving the data by the Player for fill the Player Score
    public function resumePlayer(Request $request) 
    {       
        $overview = WorldSeriesEvents::whereUserId($request->get('user_id'))
            ->whereMovedByUserId(null)
            ->first();

        $data = [
            'runs'   => !is_null($overview) && $overview->position == 4 ? 1 : 0,
            'hits'   => !is_null($overview) && $overview->position > 0 ? 1 : 0,
            'errors' => 0,
            'total'  => 0,
        ];

        $this->setResponse(['resume' => $data]);
        $this->setResponseCode(200);

        return $this->showResponse();
    }


    # Retrieving the top teams by the Owner
    public function resumeTopTeams(Request $request) 
    {       
        $limit = $request->get('limit', 5);
        $month = $request->get('month', date('m'));

        $date = date('Y-'.$month.'-d');

        $overview = WorldSeriesOverviews::with(['sponsor'])
            ->whereMonth('season_period', $this->checkMonth($date))
            ->orderBy('runs', 'DESC')
            ->orderBy('hits', 'DESC')
            ->orderBy('updated_at', 'DESC')
            ->orderBy('sponsor_id', 'DESC')
            ->limit($limit)
            ->get();

        $this->setResponse(['resume' => $overview]);
        $this->setResponseCode(200);

        return $this->showResponse();
    }

    public function checkMonth($date)
    {
        if($date){
            $month = \Carbon\Carbon::create($date)->endOfMonth()->month;
        }else{
            $month = \Carbon\Carbon::now()->endOfMonth()->month;
        }

        return $month;
    }

}
