<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Model\WexingConcern;
use App\User;
use EasyWeChat\Core\Exceptions\HttpException;
use EasyWeChat\Foundation\Application;
use EasyWeChat\Message\Text;
use EasyWeChat\Message\News;
use Illuminate\Support\Facades\DB;

class WechatController extends Controller
{
    private $app = null;
    private $agentId = 0;
    private $agent = null;
    private $config = [
        'app_id' => '',
        'secret' => '',
        'token' => '',
    ];

    /**
     * 获取实例
     * @param $request
     * @return WechatController|bool
     */
    static public function instance($request)
    {
        $agentId = $request->get("agentId");
        $agent = DB::table("a_agent")->leftJoin("a_agent_extra_info", "a_agent.id", "=", "a_agent_extra_info.id")
            ->where("a_agent.id", $agentId)->where("a_agent.is_independent", 1)->first();
        if (!$agent) return false;

        $wechat = new self([
            'app_id' => $agent->appid,
            'secret' => $agent->public_key,
            'token' => "yingli",
            'agent' => $agent
        ]);
        return $wechat;
    }

    public function __construct($config = [])
    {
        $this->config = $config;
        $this->agentId = $config["agent"]->id;
        $this->agent = $config["agent"];
        $options = [
            'debug' => false,
            'app_id' => $config["app_id"],
            'secret' => $config["secret"],
            'token' => $config["token"],
            'log' => [
                'level' => 'debug',
                'file' => '/tmp/easywechat.log',
            ],
            "oauth" => [
                'scopes' => ['snsapi_base'],
                'callback' => '/oauth_callback',
            ],
        ];

        $this->app = new Application($options);
    }

    /**
     * 微信服务端
     */
    public function index()
    {
        $server = $this->app->server;
        $server->setMessageHandler(function ($message) {
            switch ($message->MsgType) {
                case 'event':
                    switch ($message->Event) {
                        case "subscribe":
                            return $this->subscribe($message);
                            break;
                        case "unsubscribe":
                            return $this->unSubscribe($message);
                            break;
                        default:
                            break;
                    }
                    break;
                case 'text':
                    return $this->responseText($message);
                    break;
                case 'image':
                    break;
                case 'voice':
                    break;
                case 'video':
                    break;
                case 'location':
                    break;
                case 'link':
                    break;
                default:
                    break;
            }
        });

        $response = $server->serve();
        $response->send();
    }

    /**
     * 创建二维码
     * @param string $value
     * @return bool|string
     */
    public function makeQrCode($value = "")
    {
        $qrCode = $this->app->qrcode;
        $result = $qrCode->forever($value);
        $ticket = $result->ticket;
        $url = $qrCode->url($ticket);
        $content = file_get_contents($url);
        return $content;
    }

