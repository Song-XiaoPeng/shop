<?php

namespace App\Repositories;

use App\Repositories\Sms\Sms;
use App\Http\Model\MsgCheck;
use Illuminate\Container\Container as Application;
use App\Http\Model\Msg;
use Illuminate\Http\Request;

class SmsRepository extends Base
{
    const VERIFY_CODE_INVALID_MINUTES = 10;
    const VERIFY_CODE_EXPIRE_MINUTES = 1;
    private $sms = null;
    private $agent = null;

    //发送验证码
    public function sendVerify($phone, $remark = "", $isRegister = false)
    {
        $this->init();
        $sendRecord = MsgCheck::where("cellphone", $phone)->orderBy("create_time", "desc")->first();
        if ($sendRecord && $sendRecord->create_time) {
            if (strtotime($sendRecord->create_time) + self::VERIFY_CODE_EXPIRE_MINUTES * 60 > time()) {
                $this->setErrorMsg("用户发送短信太频繁");
                return false;
            }
        }

        $code = $this->createCode();
        $template = $isRegister ? REGISTER_VERIFY_SMS_TEMLATE_CONTENT : VERIFY_SMS_TEMLATE_CONTENT;
        $ret = $this->sms->send($phone, sprintf($template, $code));
        if (!$ret) return false;

        $data = [
            "cellphone" => $phone,
            "check_code" => $code,
            "create_time" => date("Y-m-d H:i:s"),
            "invalid_time" => date("Y-m-d H:i:s",
                strtotime("+" . self::VERIFY_CODE_INVALID_MINUTES . " minutes")),
            "type_remark" => $remark,
            "agent_id" => $this->agent->id,
        ];
        $ret = MsgCheck::create($data);
        if ($ret) {
            $this->recordSms($phone, sprintf($template, $code), 9);
        }

        return $ret;
    }

    //检测验证码
    public function checkVerify($phone, $code)
    {
        $sendRecord = MsgCheck::where("cellphone", $phone)->orderBy("id", "desc")->first();
        if (!$sendRecord || $sendRecord->check_code != $code ||
            strtotime($sendRecord->invalid_time) < time()) {
            return false;
        }

        return true;
    }

    public function __call($name, $arguments)
    {
        $this->init($arguments[5] ?? null);
        $ret = false;
        $platformName = $this->agent->platform_name;
        $phone = $arguments[0] ?? "";
        $msgType = 1;
        if ($name == "certification") {
            $content = sprintf(CERTIFICATION_SMS_TEMLATE_CONTENT, $platformName);
            $msgType = 1;
        } else if ($name == "registerSuccess") {
            $content = sprintf(REGISTER_SUCCESS_SMS_TEMLATE_CONTENT, $platformName);
            $msgType = 2;
        } else if ($name == "rechargeSuccess") {
            $content = sprintf(RECHARGE_SUCCESS_SMS_TEMLATE_CONTENT, $arguments[1], $platformName);
            $msgType = 6;
        } else if ($name == "withdraw") {
            $arguments[2] = round($arguments[2], 2);
            $content = sprintf(WITHDRAW_SMS_TEMLATE_CONTENT, $arguments[1], $arguments[2]);
            $msgType = 7;
        } else if ($name == "optionsIncome") {
            $content = sprintf(OPTIONS_INCOME_TEMLATE_CONTENT, $arguments[1], $platformName);
            $msgType = 101;
        } else if ($name == "optionsIncomeNotice") {
            $content = sprintf(OPTIONS_INCOME_NOTICE_TEMLATE_CONTENT);
            $msgType = 102;
        } else if ($name == "optionsExpenses") {
            $content = sprintf(OPTIONS_EXPENSES_TEMLATE_CONTENT, $arguments[1], $platformName, $platformName);
            $msgType = 103;
        } else if ($name == "optionsVerifyProduct") {
            $content = sprintf(OPTIONS_VERIFY_PRODUCT_TEMLATE_CONTENT, $arguments[1]);
            $msgType = 104;
        }

        $sendRecord = MsgCheck::where("cellphone", $phone)->orderBy("create_time", "desc")->first();
        if ($sendRecord && $sendRecord->create_time) {
            if (strtotime($sendRecord->create_time) + self::VERIFY_CODE_EXPIRE_MINUTES * 60 > time()) {
                $this->setErrorMsg("用户发送短信太频繁");
                return false;
            }
        }

        if ($phone && $content) {
            $ret = $this->sms->send($phone, $content);
        }

        if ($ret) {
            return $this->recordSms($phone, $content, $msgType);
        }
        return false;
    }

    private function init($user = null)
    {
        $user = $user ?: request()->user();
        $this->agent = $agent = getAgent($user);
        $this->sms = Sms::factory($agent);
    }

    //创建验证码
    private function createCode()
    {
        $str = "0123456789";
        $code = "";
        for ($i = 0; $i < 4; $i++) {
            $code .= $str{rand(0, strlen($str) - 1)};
        }
        return $code;
    }

    /**
     * 记录发送短信
     * @param $phone
     * @param $content
     */
    private function recordSms($phone, $content, $msgType = null)
    {
        $request = request();
        $user = $request->user();

        $data = [
            "cellphone" => $phone,
            "msg_type" => $msgType ?: 1,
            "send_time" => date("Y-m-d H:i:s"),
            "msg_content" => $content,
            "status" => 1,
            "agent_id" => $this->agent->id,
        ];
        if ($user) {
            $data["cust_id"] = $user->id;
            $agent = getUserAgent($user);
            $data["agent_id"] = $agent->id;
        }

        return Msg::create($data);
    }
}