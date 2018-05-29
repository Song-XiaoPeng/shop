<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Model\WexingConcern;
use App\Repositories\WechatRepository;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class LoginController extends Controller
{
    private $register = null;

    /**
     * 登录
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            "username" => ["required", "regex:/^1[0-9]{10}$/"],
            "password" => "required"
        ], [
            "username.required" => "手机号不能为空",
            "password.required" => "密码不能为空",
            "username.regex" => "手机号格式不合法",
        ]);

        if($request->get("loginType") == 2){
            $password = $request->get("password");
        }else{
            $password = opensslDecode($request->get("password"));
        }

        if ($validator->fails()) {
            return parent::jsonReturn([], parent::CODE_FAIL, $validator->errors()->first());
        }

        $username = $request->get("username");

        $user = User::where(CUSTOMER_USERNAME_FIELD, $username)->first();
        if (!$user) {
            return parent::jsonReturn([], parent::CODE_FAIL, "用户账号或密码错误");
        }

        if ($user->is_login_forbidden) {
            return parent::jsonReturn([], parent::CODE_FAIL, "账号已被禁用，请联系客服人员！");
        }

        $ret = apiLogin($username, $password);
        if(!$ret && $request->get("loginType")) \Log::info('扮演问题',[$username,$password]);
        return $ret ? parent::jsonReturn($ret, parent::CODE_SUCCESS, "登录成功") :
            parent::jsonReturn([], parent::CODE_FAIL, "用户账号 或 密码错误");
    }

    /**
     * 刷新登录token
     * @param Request $request
     */
    public function refreshToken(Request $request)
    {
        $request->request->add([
            'grant_type' => "refresh_token",
            'client_id' => "2",
            'client_secret' => "cPAZO6gdD6wUt60nCr2p7mQLyfJo6CXTMhBiAThl",
            'refresh_token' => $request->get("refresh_token"),
            'scope' => ''
        ]);
        $proxy = Request::create(
            'oauth/token',
            'POST'
        );

        $ret = json_decode(\Route::dispatch($proxy)->getContent(), true);
        if ($ret && isset($ret['access_token'])) {
            return self::jsonReturn($ret, parent::CODE_SUCCESS);
        }
        return self::jsonReturn([], parent::CODE_FAIL);
    }

    /**
     * 登出
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout(Request $request)
    {
        //注销token
        $jwtInfo = parsePassportAuthorization($request);
        if ($jwtInfo) {
            $jti = $jwtInfo["jti"];
            DB::table("oauth_access_tokens")->where("id", $jti)->delete();
            DB::table("oauth_refresh_tokens")->where("access_token_id", $jti)->delete();
        }

        return parent::jsonReturn([], parent::CODE_SUCCESS, "success");
    }

    /**
     * 公众号授权登录页面，获取用户openid并进行相应操作
     * @param Request $request
     * @param Response $response
     * @return $this
     */
    public function loginFromOpenId(Request $request, Response $response)
    {
        $url = $request->get("callbackUrl");

        $agentId = $request->get("agentId");
        if (!$agentId) {
            return "代理信息错误";
        }

        $wechat = WechatController::instance($request);
        if (!$wechat) {
            return "微信信息错误";
        }

        $userInfo = $wechat->getOauthUserInfo();
        $openId = $userInfo["original"]["openid"];

        $user = User::where("openid", "like", "%$openId%")->where("is_login_forbidden", 0)->first();
        $agentExtraInfo = DB::table("a_agent_extra_info")->where("id", $agentId)->first();
        if (!$agentExtraInfo || !$agentExtraInfo->mobile_domain) {
            return "代理网站配置错误";
        }

        $concernInfo = WexingConcern::where("open_id", $openId)->first();
        if (!$concernInfo) {
            return "关注信息错误，请重新关注该公众号";
        }

        if ($user) {
            $ret = apiLogin($user->{CUSTOMER_USERNAME_FIELD}, passwordEncrypt($user->password));
            //TODO 假设回调url上没有其他参数
            $url = "http://{$agentExtraInfo->mobile_domain}/wechatCheckBind?access_token=" . $ret["access_token"] . "&callbackUrl=" . $url;
        } else {
            $url = "http://{$agentExtraInfo->mobile_domain}/wechatCheckBind?openid=" . $openId;
            if ($concernInfo->rec_code) {
                $url .= "&recCode={$concernInfo->rec_code}";
            }
        }
        //\Log::info($url);
        return $response->header("Location", $url);
    }


}