    /**
     * 创建目录
     */
    public function createMenu()
    {
        $get_oauth_url_method = 'getOauthUrl1';
        $menu = $this->app->menu;
        $button = [
            [
                "name" => "配资",
                "sub_button" => [
                    [
                        "type" => "view",
                        "name" => "我要配资",
                        "url" => $this->$get_oauth_url_method("financing"),
                    ],
                    [
                        "type" => "view",
                        "name" => "免息体验",
                        "url" => $this->$get_oauth_url_method("trade/freeTrial"),
                    ],
                ],
            ],
            [
                "name" => "福利",
                "sub_button" => [
                    [
                        "type" => "view",
                        "name" => "专属海报",
                        "url" => $this->$get_oauth_url_method("exclude"),
                    ],
                    [
                        "type" => "view",
                        "name" => "有利可图",
                        "url" => $this->$get_oauth_url_method("myReferral"),
                    ],
                ],
            ],
            [
                "name" => "我的",
                "sub_button" => [
                    [
                        "type" => "view",
                        "name" => "我的主页",
                        "url" => $this->$get_oauth_url_method("memberCenter/member"),
                    ],
                    [
                        "type" => "view",
                        "name" => "我要交易",
                        "url" => $this->$get_oauth_url_method("myTrade"),
                    ],
                    [
                        "type" => "view",
                        "name" => "我的推广",
                        "url" => $this->$get_oauth_url_method("myReferral"),
                    ],
                ],
            ],
        ];
        if ($this->agentId == 1) {
            $tutorialUrl = "https://cd.webportal.cc/v/1rr45Z44/";
            if ($this->agent->platform_name == "股临门") $tutorialUrl = "http://m.eqxiu.com/s/c0keqzVM";
            $button[2]["sub_button"][] = [
                "type" => "view",
                "name" => "教程",
                "url" => $tutorialUrl
            ];

            if (in_array($this->agent->platform_name, ["开天时代", "股临门", "牛股动力"])) {
                $button[2]["sub_button"][] = [
                    "type" => "view",
                    "name" => "APP下载",
                    "url" => env("PC_SITE_URL") . "download?agent_id=" . $this->agentId
                ];
            }
        }
        if (in_array($this->agent->platform_name, ["多股乐", "配多多", '股牛牛在线配资网', '股赢在线'])) {
            $button[2]["sub_button"][] = [
                "type" => "view",
                "name" => "APP下载",
                "url" => env("PC_SITE_URL") . "download?agent_id=" . $this->agentId,
            ];
        }
        if ($this->agentId == 4557) {
            array_unshift($button[0]["sub_button"], [
                "type" => "view",
                "name" => "常见问题",
                "url" => "https://mp.weixin.qq.com/s/KNnjzl8gDyVfp6fME75J6w"
            ]);
            array_unshift($button[0]["sub_button"], [
                "type" => "view",
                "name" => "操作指南",
                "url" => "http://mp.weixin.qq.com/s/L1lN-r_7fHb3Rl6aJYHqYw"
            ]);
            array_unshift($button[1]["sub_button"], [
                "type" => "view",
                "name" => "项目推介",
                "url" => "http://mp.weixin.qq.com/s/FAn6hmMUlWBe0fWKkHiXkg"
            ]);
            array_unshift($button[1]["sub_button"], [
                "type" => "view",
                "name" => "斯特拉顿",
                "url" => "http://mp.weixin.qq.com/s/_S2PDtZsQLBH-n0H-AwmiQ"
            ]);
            array_unshift($button[2]["sub_button"], [
                "type" => "view",
                "name" => "股配新星",
                "url" => "http://mp.weixin.qq.com/s/BWPmVSbTzVB03DeFSH86Cg"
            ]);
        }

        if ($this->agent->platform_name == '盛世资本') {
            array_unshift($button[0]["sub_button"], [
                "type" => "view",
                "name" => "期权",
                "url" => $this->$get_oauth_url_method("newOptionsHome?tab=1"),
            ]);
        }

        try {
            $ret = $menu->add($button);
            return true;
        } catch (HttpException $e) {
            return false;
        }
    }

    /**
     * 获取网页授权的用户信息
     * @return array
     */
    public function getOauthUserInfo()
    {
        return $this->app->oauth->user()->toArray();
    }

    /**
     * 订阅
     * @param $message
     * @return Text
     */
    private function subscribe($message)
    {
        if (isset($message["EventKey"])) {
            list($t, $data["rec_code"]) = explode("_", $message["EventKey"]);
        }
        $openId = $message->FromUserName;
        $data["open_id"] = $openId;
        $data["appid"] = $this->config["app_id"];

        $userService = $this->app->user;
        $userInfo = $userService->get($openId);
        $data["nick_name"] = $userInfo->nickname;
        $data["head_pic"] = $userInfo->headimgurl;

        $record = WexingConcern::where("open_id", $data["open_id"])->first();
        if ($record) {
            $record->update(array_merge($data, [
                "is_concern" => 1,
            ]));
        } else {
            $ret = WexingConcern::create($data);
            if (!$ret) {
                //记录日志
                \Log::info(array_merge($data, ["position" => "微信关注记录错误"]));
            }
        }
        return $this->getWelcomeResponseText();
    }

    /**
     * 取消订阅
     * @param $message
     */
    private function unSubscribe($message)
    {
        $openId = $message->FromUserName;
        $record = WexingConcern::where("open_id", $openId)->first();
        if ($record) {
            $record->update([
                "is_concern" => 0,
                "cancel_time" => date("Y-m-d H:i:s")
            ]);
        }
    }

