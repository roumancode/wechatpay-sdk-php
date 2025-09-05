<?php

declare(strict_types=1);

namespace WeChatPay\V3;

use Exception;

/**
 * 全球版支付服务类
 * @see https://pay.weixin.qq.com/wiki/doc/api_external/index_ch.shtml
 */
class GlobalPaymentService extends BaseService
{
    /**
     * 构造函数
     *
     * @param array<string, mixed> $config 配置数组
     */
    public function __construct(array $config)
    {
        parent::__construct($config);
    }

    /**
     * 付款码支付
     *
     * @param array<string, mixed> $params 下单参数
     * @return array<string, mixed>
     * @throws Exception
     */
    public function microPay(array $params): array
    {
        $path = '/v3/global/micropay/transactions/pay';
        if (!empty($this->subMchId)) {
            $publicParams = [
                'sp_appid' => $this->appId,
                'sp_mchid' => $this->mchId,
                'sub_mchid' => $this->subMchId,
            ];
            if (!empty($this->subAppId)) {
                $publicParams['sub_appid'] = $this->subAppId;
            }
        } else {
            $publicParams = [
                'appid' => $this->appId,
                'mchid' => $this->mchId,
            ];
        }
        $params = array_merge($publicParams, $params);
        $params['trade_type'] = 'MICROPAY';
        return $this->execute('POST', $path, $params);
    }

    /**
     * NATIVE支付
     *
     * @param array<string, mixed> $params 下单参数
     * @return array<string, mixed> {"code_url":"二维码链接"}
     * @throws Exception
     */
    public function nativePay(array $params): array
    {
        $path = '/v3/global/transactions/native';
        if (!empty($this->subMchId)) {
            $publicParams = [
                'sp_appid' => $this->appId,
                'sp_mchid' => $this->mchId,
                'sub_mchid' => $this->subMchId,
            ];
            if (!empty($this->subAppId)) {
                $publicParams['sub_appid'] = $this->subAppId;
            }
        } else {
            $publicParams = [
                'appid' => $this->appId,
                'mchid' => $this->mchId,
            ];
        }
        $params = array_merge($publicParams, $params);
        $params['trade_type'] = 'NATIVE';
        return $this->execute('POST', $path, $params);
    }

    /**
     * JSAPI支付
     *
     * @param array<string, mixed> $params 下单参数
     * @return array<string, mixed> Jsapi支付json数据
     * @throws Exception
     */
    public function jsapiPay(array $params): array
    {
        $path = '/v3/global/transactions/jsapi';
        if (!empty($this->subMchId)) {
            $publicParams = [
                'sp_appid' => $this->appId,
                'sp_mchid' => $this->mchId,
                'sub_mchid' => $this->subMchId,
            ];
            if (!empty($this->subAppId)) {
                $publicParams['sub_appid'] = $this->subAppId;
            }
        } else {
            $publicParams = [
                'appid' => $this->appId,
                'mchid' => $this->mchId,
            ];
        }
        $params = array_merge($publicParams, $params);
        $params['trade_type'] = 'JSAPI';
        $result = $this->execute('POST', $path, $params);
        return $this->getJsApiParameters($result['prepay_id']);
    }

    /**
     * 获取JSAPI支付的参数
     *
     * @param string $prepay_id 预支付交易会话标识
     * @return array<string, string> json数据
     * @throws Exception
     */
    private function getJsApiParameters(string $prepay_id): array
    {
        if ($prepay_id === '') {
            throw new Exception('预支付交易会话标识不能为空');
        }
        
        $params = [
            'appId' => $this->appId,
            'timeStamp' => (string)time(),
            'nonceStr' => $this->getNonceStr(),
            'package' => 'prepay_id=' . $prepay_id,
        ];
        $params['paySign'] = $this->makeSign([$params['appId'], $params['timeStamp'], $params['nonceStr'], $params['package']]);
        $params['signType'] = 'RSA';
        return $params;
    }

