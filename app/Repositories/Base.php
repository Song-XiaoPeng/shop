<?php

namespace App\Repositories;

use Illuminate\Container\Container as Application;
use Prettus\Repository\Eloquent\BaseRepository;

class Base extends BaseRepository
{
    protected $errorMsg = "";

    public function model()
    {
        return 'App\User';
    }

    public function setErrorMsg($msg)
    {
        $this->errorMsg = $msg;
    }

    public function getErrorMsg()
    {
        return $this->errorMsg;
    }

}
