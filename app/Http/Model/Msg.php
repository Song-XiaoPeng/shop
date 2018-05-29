<?php

namespace App\Http\Model;

use Illuminate\Database\Eloquent\Model;

class Msg extends Base
{
    protected $table = 'u_msg';

    public $timestamps = false;

    protected $guarded = ["id"];

}