    /**
     * 回复客户的文字信息
     * @param $message
     * @return array|News|Text
     */
    private function responseText($message)
    {
        $msg = $message->Content;
        if ($this->agentId == 1 && $this->agent->platform_name == "开天时代") {
            switch ($msg) {
                case "1":
                    $mater = new News([
                        "title" => "1、如何注册开天时代帐号及绑定银行卡",
                        "description" => "如何注册开天时代帐号及绑定银行卡",
                        "image" => "http://gubao.oss-cn-shenzhen.aliyuncs.com/file/%E7%99%BB%E5%BD%95.png",
                        "url" => "https://mp.weixin.qq.com/s/QJ-MYKH6COfkiJoniaAwTA"
                    ]);
                    $mater2 = new News([
                        "title" => "2、修改手机号、密码、银行卡流程",
                        "description" => "修改手机号、密码、银行卡流程",
                        "image" => "http://gubao.oss-cn-shenzhen.aliyuncs.com/file/%E4%BF%AE%E6%94%B9.png",
                        "url" => "https://mp.weixin.qq.com/s/VK6A4hXo4uevLN4FTLR70g"
                    ]);
                    return [$mater, $mater2];
                case "2":
                    $mater = new News([
                        "title" => "⑴了解配资流程",
                        "description" => "了解配资流程",
                        "image" => "http://gubao.oss-cn-shenzhen.aliyuncs.com/file/%E9%85%8D%E8%B5%84%E6%B5%81%E7%A8%8B.png",
                        "url" => "https://mp.weixin.qq.com/s/SewPs10nPq_47x4XBgPn-w"
                    ]);
                    $mater2 = new News([
                        "title" => "⑵了解入金（认证支付）、出金流程",
                        "description" => "了解入金（认证支付）、出金流程",
                        "image" => "http://gubao.oss-cn-shenzhen.aliyuncs.com/file/%E5%85%A5%E9%87%91.png",
                        "url" => "https://mp.weixin.qq.com/s/xrQjkrysJDJSGUmCqpU-aA"
                    ]);
                    return [$mater, $mater2];
                case "3":
                    $mater = new News([
                        "title" => "①了解下单买股票流程 、委托买入 、委托卖出、撤单",
                        "description" => "了解下单买股票流程 、委托买入 、委托卖出、撤单",
                        "image" => "http://gubao.oss-cn-shenzhen.aliyuncs.com/file/%E4%B9%B0%E8%82%A1%E6%B5%81%E7%A8%8B.png",
                        "url" => "https://mp.weixin.qq.com/s/sMHnNHZmkDF_aj0JAcToNQ"
                    ]);
                    $mater2 = new News([
                        "title" => "②如何股票委托查询、交易查询、撤单查询",
                        "description" => "如何股票委托查询、交易查询、撤单查询",
                        "image" => "http://gubao.oss-cn-shenzhen.aliyuncs.com/file/%E4%BA%A4%E6%98%93%E6%9F%A5%E8%AF%A2.png",
                        "url" => "https://mp.weixin.qq.com/s/qYrBe-8Vg2dSwrv033Ig3A"
                    ]);
                    return [$mater, $mater2];
                case "4":
                    $mater = new News([
                        "title" => "配资合约账户如何提取盈利/追加配资/追加保证金",
                        "description" => "什么是单合约：就是一张合约，可以补仓，从低吸纳，拉低成本价。",
                        "image" => "http://gubao.oss-cn-shenzhen.aliyuncs.com/file/%E6%8F%90%E5%8F%96%E5%88%A9%E6%B6%A6.png",
                        "url" => "https://mp.weixin.qq.com/s/F1gkqj5Y8DguWmMstLh7RA",
                    ]);
                    return $mater;
                case "5":
                    $mater = new News([
                        "title" => "如何结束合约",
                        "description" => "结束合约：配资—我要配资—我要交易—账户信息—申请结算。",
                        "image" => "http://gubao.oss-cn-shenzhen.aliyuncs.com/file/%E7%BB%93%E6%9D%9F%E5%90%88%E7%BA%A6.png",
                        "url" => "https://mp.weixin.qq.com/s/oEBEim24yJ9P5OfN53VwJg"
                    ]);

                    return $mater;
                default:
                    return $this->getDefaultResponseText();
            }
        }
    }

    private function getWelcomeResponseText()
    {
        $ret = "";
        if ($this->agentId == 1 && $this->agent->platform_name == "开天时代") {
            $ret = $this->getDefaultResponseText();
        } else if ($this->agentId == 4557) {
            $ret = new Text([
                "content" => "同样是炒股，为什么别人能赚钱，你却总是亏钱？如果还没找到原因和解决办法，你来找我们（请留下微信号和手机号）。股配新星，让你的资金利用率变高，让你的投资收益率更高。授人以鱼，不如授人以渔。股配新星成立斯特拉顿研究院助你在股市淘金，让投资变的简单。点击屏幕左下角“配资—我要配资”即刻开启财富之旅，赚钱的路上我们与你同行。股配新星官方推荐码：455700。"
            ]);
        }
        return $ret;
    }

