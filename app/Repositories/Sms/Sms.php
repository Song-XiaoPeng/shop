<?php

namespace App\Repositories\Sms;


class Sms
{
    private static $options = [
        "1" => "Feige",
        "2" => "TWXunHang"
    ];

    public static function factory($agent)
    {
        $config = [
            "sms_account" => $agent->sms_account,
            "sms_pwd" => $agent->sms_pwd,
            "sms_sign_id" => $agent->sms_sign_id,
            "sms_captcha_template_id" => $agent->sms_captcha_template_id
        ];
        $type = !empty($config['sms_captcha_template_id'])?$config['sms_captcha_template_id']:1;
        $path ='App\Repositories\Sms\\' . self::$options[$type];
        return new $path($config);
    }
}