<?php

namespace App\Http\Model;

use Illuminate\Database\Eloquent\Model;

class MsgCheck extends Base
{
    protected $table = 'u_msg_check';
    public $timestamps = false;

    protected $guarded = ["id", "created_time"];
}