<?php

namespace App\Repositories;

use App\Http\Controllers\Api\RegisterController;
use App\Http\Model\Agent;
use App\Http\Model\AgentEmpPercentageSetting;
use App\Http\Model\MemberAgentRelation;
use App\Http\Model\MemberFeeRate;
use App\Http\Model\RecCode;
use App\Http\Model\WexingConcern;
use App\User;
use EasyWeChat\Core\Exceptions\HttpException;
use Illuminate\Support\Facades\DB;
use App\Http\Model\AgentPercentageSetting;
use App\Http\Controllers\Api\WechatController;
use Mockery\Exception;

class RegisterRepository extends Base
{
    const CUSTOMER_TYPE_CODE = 0;   //客户
    const USER_TYPE_CODE = 1;   //员工
    const AGENT_TYPE_CODE = 2;  //代理
    const PERCENTAGE_TIANPEI_TYPE_CODE = 0;   //天配
    const PERCENTAGE_YUEPEI_TYPE_CODE = 1;   //月配
    const PERCENTAGE_FEE_TYPE_CODE = 2;   //手续费
    const PERCENTAGE_LEVEL1_TYPE_CODE = 3;   //一级返佣
    const PERCENTAGE_LEVEL2_TYPE_CODE = 4;   //二级返佣

    /**
     * 注册
     * @param $data
     * @return bool
     */
    public function register($data)
    {
        //检测推荐码
        $isTrueRecCode = RecCode::where("rec_code", $data["recCode"])->count();
        if (!$isTrueRecCode || !$data["recCode"]) {
            $this->setErrorMsg("推荐码填写错误");
            return false;
        }

        $user = $this->make($data);
        if (!$user) return false;

        $custId = $user->id;
        $recCode = $data["recCode"] ?? "";
        $relationData = $this->setRelation($custId, $recCode);
        $this->setFeeRate($relationData);
        $this->setRecCode($user, $recCode, $relationData["direct_agent_id"]);

        return true;
    }

    /**
     * 绑定微信和账号
     * @param $data
     */
    public function bindAccountFromWechat($data)
    {
        $user = User::where(CUSTOMER_USERNAME_FIELD, $data["cellphone"])->first();
        $openId = $data["openid"];

        $concern = WexingConcern::where("open_id", $openId)->first();
        $hasBind = User::where("openid", "like", "%$openId%")->count();

        if (!$concern || $hasBind) {
            \Log::info($data + ["position" => "微信无法绑定"]);
            $this->setErrorMsg("建议先取消关注该公众号再重新关注");
            return false;
        }

        $data["head_pic"] = $concern->head_pic;
        $data["nick_name"] = $concern->nick_name;

        if ($user) {
            //登录
            if ($user->is_login_forbidden) {
                $this->setErrorMsg("账号已被禁用，请联系客服人员");
                return false;
            }

            $openIds = array_filter(explode(",", $user->openid));
            $openIds[] = $openId;
            $openIds = array_unique($openIds);
            $data["openid"] = implode(",", $openIds);

            $info = $this->createRegisterData($data);
            return $user->update($info) ? ["password" => passwordEncrypt($user->password)] : false;
        } else {
            //检测密码
            /*if (!preg_match(RegisterController::$password_reg, $data["password"])) {
                $this->setErrorMsg("密码应为6-20位数字或字符或特殊符号组成");
                return false;
            }*/

            //检测推荐码
            $isTrueRecCode = RecCode::where("rec_code", $data["recCode"])->count();
            if (!$isTrueRecCode || !$data["recCode"]) {
                $this->setErrorMsg("推荐码填写错误");
                return false;
            }

            //注册,和正常注册一样
            $user = $this->make($data);
            if (!$user) return false;

            $custId = $user->id;
            $recCode = $data["recCode"];
            $relationData = $this->setRelation($custId, $recCode);
            $this->setFeeRate($relationData);
            $this->setRecCode($user, $recCode, $relationData["direct_agent_id"]);

            return ["password" => $data["password"]];
        }
    }

    public function getUserInfo($phone)
    {
        $ret = User::where("cellphone", $phone)->first();
        return $ret ? $ret->toArray() : null;
    }

    /**
     * 写入用户信息
     * @param $data
     * @return mixed
     */
    private function make($data)
    {
        $info = $this->createRegisterData($data);
        return $this->create($info);
    }

    private function createRegisterData($data)
    {
        $info = [
            CUSTOMER_USERNAME_FIELD => $data["cellphone"],
            "reg_source" => $data["reg_source"],
            "reg_ip" => $data["reg_ip"],
            "ip_location" => $data["ip_location"] ?? "",
        ];
        if (isset($data["password"]) && $data["password"]) $info["password"] = encryptPassword($data["password"]);
        if (isset($data["nick_name"])) $info["nick_name"] = $data["nick_name"];
        if (isset($data["head_pic"])) $info["avatar"] = $data["head_pic"];
        if (isset($data["openid"])) $info["openid"] = $data["openid"];
        return $info;
    }

