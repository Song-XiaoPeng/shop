<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Controller;
use Closure;

class CaptchaCheck
{
    /**
     * 检查图像验证码
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Closure $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {

        $captchaId = $request->input('captchaId');
        $captchaCode = $request->input('captchaCode');
        if (!$captchaId || !$captchaCode || !Controller::verifyCaptchaCode($captchaId, $captchaCode)) {
            return Controller::jsonReturn([], Controller::CODE_FAIL, '图像验证码错误', $request);
        }

        return $next($request);
    }
}
