<?php

declare(strict_types=1);

namespace WeChatPay\V3;

use Exception;

/**
 * 服务商基础支付服务类
 * @see https://pay.weixin.qq.com/wiki/doc/apiv3_partner/index.shtml
 */
class PartnerPaymentService extends BaseService
{
    /**
     * 构造函数
     *
     * @param array<string, mixed> $config 配置数组
     * @throws Exception
     */
    public function __construct(array $config)
    {
        if (isset($config['sub_mchid']) && strpos($config['sub_mchid'], ',') !== false) {
            $sub_mchids = explode(',', $config['sub_mchid']);
            $config['sub_mchid'] = $sub_mchids[array_rand($sub_mchids)];
        }
        parent::__construct($config);
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
        $path = '/v3/pay/partner/transactions/native';
        $publicParams = [
            'sp_appid' => $this->appId,
            'sp_mchid' => $this->mchId,
            'sub_appid' => $this->subAppId,
            'sub_mchid' => $this->subMchId,
        ];
        $params = array_merge($publicParams, $params);
        return $this->execute('POST', $path, $params);
    }

    /**
     * JSAPI支付
     *
     * @param array<string, mixed> $params 下单参数
     * @return array<string, string> Jsapi支付json数据
     * @throws Exception
     */
    public function jsapiPay(array $params): array
    {
        $path = '/v3/pay/partner/transactions/jsapi';
        $publicParams = [
            'sp_appid' => $this->appId,
            'sp_mchid' => $this->mchId,
            'sub_appid' => $this->subAppId,
            'sub_mchid' => $this->subMchId,
        ];
        $params = array_merge($publicParams, $params);
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
        $path = '/v3/pay/partner/transactions/h5';
        $publicParams = [
            'sp_appid' => $this->appId,
            'sp_mchid' => $this->mchId,
            'sub_appid' => $this->subAppId,
            'sub_mchid' => $this->subMchId,
        ];
        $params = array_merge($publicParams, $params);
        return $this->execute('POST', $path, $params);
    }

    /**
     * APP支付
     *
     * @param array<string, mixed> $params 下单参数
     * @return array<string, string> {"prepay_id":"预支付交易会话标识"}
     * @throws Exception
     */
    public function appPay(array $params): array
    {
        $path = '/v3/pay/partner/transactions/app';
        $publicParams = [
            'sp_appid' => $this->appId,
            'sp_mchid' => $this->mchId,
            'sub_appid' => $this->subAppId,
            'sub_mchid' => $this->subMchId,
        ];
        $params = array_merge($publicParams, $params);
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
            $path = '/v3/pay/partner/transactions/id/' . $transaction_id;
        } elseif ($out_trade_no !== null && $out_trade_no !== '') {
            $path = '/v3/pay/partner/transactions/out-trade-no/' . $out_trade_no;
        } else {
            throw new Exception('微信支付订单号和商户订单号不能同时为空');
        }
        
        $params = [
            'sp_mchid' => $this->mchId,
            'sub_mchid' => $this->subMchId,
        ];
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
        
        $path = '/v3/pay/partner/transactions/out-trade-no/' . $out_trade_no . '/close';
        $params = [
            'sp_mchid' => $this->mchId,
            'sub_mchid' => $this->subMchId,
        ];
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
        
        $path = '/v3/refund/domestic/refunds';
        $publicParams = [
            'sub_mchid' => $this->subMchId,
        ];
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
        
