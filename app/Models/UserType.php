<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserType extends Model
{
    public $timestamps = false;

    // user type
    const TYPE_ADMIN = 1;
    const TYPE_DISTRIBUTOR = 2;
    // admin role
    const ADMIN_SUPER_ADMIN = 1;
    const ADMIN_SUPER_EXEC = 2;
    const ADMIN_SALES = 3;
    const ADMIN_CS_MGR = 4; // CUSTOMER SERVICE MANAGER
    const ADMIN_CS = 5; // CUSTOMER SERVICE REP
    const ADMIN_CS_EXEC = 6; // CUSTOMER SERVICE EXEC
}
