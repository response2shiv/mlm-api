<?php
namespace App\Contracts;

use Illuminate\Database\Eloquent\Model;

interface PayOutControlContract{
    public function getType(): string;
    public function addUser($userId, $transactionRefId): Model;
    public function getPayoutByUserId($id): Model;
    public function commission(string $user_name, int $user_id, float $amount): array;
    public function checkout(string $user_name, int $user_id, float $amount): array;
    public function createUser(\App\Models\User $user): array;
    public function checkUser(\App\Models\User $user): array;
}