    /**
     * H5支付
     *
     * @param array<string, mixed> $params 下单参数
     * @return array<string, mixed> {"h5_url":"支付跳转链接"}
     * @throws Exception
     */
    public function h5Pay(array $params): array
    {
        $path = '/v3/global/transactions/mweb';
        if (!empty($this->subMchId)) {
            $publicParams = [
                'sp_appid' => $this->appId,
                'sp_mchid' => $this->mchId,
                'sub_mchid' => $this->subMchId,
            ];
            if (!empty($this->subAppId)) {
                $publicParams['sub_appid'] = $this->subAppId;
            }
        } else {
            $publicParams = [
                'appid' => $this->appId,
                'mchid' => $this->mchId,
            ];
        }
        $params = array_merge($publicParams, $params);
        $params['trade_type'] = 'MWEB';
        return $this->execute('POST', $path, $params);
    }

    /**
     * APP支付
     *
     * @param array<string, mixed> $params 下单参数
     * @return array<string, string> APP支付json数据
     * @throws Exception
     */
    public function appPay(array $params): array
    {
        $path = '/v3/global/transactions/app';
        if (!empty($this->subMchId)) {
            $publicParams = [
                'sp_appid' => $this->appId,
                'sp_mchid' => $this->mchId,
                'sub_mchid' => $this->subMchId,
            ];
            if (!empty($this->subAppId)) {
                $publicParams['sub_appid'] = $this->subAppId;
            }
        } else {
            $publicParams = [
                'appid' => $this->appId,
                'mchid' => $this->mchId,
            ];
        }
        $params = array_merge($publicParams, $params);
        $params['trade_type'] = 'APP';
        $result = $this->execute('POST', $path, $params);
        return $this->getAppParameters($result['prepay_id']);
    }

    /**
     * 获取APP支付的参数
     *
     * @param string $prepay_id 预支付交易会话标识
     * @return array<string, string>
     * @throws Exception
     */
    private function getAppParameters(string $prepay_id): array
    {
        if ($prepay_id === '') {
            throw new Exception('预支付交易会话标识不能为空');
        }
        
        $params = [
            'appid' => $this->appId,
            'partnerid' => $this->mchId,
            'prepayid' => $prepay_id,
            'package' => 'Sign=WXPay',
            'noncestr' => $this->getNonceStr(),
            'timestamp' => (string)time(),
        ];
        $params['sign'] = $this->makeSign([$params['appid'], $params['timestamp'], $params['noncestr'], $params['prepayid']]);
        return $params;
    }

    /**
     * 查询订单，微信订单号、商户订单号至少填一个
     *
     * @param string|null $transaction_id 微信订单号
     * @param string|null $out_trade_no 商户订单号
     * @return array<string, mixed>
     * @throws Exception
     */
    public function orderQuery(?string $transaction_id = null, ?string $out_trade_no = null): array
    {
        if ($transaction_id !== null && $transaction_id !== '') {
            $path = '/v3/global/transactions/id/' . $transaction_id;
        } elseif ($out_trade_no !== null && $out_trade_no !== '') {
            $path = '/v3/global/transactions/out-trade-no/' . $out_trade_no;
        } else {
            throw new Exception('微信支付订单号和商户订单号不能同时为空');
        }
        
        if (!empty($this->subMchId)) {
            $params = [
                'sp_mchid' => $this->mchId,
                'sub_mchid' => $this->subMchId,
            ];
        } else {
            $params = [
                'mchid' => $this->mchId,
            ];
        }
        return $this->execute('GET', $path, $params);
    }

    /**
     * 判断订单是否已完成
     *
     * @param string $transaction_id 微信订单号
     * @return bool
     */
    public function orderQueryResult(string $transaction_id): bool
    {
        if ($transaction_id === '') {
            return false;
        }
        
        try {
            $data = $this->orderQuery($transaction_id);
            return isset($data['trade_state']) && ($data['trade_state'] === 'SUCCESS' || $data['trade_state'] === 'REFUND');
        } catch (Exception) {
            return false;
        }
    }