    /**
     * 建立用户关系
     * @param $custId
     * @param $recCode
     * @return int
     */
    private function setRelation($custId, $recCode)
    {
        $codeRecord = RecCode::where("rec_code", $recCode)->first();

        $agentList = [];    //推荐代理列表
        $custList = [];     //推荐客户列表
        $directCust = 0;
        $directAgent = 0;
        $emp = 0;

        if ($codeRecord) {
            $userType = $codeRecord->user_type;
            if ($userType == self::CUSTOMER_TYPE_CODE) {
                //如果推荐人为用户,直接去查上级客户的关系表
                $relationRecord = MemberAgentRelation::where("cust_id", $codeRecord->user_id)->first();
                if ($relationRecord) {
                    for ($i = 2; $i < 6; $i++) {
                        $t = "agent" . $i;
                        if ($relationRecord->{$t}) {
                            $agentList[] = $relationRecord->{$t};
                        }
                    }
                    //一级客户为被推荐客户的直接推荐人
                    $custList = [$codeRecord->user_id, $relationRecord->cust1 ?: null];
                    $emp = $relationRecord->direct_emp_id;
                }
            } else if ($userType == self::USER_TYPE_CODE || $userType == self::AGENT_TYPE_CODE) {
                //如果推荐人为员工或者代理商
                if ($userType == self::USER_TYPE_CODE) {
                    //如果推荐人为员工
                    $emp = $codeRecord->user_id;
                    $empInfo = DB::table("a_agent_emp")->where("id", $emp)->first();
                    $agentId = $empInfo ? $empInfo->agent_id : 0;
                } else {
                    //如果推荐人为代理商
                    $agentId = $codeRecord->user_id;
                }

                $agentLevel = 5;
                //遍历出多级代理
                while ($agentLevel > 2) {
                    $agent = Agent::where("id", $agentId)->where("agent_level", "!=", 1)->first();
                    if ($agent) {
                        array_unshift($agentList, $agent->id);
                        $agentLevel = $agent->agent_level;
                        $agentId = $agent->parent_id;
                    } else {
                        $agentLevel = 1;
                    }
                }
            }
        }

        $defaultAgent = getDefaultAgent();
        array_unshift($agentList, $defaultAgent ? $defaultAgent->id : 0);
        $directAgent = (int)end($agentList);
        $directCust = $custList[0] ?? 0;
        $data = [
            "cust_id" => $custId,
            "direct_cust_id" => $directCust ?: null,
            "direct_agent_id" => $directAgent ?: null,
            "agent1" => $agentList[0],
            "agent2" => $agentList[1] ?? null,
            "agent3" => $agentList[2] ?? null,
            "agent4" => $agentList[3] ?? null,
            "agent5" => $agentList[4] ?? null,
            "direct_emp_id" => $emp ?: null,
            "belong_to_agent" => $directAgent && $emp ? $directAgent : null,
            "cust1" => $custList[0] ?? null,
            "cust2" => $custList[1] ?? null,
        ];
        $ret = MemberAgentRelation::create($data);
        if (!$ret) {
            \Log::info($data + ["position" => "注册用户生成关系错误"]);
        }

        return $data;
    }

