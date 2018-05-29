<?php

namespace App\Repositories\Sms;

class Feige
{
    private $defaultSmsConfig = [
        "account" => "",
        "pwd" => "",
        "signId" => 0,
        "templateSmsUrl" => "http://api.feige.ee/SmsService/Template",
        "apiSmsUrl" => "http://api.feige.ee/SmsService/Send",
    ];

    static private $requestHeaders = [
        "User-Agent" => "sms",
    ];

    public function __construct($config)
    {
        $this->defaultSmsConfig['account'] = $config['sms_account'];
        $this->defaultSmsConfig['pwd'] = $config['sms_pwd'];
        $this->defaultSmsConfig['signId'] = $config['sms_sign_id'];
    }

    /**
     * 发送模板短信接口
     * @param $phone
     * @param $templateId
     * @param string $content
     * @return bool
     */
    public function sendTemplate($phone, $templateId, $content = "1")
    {
        $client = new \GuzzleHttp\Client();
        $data = [
            "Account" => $this->defaultSmsConfig["account"],
            "Pwd" => $this->defaultSmsConfig["pwd"],
            "Content" => $content,
            "Mobile" => $phone,
            "TemplateId" => $templateId,
            "signId" => $this->defaultSmsConfig["signId"],
        ];
        $response = $client->request("post", $this->defaultSmsConfig["templateSmsUrl"],
            ["query" => $data], static::$requestHeaders);
        $result = json_decode($response->getBody(), true);
        return $result && $result["Code"] === 0;
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
            "Account" => $this->defaultSmsConfig["account"],
            "Pwd" => $this->defaultSmsConfig["pwd"],
            "Content" => $content,
            "Mobile" => $phone,
            "signId" => $this->defaultSmsConfig["signId"],
        ];
        $response = $client->request("post", $this->defaultSmsConfig["apiSmsUrl"],
            ["query" => $data], static::$requestHeaders);
        $result = json_decode($response->getBody(), true);
        $ret = $result && $result["Code"] === 0;
        if (!$ret) {
            \Log::info($data + (array)$result + ["position" => "短信发送错误"]);
        }
        \Log::info($data + (array)$result + ["position" => "飞鸽短信"]);
        return $ret;
    }
}