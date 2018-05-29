<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Repositories\SmsRepository;
use App\User;
use Illuminate\Http\Request;
use App\Repositories\RegisterRepository;

/**
 * 注册
 * Class RegisterController
 * @package App\Http\Controllers\Api
 */
class RegisterController extends Controller
{
    static $password_reg = "/^[\w\?%&=\-_]{6,20}$/";
    private $register = null;
    private $sms = null;

    public function __construct(RegisterRepository $register, SmsRepository $sms)
    {
        $this->register = $register;
        $this->sms = $sms;
    }

    /**
     * 注册
     * @param Request $request
     * @return mixed
     */
    public function register(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            "cellphone" => ["required", "regex:/^1[0-9]{10}$/", "unique:u_customer,cellphone"],
            "nick_name" => "between:0,20",
            "password" => "required",
            "phoneCode" => "required",
            "recCode" => "required|min:1",
        ], [
            "cellphone.required" => "手机号码不能为空",
            "password.required" => "密码不能为空",
            "cellphone.regex" => "请填写正确的手机号码",
            "cellphone.unique" => "手机号码已经被注册",
            "nick_name.between" => "昵称格式应该为1-20字符",
            //"password.regex" => "密码应为6-20位数字或字符或特殊符号组成",
            "phoneCode.required" => "手机验证码不能为空",
            "recCode.required" => "推荐码不能为空",
            "recCode.min" => "推荐码不能为空",
        ]);

        if ($validator->fails()) {
            return parent::jsonReturn([], parent::CODE_FAIL, $validator->errors()->first());
        }

        $checkPhoneCode = $this->sms->checkVerify($request->get("cellphone"), $request->get("phoneCode"));
        if (!$checkPhoneCode) {
            return parent::jsonReturn([], parent::CODE_FAIL, "短信验证码错误");
        }

        $data = $request->only(["cellphone", "nick_name", "recCode"]);
        $data['password'] = opensslDecode($request->get("password"));
        $data = array_merge($data, [
            "reg_source" => 0,
            "reg_ip" => getRealIp(),
        ]);
        $ipInfo = getIpInfo($data["reg_ip"]);
        if ($ipInfo) {
            $data["ip_location"] = $ipInfo["country"] . $ipInfo["region"] . $ipInfo["city"] . $ipInfo["isp"];
        }

        $ret = $this->register->register($data);
        if ($ret) {
            //发送短信
            $this->sms->registerSuccess($request->get("cellphone"));
        }
        return $ret ? parent::jsonReturn([], parent::CODE_SUCCESS, "注册成功") :
            parent::jsonReturn([], parent::CODE_FAIL,
                $this->register->getErrorMsg() ?: "注册失败");
    }

    /**
     * 找回密码
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getBackPassword(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            "cellphone" => ["required", "regex:/^1[0-9]{10}$/"],
            "password" => "required",
            "phoneCode" => "required",
        ], [
            "cellphone.required" => "手机号码不能为空",
            "password.required" => "密码不能为空",
            "cellphone.regex" => "请填写正确的手机号码",
            //"password.regex" => "密码应为6-20位数字或字符或特殊符号组成",
            "phoneCode.required" => "手机验证码不能为空",
        ]);

        $password = opensslDecode($request->get("password"));

        if ($validator->fails()) {
            return parent::jsonReturn([], parent::CODE_FAIL, $validator->errors()->first());
        }

        $checkPhoneCode = $this->sms->checkVerify($request->get("cellphone"), $request->get("phoneCode"));
        if (!$checkPhoneCode) {
            return parent::jsonReturn([], parent::CODE_FAIL, "短信验证码错误");
        }

        $ret = $this->register->getBackPassword($request->get("cellphone"), $password);
        return $ret ? parent::jsonReturn([], parent::CODE_SUCCESS, "提交成功") :
            parent::jsonReturn([], parent::CODE_FAIL, "提交失败");
    }

    /**
     * 微信绑定账户
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function bindAccountFromWechat(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            "cellphone" => ["required", "regex:/^1[0-9]{10}$/"],
            "phoneCode" => "required",
            "openid" => "required|min:1",
            "phoneCode" => "required",
        ], [
            "cellphone.required" => "手机号码不能为空",
            "cellphone.regex" => "请填写正确的手机号码",
            "phoneCode.required" => "手机验证码不能为空",
            "openid.required" => "建议先取消关注该公众号再重新关注",
            "openid.min" => "建议先取消关注该公众号再重新关注",
        ]);

        if ($validator->fails()) {
            return parent::jsonReturn([], parent::CODE_FAIL, $validator->errors()->first());
        }

        $checkPhoneCode = $this->sms->checkVerify($request->get("cellphone"), $request->get("phoneCode"));
        if (!$checkPhoneCode) {
            return parent::jsonReturn([], parent::CODE_FAIL, "短信验证码错误");
        }

        $data = $request->only(["cellphone", "nick_name", "password", "recCode", "openid"]);
        $data = array_merge($data, [
            "reg_source" => 0,
            "reg_ip" => getRealIp(),
        ]);
        $ipInfo = getIpInfo($data["reg_ip"]);
        if ($ipInfo) {
            $data["ip_location"] = $ipInfo["country"] . $ipInfo["region"] . $ipInfo["city"] . $ipInfo["isp"];
        }

        $ret = $this->register->bindAccountFromWechat($data);
        if ($ret) {
            $res = apiLogin($request->get("cellphone"), $ret["password"]);
            return parent::jsonReturn($res, parent::CODE_SUCCESS, "绑定成功");
        } else {
            return parent::jsonReturn([], parent::CODE_FAIL,
                $this->register->getErrorMsg() ?: "绑定错误");
        }
    }

    /**
     * 检测用户是否注册
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkIsRegistered(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            "cellphone" => ["required", "regex:/^1[0-9]{10}$/"],
        ], [
            "cellphone.required" => "手机号码不能为空",
            "cellphone.regex" => "请填写正确的手机号码",
        ]);

        if ($validator->fails()) {
            return parent::jsonReturn([], parent::CODE_FAIL, $validator->errors()->first());
        }

        $ret = (int)$this->register->getUserInfo($request->get("cellphone"));
        return $ret ? parent::jsonReturn([]) :
            parent::jsonReturn([], parent::CODE_FAIL, $this->sms->getErrorMsg() ?: "未注册");
    }

    /**
     * 发送短信（注册）
     * @param Request $request
     * @param bool $isCheckUnique
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendSms(Request $request, $isCheckUnique = true, $isRegister = true)
    {
        $validates = ["cellphone" => ["required", "regex:/^1[0-9]{10}$/"]];
        $contents = ["cellphone.required" => "手机号不能为空", "cellphone.regex" => "请填写正确的手机号码",];
        if ($isCheckUnique) {
            $validates["cellphone"][] = "unique:u_customer,cellphone";
            $contents["cellphone.unique"] = "该手机号已被注册";
        }
        $validator = \Validator::make($request->all(), $validates, $contents);

        if ($validator->fails()) {
            return parent::jsonReturn([], parent::CODE_FAIL, $validator->errors()->first());
        }

        $ret = $this->sms->sendVerify($request->get("cellphone"), "注册验证码、找回密码验证码", $isRegister);
        return $ret ? parent::jsonReturn([], parent::CODE_SUCCESS, "短信验证码获取成功") :
            parent::jsonReturn([], parent::CODE_FAIL, $this->sms->getErrorMsg() ?: "发送错误");
    }

    /**
     * 发送短信（找回密码）
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendGetBackSms(Request $request)
    {
        $user = User::where("cellphone", $request->get("cellphone"))->first();
        if (!$user) return parent::jsonReturn([], parent::CODE_FAIL, "该手机号用户不存在");

        return $this->sendSms($request, false, false);
    }

    /**
     * 发送短信（微信绑定）
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendWechatBindSms(Request $request)
    {
        return $this->sendSms($request, false, false);
    }
}