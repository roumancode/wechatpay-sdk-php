<?php

declare(strict_types=1);

/**
 * 其他微信支付V3接口调用示例
 * 使用\WeChatPay\V3\BaseService中的execute方法调用自定义接口
 */

require __DIR__ . '/../../vendor/autoload.php';
header('Content-Type: text/html; charset=UTF-8');

//引入配置文件
$wechatpay_config = require('config.php');

/**
 * 创建支付分订单API示例
 */
//构造请求参数
$params = [
    'appid' => $wechatpay_config['appid'],
    'out_order_no' => date("YmdHis") . random_int(111, 999), //商户订单号
    'service_id' => '', //服务ID
    'service_introduction' => '', //服务信息
];

//发起请求
try {
    $client = new \WeChatPay\V3\BaseService($wechatpay_config);
    $result = $client->execute('POST', '/v3/payscore/serviceorder', $params);
    echo '<pre>' . htmlspecialchars(print_r($result, true)) . '</pre>';
} catch (Exception $e) {
    echo '创建支付分订单失败：' . htmlspecialchars($e->getMessage());
}

echo '<hr>';

/**
 * 商户上传反馈图片API示例
 */
$file_path = __DIR__ . '/pic.png'; //文件路径
$file_name = 'pic.png'; //文件名称

if (!file_exists($file_path)) {
    echo '示例图片文件不存在：' . htmlspecialchars($file_path);
} else {
    try {
        $client = new \WeChatPay\V3\BaseService($wechatpay_config);
        $result = $client->upload('/v3/merchant-service/images/upload', $file_path, $file_name);
        
        if (isset($result['media_id'])) {
            echo '上传成功！媒体ID：' . htmlspecialchars($result['media_id']);
        } else {
            echo '上传成功但未返回媒体ID：' . htmlspecialchars(print_r($result, true));
        }
    } catch (Exception $e) {
        echo '上传失败：' . htmlspecialchars($e->getMessage());
    }
}