        $path = '/v3/refund/domestic/refunds/' . $out_refund_no;
        $params = [
            'sub_mchid' => $this->subMchId,
        ];
        return $this->execute('GET', $path, $params);
    }

    /**
     * 申请交易账单
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     * @throws Exception
     */
    public function tradeBill(array $params): array
    {
        if (empty($params)) {
            throw new Exception('账单参数不能为空');
        }
        
        $path = '/v3/bill/tradebill';
        $publicParams = [
            'sub_mchid' => $this->subMchId,
        ];
        $params = array_merge($publicParams, $params);
        return $this->execute('GET', $path, $params);
    }

    /**
     * 申请资金账单
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     * @throws Exception
     */
    public function fundflowBill(array $params): array
    {
        if (empty($params)) {
            throw new Exception('账单参数不能为空');
        }
        
        $path = '/v3/bill/fundflowbill';
        return $this->execute('GET', $path, $params);
    }

    /**
     * 申请单个子商户资金账单
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     * @throws Exception
     */
    public function subMerchantFundflowBill(array $params): array
    {
        if (empty($params)) {
            throw new Exception('账单参数不能为空');
        }
        
        $path = '/v3/bill/sub-merchant-fundflowbill';
        $publicParams = [
            'sub_mchid' => $this->subMchId,
        ];
        $params = array_merge($publicParams, $params);
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
        if (!is_array($data) || (!isset($data['transaction_id']) && !isset($data['combine_out_trade_no']))) {
            throw new Exception('缺少订单号参数');
        }
        
        if (!isset($data['combine_out_trade_no']) && !$this->orderQueryResult($data['transaction_id'])) {
            throw new Exception('订单未完成');
        }
        
        return $data;
    }

    /**
     * 合单Native支付
     *
     * @param array<string, mixed> $params 下单参数
     * @return array<string, mixed> {"code_url":"二维码链接"}
     * @throws Exception
     */
    public function combineNativePay(array $params): array
    {
        if (empty($params) || !isset($params['sub_orders'])) {
            throw new Exception('合单支付参数不能为空');
        }
        
        $path = '/v3/combine-transactions/native';
        $publicParams = [
            'combine_appid' => $this->appId,
            'combine_mchid' => $this->mchId,
        ];
        
        foreach ($params['sub_orders'] as &$order) {
            $order['mchid'] = $this->mchId;
            if (!isset($order['sub_mchid'])) {
                $order['sub_mchid'] = $this->subMchId;
            }
            if (!isset($order['sub_appid'])) {
                $order['sub_appid'] = $this->subAppId;
            }
        }
        
        $params = array_merge($publicParams, $params);
        return $this->execute('POST', $path, $params);
    }

    /**
     * 合单JSAPI支付
     *
     * @param array<string, mixed> $params 下单参数
     * @return array<string, string> Jsapi支付json数据
     * @throws Exception
     */
    public function combineJsapiPay(array $params): array
    {
        if (empty($params) || !isset($params['sub_orders'])) {
            throw new Exception('合单支付参数不能为空');
        }
        
        $path = '/v3/combine-transactions/jsapi';
        $publicParams = [
            'combine_appid' => $this->appId,
            'combine_mchid' => $this->mchId,
        ];
        
        foreach ($params['sub_orders'] as &$order) {
            $order['mchid'] = $this->mchId;
            if (!isset($order['sub_mchid'])) {
                $order['sub_mchid'] = $this->subMchId;
            }
            if (!isset($order['sub_appid'])) {
                $order['sub_appid'] = $this->subAppId;
            }
        }
        
        $params = array_merge($publicParams, $params);
        $result = $this->execute('POST', $path, $params);
        return $this->getJsApiParameters($result['prepay_id']);
    }

    /**
     * 合单H5支付
     *
     * @param array<string, mixed> $params 下单参数
     * @return array<string, mixed> {"h5_url":"支付跳转链接"}
     * @throws Exception
     */
    public function combineH5Pay(array $params): array
    {
        if (empty($params) || !isset($params['sub_orders'])) {
            throw new Exception('合单支付参数不能为空');
        }
        
        $path = '/v3/combine-transactions/h5';
        $publicParams = [
            'combine_appid' => $this->appId,
            'combine_mchid' => $this->mchId,
        ];
        
        foreach ($params['sub_orders'] as &$order) {
            $order['mchid'] = $this->mchId;
            if (!isset($order['sub_mchid'])) {
                $order['sub_mchid'] = $this->subMchId;
            }
            if (!isset($order['sub_appid'])) {
                $order['sub_appid'] = $this->subAppId;
            }
        }
        
        $params = array_merge($publicParams, $params);
        return $this->execute('POST', $path, $params);
    }

    /**
     * 合单APP支付
     *
     * @param array<string, mixed> $params 下单参数
     * @return array<string, string> {"prepay_id":"预支付交易会话标识"}
     * @throws Exception
     */
    public function combineAppPay(array $params): array
    {
        if (empty($params) || !isset($params['sub_orders'])) {
            throw new Exception('合单支付参数不能为空');
        }
        
        $path = '/v3/combine-transactions/app';
        $publicParams = [
            'combine_appid' => $this->appId,
            'combine_mchid' => $this->mchId,
        ];
        
        foreach ($params['sub_orders'] as &$order) {
            $order['mchid'] = $this->mchId;
            if (!isset($order['sub_mchid'])) {
                $order['sub_mchid'] = $this->subMchId;
            }
            if (!isset($order['sub_appid'])) {
                $order['sub_appid'] = $this->subAppId;
            }
        }
        
        $params = array_merge($publicParams, $params);
        return $this->execute('POST', $path, $params);
    }

    /**
     * 合单查询订单
     *
     * @param string $combine_out_trade_no 合单商户订单号
     * @return array<string, mixed>
     * @throws Exception
     */
    public function combineQueryOrder(string $combine_out_trade_no): array
    {
        if ($combine_out_trade_no === '') {
            throw new Exception('合单商户订单号不能为空');
        }
        
        $path = '/v3/combine-transactions/out-trade-no/' . $combine_out_trade_no;
        return $this->execute('GET', $path, []);
    }

    /**
     * 合单关闭订单
     *
     * @param string $combine_out_trade_no 合单商户订单号
     * @param array<string> $out_trade_no_list 子单订单号列表
     * @return array<string, mixed>
     * @throws Exception
     */
    public function combineCloseOrder(string $combine_out_trade_no, array $out_trade_no_list): array
    {
        if ($combine_out_trade_no === '') {
            throw new Exception('合单商户订单号不能为空');
        }
        
        if (empty($out_trade_no_list)) {
            throw new Exception('子单订单号列表不能为空');
        }
        
        $path = '/v3/combine-transactions/out-trade-no/' . $combine_out_trade_no . '/close';
        $sub_orders = [];
        
        foreach ($out_trade_no_list as $out_trade_no) {
            if ($out_trade_no === '') {
                throw new Exception('子单订单号不能为空');
            }
            
            $sub_orders[] = [
                'mchid' => $this->mchId,
                'out_trade_no' => $out_trade_no,
                'sub_appid' => $this->subAppId,
                'sub_mchid' => $this->subMchId,
            ];
        }
        
        $params = [
            'combine_appid' => $this->appId,
            'sub_orders' => $sub_orders
        ];
        return $this->execute('POST', $path, $params);
    }
}