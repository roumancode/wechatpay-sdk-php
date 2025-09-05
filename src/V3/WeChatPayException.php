<?php

declare(strict_types=1);

namespace WeChatPay\V3;

/**
 * 微信支付响应内容异常
 */
class WeChatPayException extends \Exception
{
    /**
     * 响应数据
     * @var array<string, mixed>
     */
    private array $res = [];

    /**
     * 错误代码
     * @var string|null
     */
    private ?string $errCode = null;

    /**
     * HTTP状态码
     * @var string
     */
    private string $httpCode;

    /**
     * 构造函数
     * @param array<string, mixed>|mixed $res 响应数据
     * @param string $httpCode HTTP状态码
     */
    public function __construct($res, string $httpCode)
    {
        $this->res = is_array($res) ? $res : [];
        $this->httpCode = $httpCode;

        if (is_array($res)) {
            $this->errCode = isset($res['code']) ? (string)$res['code'] : null;
            $message = '[' . ($res['code'] ?? 'UNKNOWN') . ']' . ($res['message'] ?? '未知错误');
            
            if (isset($res['detail']['issue'])) {
                $message .= '(' . (string)$res['detail']['issue'] . ')';
            }
        } else {
            $message = '返回数据解析失败(http_code=' . $httpCode . ')';
        }

        parent::__construct($message);
    }

    /**
     * 获取响应数据
     * @return array<string, mixed>
     */
    public function getResponse(): array
    {
        return $this->res;
    }

    /**
     * 获取错误代码
     * @return string|null
     */
    public function getErrCode(): ?string
    {
        return $this->errCode;
    }

    /**
     * 获取HTTP状态码
     * @return string
     */
    public function getHttpCode(): string
    {
        return $this->httpCode;
    }
}