    /**
     * @param $relation
     */
    private function setFeeRate($relation)
    {
        unset($relation["direct_cust_id"], $relation["belong_to_agent"]);
        $relation = array_merge($relation, ["emp_id" => $relation["direct_emp_id"], "agent1_rate" => 1]);
        $feeRate0 = $feeRate1 = $feeRate2 = $relation;
        $feeRate0["type"] = 0;
        $feeRate1["type"] = 1;
        $feeRate2["type"] = 2;
        //获取多个代理商分成设置记录
        $agentPercentageSettings = AgentPercentageSetting::where(function ($query) use ($relation) {
            for ($i = 1; $i <= 5; $i++) {
                $field = "agent" . $i;
                if ($relation[$field] < 1) break;
                $agentId = $relation[$field];

                $query->orWhere(function ($q) use ($agentId) {
                    $q->where("agent_id", $agentId)->where("type", self::PERCENTAGE_TIANPEI_TYPE_CODE);
                })->orWhere(function ($q) use ($agentId) {
                    $q->where("agent_id", $agentId)->where("type", self::PERCENTAGE_YUEPEI_TYPE_CODE);
                })->orWhere(function ($q) use ($agentId) {
                    $q->where("agent_id", $agentId)->where("type", self::PERCENTAGE_FEE_TYPE_CODE);
                });
            }
        })->get();
        foreach ($agentPercentageSettings as $v) {
            $type = $v->type;
            $t = &${"feeRate" . $type};

            for ($i = 1; $i <= 5; $i++) {
                if ($relation["agent{$i}"] == $v["agent_id"]) {
                    $t = array_merge($t, [
                        "agent{$i}_rate" => $v["percentage"],
                    ]);
                }
            }
        }

        //如果客户有推荐客户
        if ($relation["cust1"]) {
            $custPercentageSettings = AgentPercentageSetting::where("agent_id", $relation["direct_agent_id"])
                ->where(function ($query) {
                    $query->where("type", self::PERCENTAGE_LEVEL1_TYPE_CODE)
                        ->orWhere("type", self::PERCENTAGE_LEVEL2_TYPE_CODE);
                })->get();

            foreach ($custPercentageSettings as $v) {
                $type = $v->type;
                if ($type == self::PERCENTAGE_LEVEL1_TYPE_CODE && $relation["cust1"]) {
                    $feeRate0["cust1_rate"] = $feeRate1["cust1_rate"] = $feeRate2["cust1_rate"] = $v["percentage"];
                } else if ($type == self::PERCENTAGE_LEVEL2_TYPE_CODE && $relation["cust2"]) {
                    $feeRate0["cust2_rate"] = $feeRate1["cust2_rate"] = $feeRate2["cust2_rate"] = $v["percentage"];
                }
            }
        }

        //如果用户有推荐员工
        if ($relation["direct_emp_id"]) {
            $agentEmpPercentageSettings = AgentEmpPercentageSetting::where("employee_id", $relation["direct_emp_id"])->
            where(function ($query) {
                $query->where("type", self::PERCENTAGE_TIANPEI_TYPE_CODE)->orWhere("type",
                    self::PERCENTAGE_YUEPEI_TYPE_CODE)->orWhere("type", self::PERCENTAGE_FEE_TYPE_CODE);
            })->get();

            foreach ($agentEmpPercentageSettings as $v) {
                $type = $v->type;
                $t = &${"feeRate" . $type};
                $t = array_merge($t, [
                    "emp_rate" => $v["percentage"],
                ]);
            }
        }

        unset($feeRate0["direct_emp_id"], $feeRate1["direct_emp_id"], $feeRate2["direct_emp_id"]);
        $ret1 = MemberFeeRate::create($feeRate0);
        $ret2 = MemberFeeRate::create($feeRate1);
        $ret3 = MemberFeeRate::create($feeRate2);
        if (!$ret1 || !$ret2 || !$ret3) {
            \Log::info([["ret1" => $ret1] + $feeRate0] + [["ret2" => $ret2] + $feeRate1] + [["ret3" => $ret3] + $feeRate2]
                + ["position" => "注册用户生成比例关系错误"]);
        }
    }

    /**
     * 找回密码
     * @param $phone
     * @param $password
     * @return bool
     */
    public function getBackPassword($phone, $password)
    {
        $user = User::where("cellphone", $phone)->first();
        if (!$user) return false;
        return $user->update(["password" => encryptPassword($password)]);
    }

    /**
     * 设置用户邀请码相关
     * @param $user
     * @param $recCode
     * @param $directAgent
     */
    private function setRecCode($user, $recCode)
    {
        $code = createRecCode();
        $ret = RecCode::create([
            "user_type" => self::CUSTOMER_TYPE_CODE,
            "user_id" => $user->id,
            "rec_code" => $code,
        ]);

        $qrCode = self::makeQrcode($code, $user) ?: "";

        $agent = getAgent($user);
        if ($ret) {
            $data = array(
                "cust_rec_code" => $code,
                "rec_code" => $recCode,
                "bar_code" => $qrCode,     //TODO:根据直属代理商公众号生成关注二维码
                "pc_adv_url" => "http://" . $agent->web_domain . "/register?code={$code}",
                "phone_adv_url" => "http://" . $agent->mobile_domain . "/register?code={$code}",
            );

            $ret = $user->update($data);
            if (!$ret) {
                \Log::info($data + ["position" => "注册用户推荐码信息错误"]);
            }
        }
    }

    /**
     * 生成关注微信的二维码
     * @param $code
     * @return bool|string
     */
    static public function makeQrCode($code, $user)
    {
        $wechat = getWechat($user);
        try {
            $img = $wechat->makeQrCode($code);
        } catch (\Exception $e) {
            \Log::info('生成关注微信的二维码出错-' . $e->getMessage());
            return false;
        }

        $object = time() . rand(1, 99999) . $code . ".jpg";
        $ret = ossUpload($object, $img, "qrCode");
        return $ret;
    }

}