    /**
     * 关闭订单
     *
     * @param string $out_trade_no 商户订单号
     * @return array<string, mixed>
     * @throws Exception
     */
    public function closeOrder(string $out_trade_no): array
    {
        if ($out_trade_no === '') {
            throw new Exception('商户订单号不能为空');
        }
        
        $path = '/v3/global/transactions/out-trade-no/' . $out_trade_no . '/close';
        if (!empty($this->subMchId)) {
            $params = [
                'sp_mchid' => $this->mchId,
                'sub_mchid' => $this->subMchId,
            ];
        } else {
            $params = [
                'mchid' => $this->mchId,
            ];
        }
        return $this->execute('POST', $path, $params);
    }

    /**
     * 撤销订单
     *
     * @param string $out_trade_no 商户订单号
     * @return array<string, mixed>
     * @throws Exception
     */
    public function reverseOrder(string $out_trade_no): array
    {
        if ($out_trade_no === '') {
            throw new Exception('商户订单号不能为空');
        }
        
        $path = '/v3/global/micropay/transactions/out-trade-no/' . $out_trade_no . '/reverse';
        if (!empty($this->subMchId)) {
            $params = [
                'sp_mchid' => $this->mchId,
                'sub_mchid' => $this->subMchId,
            ];
        } else {
            $params = [
                'mchid' => $this->mchId,
            ];
        }
        return $this->execute('POST', $path, $params);
    }

    /**
     * 申请退款
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     * @throws Exception
     */
    public function refund(array $params): array
    {
        if (empty($params)) {
            throw new Exception('退款参数不能为空');
        }
        
        $path = '/v3/global/refunds';
        if (!empty($this->subMchId)) {
            $publicParams = [
                'sp_appid' => $this->appId,
                'sp_mchid' => $this->mchId,
                'sub_mchid' => $this->subMchId,
            ];
            if (!empty($this->subAppId)) {
                $publicParams['sub_appid'] = $this->subAppId;
            }
        } else {
            $publicParams = [
                'appid' => $this->appId,
                'mchid' => $this->mchId,
            ];
        }
        $params = array_merge($publicParams, $params);
        return $this->execute('POST', $path, $params);
    }

    /**
     * 查询退款
     *
     * @param string $out_refund_no
     * @return array<string, mixed>
     * @throws Exception
     */
    public function refundQuery(string $out_refund_no): array
    {
        if ($out_refund_no === '') {
            throw new Exception('商户退款单号不能为空');
        }
        
        $path = '/v3/global/refunds/out-refund-no/' . $out_refund_no;
        if (!empty($this->subMchId)) {
            $params = [
                'sp_mchid' => $this->mchId,
                'sub_mchid' => $this->subMchId,
            ];
        } else {
            $params = [
                'mchid' => $this->mchId,
            ];
        }
        return $this->execute('GET', $path, $params);
    }

    /**
     * 下载对账单
     *
     * @param string $date
     * @return array<string, mixed>
     * @throws Exception
     */
    public function tradeBill(string $date): array
    {
        if ($date === '') {
            throw new Exception('对账单日期不能为空');
        }
        
        $path = '/v3/global/statements';
        if (!empty($this->subMchId)) {
            $params = [
                'sp_mchid' => $this->mchId,
                'sub_mchid' => $this->subMchId,
            ];
        } else {
            $params = [
                'mchid' => $this->mchId,
            ];
        }
        $params['date'] = $date;
        return $this->execute('GET', $path, $params);
    }

    /**
     * 支付通知处理
     *
     * @return array<string, mixed> 支付成功通知参数
     * @throws Exception
     */
    public function notify(): array
    {
        $data = parent::notify();
        if (!is_array($data) || !isset($data['id'])) {
            throw new Exception('缺少订单号参数');
        }
        
        if (!$this->orderQueryResult($data['id'])) {
            throw new Exception('订单未完成');
        }
        
        return $data;
    }
}