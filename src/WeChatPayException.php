<?php

declare(strict_types=1);

namespace WeChatPay;

/**
 * 微信支付响应内容异常
 */
class WeChatPayException extends \Exception
{
    private array $res = [];
    private ?string $errCode = null;

    /**
     * @param array $res
     */
    public function __construct(array $res)
    {
        $this->res = $res;
        
        if (isset($res['err_code'])) {
            $this->errCode = (string) $res['err_code'];
            $message = '[' . $this->errCode . ']' . ($res['err_code_des'] ?? '');
        } elseif (isset($res['return_code'])) {
            $message = '[' . $res['return_code'] . ']' . ($res['return_msg'] ?? '');
        } else {
            $message = '返回数据解析失败';
        }
        
        parent::__construct($message);
    }

    public function getResponse(): array
    {
        return $this->res;
    }

    public function getErrCode(): ?string
    {
        return $this->errCode;
    }
}