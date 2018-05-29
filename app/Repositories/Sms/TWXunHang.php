<?php

namespace App\Repositories\Sms;

class TWXunHang
{
    private $config = '';

    public function __construct($config)
    {
        $this->config = $config;
    }

    /**
     * 发送模板短信接口
     * @param $phone
     * @param $templateId
     * @param string $content
     * @return bool
     */
    public function sendTemplate()
    {
    }

    /**
     * 发送文字短信接口
     * @param $phone
     * @param $content
     * @return bool
     */
    public function send($phone, $content)
    {
        $client = new \GuzzleHttp\Client();
        $data = [
            "yhy" => $this->config["sms_account"],
            "dc2a" => $this->config["sms_pwd"],
            "movetel" => $phone,
            "sms_mv" => 'GB2',
            "sb" => $content
        ];

        $response = $client->request("POST", 'http://smsmo.smse.com.tw/STANDARD/SMS_FU.ASP',
            ["form_params" => $data,
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded'
            ]]);
        $result = $response->getBody()->getContents();//很关键
        $ret = $result === 'OK_1';
        \Log::info($data + (array)$result + ["position" => "台湾迅航短信发送日志"]);
        if (!$ret) {
            \Log::info($data + (array)$result + ["position" => "短信发送错误"]);
        }
        return $ret;
    }
}