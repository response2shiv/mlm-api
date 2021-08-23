<?php

namespace App\Jobs;

use App\Helpers\Util;
use App\Models\User;
use App\Models\Order;
use App\Services\BucketService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DistributeVolumes implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */

    private $user;
    private $order;

    public function __construct(User $user, Order $order)
    {
        $this->user = $user;
        $this->order = $order;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {

        BucketService::createNewUserBucketVolume($this->user, $this->order->orderbv, $this->order->ordercv, $this->order->orderqv);
        info("user created empty bucket volume");

        BucketService::distributeDirectLineVolumes($this->user, $this->order);
        info("Volumes distributed at bucket sponsors");

        BucketService::recalculatePEV($this->user);
        info("the PEV of sponsors calculated");


        $this->order->is_distributed = true;
        $this->order->distributed_at = now(Util::USER_TIME_ZONE);
        $this->order->save();

        info("order updated");
    }
}
