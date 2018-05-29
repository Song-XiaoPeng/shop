<?php

namespace App\Repositories;

use App\Http\Model\CustBankCard;
use App\Http\Model\MemberAgentRelation;
use App\Repositories\Option\OptionRepository;
use Mockery\Exception;

class UserDataRepository extends Base
{
    const NOTICE_CHG_PWD = 1;

    public function getUserInfo($user)
    {
        $notice_type = $user->notice_type;

        $notice_type_msg = $this->getNoticeTypeMsg($notice_type);
        $userInfo = [
            "cust_id" => $user->id,
            "cellphone" => half_replace($user->cellphone),
            "nick_name" => $user->nick_name,
            "has_set_withdraw_password" => $user->withdraw_pw ? 1 : 0,
            "real_name" => $user->real_name,
            "cust_rec_code" => $user->cust_rec_code,
            "bar_code" => $user->bar_code,
            "pc_adv_url" => $user->pc_adv_url,
            "phone_adv_url" => $user->phone_adv_url,
            "cust_capital_amount" => substr(sprintf("%.4f", $user->cust_capital_amount), 0, -2),
            "is_cash_forbidden" => $user->is_cash_forbidden,
            "is_login_forbidden" => $user->is_login_forbidden,
            "is_charge_forbidden" => $user->is_charge_forbidden,
            "is_stock_finance_forbidden" => $user->is_stock_finance_forbidden,
            "avatar" => $user->avatar,
            "id_card" => $user->id_card,
            "notice_pwd_chg" => $notice_type_msg
        ];

        $this->resetNoticeType($user,$notice_type);

        $bankCards = $user->bankCard()->get()->toArray();
        array_walk($bankCards, function ($v, $k) use (&$bankCards) {
            $bankCards[$k]["bank_card"] = half_replace($v["bank_card"]);
        });
        $userInfo["bankCards"] = $bankCards;

        //判断期权相关
        try {
            $userInfo["is_open_options_account"] = OptionRepository::getUserOptionAccount($user->id) ? 1 : 0;

            $relation = MemberAgentRelation::where("cust_id", $user->id)->first();
            $agentId = $relation->direct_agent_id;
            $agent = \DB::table("a_agent")->leftJoin("a_agent_extra_info", "a_agent.id", "=", "a_agent_extra_info.id")
                ->where("a_agent.id", $agentId)->first();
            $userInfo["is_show_options"] = $agent->is_option_open_trading;
        } catch (\Exception $e) {
            $userInfo["is_open_options_account"] = 0;
            $userInfo["is_show_options"] = 0;
        }

        //TODO 根据需求新增
        return $userInfo;
    }

    /**
     * 银行卡信息列表
     * @param $user
     * @return mixed
     */
    public function bankCards($user)
    {
        $ret = CustBankCard::where("cust_id", $user->id)
            ->get();
        return $ret ? $ret->toArray() : false;
    }

    /**
     * 获取银行卡详情
     * @param $user
     * @param $id
     * @return mixed
     */
    public function getBankCard($user, $id)
    {
        $ret = CustBankCard::where("cust_id", $user->id)->where("id", $id)->first();
        return $ret ? $ret->toArray() : false;
    }

    /**
     * 创建银行卡
     * @param $user
     * @param $data
     * @return mixed
     */
    public function storeBankCard($user, $data)
    {
        if (CustBankCard::where("cust_id", $user->id)->where("bank_card", $data["bank_card"])->count()) {
            $this->setErrorMsg("用户已绑定该银行卡，无法再绑定");
            return false;
        }
        $data["cust_id"] = $user->id;
        return CustBankCard::create($data);
    }

    /**
     * 更新银行卡信息
     * @param $user
     * @param $id
     * @param $data
     * @return bool
     */
    public function updateBankCard($user, $id, $data)
    {
        $cardRecord = CustBankCard::where("cust_id", $user->id)->find($id);
        if (!$cardRecord || $cardRecord->cust_id != $user->id) {
            return false;
        }

        if (CustBankCard::where("cust_id", $user->id)->where("id", "!=", $id)
            ->where("bank_card", $data["bank_card"])->count()) {
            $this->setErrorMsg("用户已绑定该银行卡，无法再绑定");
            return false;
        }

        return $cardRecord->update($data);
    }

    /**
     * 删除银行卡
     * @param $user
     * @param $id
     * @return bool
     */
    public function deleteBankCard($user, $id)
    {
        $cardRecord = CustBankCard::find($id);
        if (!$cardRecord || $cardRecord->cust_id != $user->id) {
            return false;
        }

        return $cardRecord->delete();
    }

    /**
     * 修改昵称
     * @param $user
     * @param $nickname
     * @return mixed
     */
    public function updateNickname($user, $nickname)
    {
        return $user->update(["nick_name" => $nickname]);
    }

    /**
     * 实名认证
     * @param $user
     * @param $realName
     * @param $idCard
     * @return bool
     */
    public function storeCetification($user, $realName, $idCard)
    {
        if ($user->real_name || $user->id_card) {
            return false;
        }

        return $user->update(["real_name" => $realName, "id_card" => $idCard]);
    }

    /**
     * 设置提款密码
     * @param $user
     * @param $withDrawPassword
     * @return bool
     */
    public function storeWithdrawPassword($user, $withDrawPassword)
    {
        if ($user->withdraw_pw) {
            return false;
        }

        return $user->update(["withdraw_pw" => encryptPassword($withDrawPassword)]);
    }

    /**
     * 更新提款密码
     * @param $user
     * @param $oldWithdrawPassword
     * @param $withDrawPassword
     * @return bool
     */
    public function updateWithdrawPassword($user, $oldWithdrawPassword, $withDrawPassword)
    {
        if ($user->withdraw_pw != encryptPassword($oldWithdrawPassword)) {
            return false;
        }

        return $user->update(["withdraw_pw" => encryptPassword($withDrawPassword)]);
    }

    /**
     * 更新手机
     * @param $user
     * @param $newPhone
     * @return mixed
     */
    public function updatePhone($user, $newPhone)
    {
        return $user->update(["cellphone" => $newPhone]);
    }

    /**
     * 更新密码
     * @param $user
     * @param $password
     * @return mixed
     */
    public function updatePassword($user, $password)
    {
        return $user->update(["password" => encryptPassword($password)]);
    }

    /**
     * 找回提款密码
     * @param $user
     * @param $password
     * @return mixed
     */
    public function getBackWithdrawPassword($user, $password)
    {
        return $user->update(["withdraw_pw" => encryptPassword($password)]);
    }

    /**
     * 头像上传
     * @param $user
     * @param $avatarUrl
     * @return mixed
     */
    public function uploadAvatar($user, $avatarUrl)
    {
        return $user->update(["avatar" => $avatarUrl]);
    }

    public function getNoticeTypeMsg($notice_type)
    {
        $notice_type = explode(',', $notice_type);
        if (in_array(self::NOTICE_CHG_PWD, $notice_type)) {
            return "您的密码已被系统管理员重置！请及时修改您的密码！";
        }
    }

    public function resetNoticeType($user,$reset_notice_type)
    {
        $user_notice_type = $user->notice_type;
        $old_notice_type = explode(',',$user_notice_type);
        $notice_type_key = array_search($reset_notice_type,$old_notice_type);
        if($notice_type_key >= 0){
            unset($old_notice_type[$notice_type_key]);
        }
        $new_notice_type = implode(',',$old_notice_type);
        $user->notice_type = $new_notice_type;
        $user->save();
    }
}