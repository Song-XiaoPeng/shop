<?php

namespace App\Http\Controllers\Common;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CaptchaController extends Controller
{
    /**
     * 生成图片验证码
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function generateCaptcha(Request $request)
    {
        $captchaId = $request->input('captchaId');
        $data = parent::generateCaptchaImage($captchaId);
        return response()->json(["data" => $data]);
    }

    /**
     * 验证验证码
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function verifyCaptcha(Request $request)
    {
        $captchaId = $request->input('captchaId');
        $captchaCode = $request->input('captchaCode');

        $return = ["status" => 0, "msg" => ""];

        if ($captchaId && $captchaCode && parent::verifyCaptchaCode($captchaId, $captchaCode)) {
            $return["status"] = 1;
            $return["msg"] = "验证成功";
        } else {
            $return["msg"] = "验证码错误";
        }

        return response()->json($return);
    }
}