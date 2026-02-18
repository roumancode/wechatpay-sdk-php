<?php

declare(strict_types=1);

namespace WeChatPay;

use Exception;

/**
 * JSAPI支付工具类
 * 实现了从微信公众平台获取code、通过code获取openid和access_token
 */
class JsApiTool
{
    public const GET_AUTH_CODE_URL = "https://open.weixin.qq.com/connect/oauth2/authorize";
    public const GET_ACCESS_TOKEN_URL = "https://api.weixin.qq.com/sns/oauth2/access_token";
    public const GET_MINIAPP_TOKEN_URL = "https://api.weixin.qq.com/sns/jscode2session";

    private string $appid;
    private string $appsecret;

    /**
     * 网页授权接口微信服务器返回的数据，返回样例如下
     * {
     *  "access_token":"ACCESS_TOKEN",
     *  "expires_in":7200,
     *  "refresh_token":"REFRESH_TOKEN",
     *  "openid":"OPENID",
     *  "scope":"SCOPE",
     *  "unionid": "o6_bmasdasdsad6_2sgVt7hMZOPfL"
     * }
     * openid是微信支付jsapi支付接口必须的参数
     * @var array|null
     */
    public ?array $data = null;

    public function __construct(string $appid, string $appsecret)
    {
        $this->appid = $appid;
        $this->appsecret = $appsecret;
    }

    /**
     * 通过跳转获取用户的openid，跳转流程如下：
     * 1、设置自己需要调回的url及其其他参数，跳转到微信服务器
     * 2、微信服务处理完成之后会跳转回用户redirect_uri地址，此时会带上一些参数，如：code
     *
     * @return string 用户的openid
     * @throws Exception
     */
    public function GetOpenid(): string
    {
        if (!isset($_GET['code'])) {
            $this->login();
        }
        $code = $_GET['code'];
        return $this->GetOpenidFromMp($code);
    }

    /**
     * 跳转到微信公众平台登录
     */
    public function login(): never
    {
        if (function_exists('is_https')) {
            $redirect_uri = ($this->is_https() ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        } else {
            $redirect_uri = ($_SERVER['SERVER_PORT'] == 443 ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        }
        $param = [
            "appid" => $this->appid,
            "redirect_uri" => $redirect_uri,
            "response_type" => "code",
            "scope" => "snsapi_base",
            "state" => "STATE"
        ];
        $url = self::GET_AUTH_CODE_URL . '?' . http_build_query($param) . "#wechat_redirect";
        header("Location: $url");
        exit;
    }

    public function is_https() {
        // 检查 $_SERVER['HTTPS'] 是否为 'on' 或 '1'（Apache/Nginx 常见配置）
        if (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') {
            return true;
        }
        // 检查端口是否为 443（HTTPS 默认端口）
        elseif (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) {
            return true;
        }
        // 检查代理转发的 HTTPS 标识（适用于反向代理场景，如 Nginx 代理到 PHP-FPM）
        elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
            return true;
        }
        return false;
    }

    /**
     * 从公众平台获取openid
     * @param string $code 微信跳转回来带上的code
     *
     * @return string openid
     * @throws Exception
     */
    public function GetOpenidFromMp(string $code): string
    {
        $param = [
            "appid" => $this->appid,
            "secret" => $this->appsecret,
            "code" => $code,
            "grant_type" => "authorization_code"
        ];
        $url = self::GET_ACCESS_TOKEN_URL . '?' . http_build_query($param);
        $res = $this->curl($url);
        $data = json_decode($res, true);
        
        if (!is_array($data)) {
            throw new Exception('获取openid失败，返回数据格式错误');
        }
        
        if (isset($data['access_token']) && isset($data['openid'])) {
            $this->data = $data;
            return $data['openid'];
        }
        
        if (isset($data['errcode'])) {
            throw new Exception('Openid获取失败 [' . $data['errcode'] . ']' . ($data['errmsg'] ?? ''));
        }
        
        throw new Exception('Openid获取失败，原因未知');
    }

    /**
     * 微信小程序获取Openid
     * @param string $code 登录时获取的code
     *
     * @return string openid
     * @throws Exception
     */
    public function AppGetOpenid(string $code): string
    {
        $param = [
            "appid" => $this->appid,
            "secret" => $this->appsecret,
            "js_code" => $code,
            "grant_type" => "authorization_code"
        ];
        $url = self::GET_MINIAPP_TOKEN_URL . '?' . http_build_query($param);
        $res = $this->curl($url);
        $data = json_decode($res, true);
        
        if (!is_array($data)) {
            throw new Exception('获取openid失败，返回数据格式错误');
        }
        
        if (isset($data['session_key']) && isset($data['openid'])) {
            $this->data = $data;
            return $data['openid'];
        }
        
        if (isset($data['errcode'])) {
            throw new Exception('获取openid失败 [' . $data['errcode'] . ']' . ($data['errmsg'] ?? ''));
        }
        
        throw new Exception('获取openid失败，原因未知');
    }

    /**
     * 发起GET请求
     * @param string $url 请求url
     * @return string
     */
    private function curl(string $url): string
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_TIMEOUT, 6);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Linux; U; Android 4.0.4; es-mx; HTC_One_X Build/IMM76D) AppleWebKit/534.30 (KHTML, like Gecko) Version/4.0");
        
        $res = curl_exec($ch);
        if ($res === false) {
            $error = curl_error($ch);
            //curl_close($ch);
            throw new Exception('CURL请求失败: ' . $error);
        }
        
        //curl_close($ch);
        return $res;
    }
}