    private function getDefaultResponseText()
    {
        return $this->agentId == 1 ? new Text([
            "content" => "尊敬的客户：
 您可以通过回复以下内容获取相关信息哦~~~
回复“1”：注册和修改信息
回复“2”：配资和入金出金流程
回复“3”：股票下单流程
回复“4”：如何提取盈利
回复“5”：如何结束合约
 开天时代客服1（gubao2011）"
        ]) : "";
    }

    /**
     * 获取网页授权地址
     * @param $url
     * @return string
     */
    private function getOauthUrl($url)
    {
        $url = env("PC_SITE_URL") . "v1/loginFromOpenId?callbackUrl=" . urlencode($url) . "&agentId=" . $this->agentId;
        $response = $this->app->oauth->setRedirectUrl($url)->redirect();
        $url = $response->getTargetUrl();
        \Log::info('网页授权地址', ["url" => $url]);
        return $url;
    }

    private function getOauthUrl2($url)
    {
        $url = env("PC_SITE_URL") . "v1/oAuth?callbackUrl=" . urlencode($url) . "&agentId=" . $this->agentId;
        $response = $this->app->oauth->setRedirectUrl($url)->redirect();
        $url = $response->getTargetUrl();
        \Log::info('网页授权地址', ["url" => $url]);
        return $url;
    }

    private function getOauthUrl1($url)
    {
        $base_url = $this->agent->mobile_domain . DIRECTORY_SEPARATOR;
        $url = 'http://' . $base_url . $url . "?agentId=" . $this->agentId;
        $response = $this->app->oauth->setRedirectUrl($url)->redirect();
        $url = $response->getTargetUrl();
        \Log::info('网页授权地址', ["url" => $url]);
        return $url;
    }

    public function oAuth1($request)
    {
        $agentId = $request->post("agentId");
        $code = $request->post("code");
        $state = $request->post("state");
        if (!$agentId) {
            return "代理信息错误";
        }
        $wechat = WechatController::instance($request);
        if (!$wechat) {
            return "微信信息错误";
        }
        $_GET['code'] = $code;
        $_GET['state'] = $state;
        $userInfo = $wechat->getOauthUserInfo();
        $openId = $userInfo["original"]["openid"];

        $concernInfo = WexingConcern::where("open_id", $openId)->first();
        if (!$concernInfo) {
            return "关注信息错误，请重新关注该公众号";
        }
        $rec_code = 'empty';
        if ($concernInfo->rec_code) {
            $rec_code = $concernInfo->rec_code;
        }

        $user = User::where("openid", "like", "%$openId%")->where("is_login_forbidden", 0)->first();

        $access_token = '';
        if ($user) {
            $ret = apiLogin($user->{CUSTOMER_USERNAME_FIELD}, passwordEncrypt($user->password));
            if ($ret == false) {
                return parent::jsonReturn([], parent::CODE_FAIL, "绑定账号失败，请重试");
            }
            $access_token = $ret["access_token"];
        }
        return parent::jsonReturn([
            'openid' => $openId,
            'access_token' => $access_token,
            'rec_code' => $rec_code,
        ], parent::CODE_SUCCESS, "success");
    }

    public function oAuth($request)
    {
        $agentId = $request->get("agentId");
        if (!$agentId) {
            return "代理信息错误";
        }
        $callback_url = $request->get("callbackUrl");

        $wechat = WechatController::instance($request);
        if (!$wechat) {
            return "微信信息错误";
        }

        $userInfo = $wechat->getOauthUserInfo();
        $openId = $userInfo["original"]["openid"];
        $agentExtraInfo = DB::table("a_agent_extra_info")->where("id", $this->agentId)->first();

        $concernInfo = WexingConcern::where("open_id", $openId)->first();
        if (!$concernInfo) {
            return "关注信息错误，请重新关注该公众号";
        }
        $rec_code = 'empty';
        if ($concernInfo->rec_code) {
            $rec_code = $concernInfo->rec_code;
        }

        $user = User::where("openid", "like", "%$openId%")->where("is_login_forbidden", 0)->first();

        if ($user) {
            $ret = apiLogin($user->{CUSTOMER_USERNAME_FIELD}, passwordEncrypt($user->password));
            $access_token = $ret["access_token"];
            if ($access_token) {
                $target_url = "http://" . $agentExtraInfo->mobile_domain . '/' . $callback_url . "?access_token=" . $access_token . '&callback_url=' . $callback_url;
            } else {
                return "绑定账号失败，请重试";
            }
        } else {
            $target_url = "http://" . $agentExtraInfo->mobile_domain . "/bindingPhone/$openId/" . $rec_code;
        }
        header('Location:' . $target_url);
        exit;
    }
}