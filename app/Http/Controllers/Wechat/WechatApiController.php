<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Model\AgentExtraInfo;
use Illuminate\Http\Request;
use App\User;
use App\Http\Model\MemberAgentRelation;
use EasyWeChat\Core\Exceptions\HttpException;
use App\Http\Model\Agent;

class WechatApiController extends Controller
{
    private $wechat = null;

    public function __construct(Request $request)
    {
        $wechat = WechatController::instance($request);
        if (!$wechat) {
            exit("代理商信息错误");
        }

        $this->wechat = $wechat;
    }

    /**
     * 微信对接接口
     */
    public function index()
    {
        return $this->wechat->index();
    }

    /**
     * 创建菜单
     */
    public function createMenu()
    {
        $ret = $this->wechat->createMenu();
        return $ret ? parent::jsonReturn([], parent::CODE_SUCCESS, "success") :
            parent::jsonReturn([], parent::CODE_FAIL, "error");
    }

    /**
     * 切换客户微信二维码
     * @param Request $request
     */
    public function changeCustomerQrcode(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            "isAllClient" => ["required", "integer", "between:0,1"],
            //"agentId" => ["required", "integer",],
        ], [
            "参数错误",
        ]);

        if ($validator->fails()) {
            return parent::jsonReturn([], parent::CODE_FAIL, $validator->errors()->first());
        }

        $isAllClient = $request->get("isAllClient");
        $custId = $request->get("custId");

        if ($isAllClient == 0 && !$custId) {
            return parent::jsonReturn([], parent::CODE_FAIL, "参数错误");
        }

        $users = [];
        $agentId = $request->get("agentId");
        $agent = AgentExtraInfo::find($agentId);
        if ($isAllClient == 0) {
            $users = User::where("id", $custId)->get();
        } else {
            //修改代理商和代理商的下级代理商的客户二维码
            $parentIds = [$agentId];
            $agentIds = [$agentId];
            for ($i = 0; $i < 4; $i++) {
                $agents = Agent::whereIn("parent_id", $parentIds)->select(["id"])->where("is_independent", 0)->get();
                if (!$agents) break;

                $parentIds = [];
                foreach ($agents as $v) {
                    $parentIds[] = $agentIds[] = $v["id"];
                }
            }

            $userRelations = MemberAgentRelation::whereIn("direct_agent_id", $agentIds)->select(["cust_id"])
                ->get();
            $userIds = [];
            foreach ($userRelations as $v) {
                $userIds[] = $v->cust_id;
            }
            $users = User::whereIn("id", $userIds)->get();
        }

        foreach ($users as $user) {
            try {
                $img = $this->wechat->makeQrCode($user->cust_rec_code);
            } catch (\Exception $e) {
                \Log::info('切换客户微信二维码-' . $e->getMessage());
                continue;
            }

            $object = time() . rand(1, 99999) . $user->cust_rec_code . ".jpg";
            $ret = ossUpload($object, $img, "qrCode");
            $data['bar_code'] = $ret ?: "";
            if (!empty($agent)) {
                $data['pc_adv_url'] = "http://" . $agent->web_domain . "/register?code={$user->cust_rec_code}";
            }
            $user->update($data);
        }

        return parent::jsonReturn([], parent::CODE_SUCCESS, "success");
    }
}