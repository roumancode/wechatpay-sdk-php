<?php

declare(strict_types=1);

namespace WeChatPay\V3;

use Exception;

class BaseService
{
    //SDK版本号
    public static string $VERSION = "1.4.8";

    public static string $GATEWAY = "https://api.mch.weixin.qq.com";

    //应用APPID
    protected string $appId;

    //商户号
    protected string $mchId;

    //商户APIv3密钥
    protected string $apiKey;

    //子商户号
    protected ?string $subMchId = null;

    //子商户公众账号ID
    protected ?string $subAppId = null;

    //是否电商收付通
    protected bool $ecommerce = false;

    //「商户API私钥」文件路径
    protected string $merchantPrivateKeyFilePath;

    //「商户API证书」的「证书序列号」
    protected string $merchantCertificateSerial;

    //「微信支付公钥」文件路径
    protected ?string $platformPublicKeyFilePath = null;

    //「微信支付平台证书」文件路径
    protected string $platformCertificateFilePath;

    //微信支付公钥/平台证书序列号
    protected string $platformCertificateSerial;

    //商户API私钥
    protected mixed $merchantPrivateKeyInstance;

    //微信支付公钥/平台证书
    protected mixed $platformPublicKeyInstance;

    //是否国际版商户
    private bool $isGlobal = false;

    private bool $download_cert = false;

    /**
     * @param array $config 微信支付配置信息
     * @throws Exception
     */
    public function __construct(array $config)
    {
        if (empty($config['appid'])) {
            throw new \InvalidArgumentException('应用APPID不能为空');
        }
        if (empty($config['mchid'])) {
            throw new \InvalidArgumentException("商户号不能为空");
        }
        if (empty($config['apikey'])) {
            throw new \InvalidArgumentException("商户APIv3密钥不能为空");
        }
        if (strlen($config['apikey']) !== 32) {
            throw new \InvalidArgumentException("无效的商户APIv3密钥");
        }
        if (empty($config['merchantPrivateKeyFilePath'])) {
            throw new \InvalidArgumentException("商户API私钥路径不能为空");
        }
        if (empty($config['merchantCertificateSerial'])) {
            throw new \InvalidArgumentException("商户API证书序列号不能为空");
        }
        if (!file_exists($config['merchantPrivateKeyFilePath'])) {
            throw new \InvalidArgumentException("商户API私钥文件不存在");
        }

        $this->appId = $config['appid'];
        $this->mchId = $config['mchid'];
        $this->apiKey = $config['apikey'];
        $this->merchantPrivateKeyFilePath = $config['merchantPrivateKeyFilePath'];
        $this->merchantCertificateSerial = $config['merchantCertificateSerial'];
        
        if (isset($config['platformPublicKeyFilePath'])) {
            $this->platformPublicKeyFilePath = $config['platformPublicKeyFilePath'];
        }
        
        $this->platformCertificateFilePath = $config['platformCertificateFilePath'];
        $this->platformCertificateSerial = $config['platformCertificateSerial'];
        
        if (isset($config['sub_mchid'])) {
            $this->subMchId = $config['sub_mchid'];
        }
        if (isset($config['sub_appid'])) {
            $this->subAppId = $config['sub_appid'];
        }
        if (isset($config['ecommerce'])) {
            $this->ecommerce = (bool) $config['ecommerce'];
        }
        if (isset($config['isGlobal'])) {
            $this->isGlobal = (bool) $config['isGlobal'];
        }

        $this->initCertificate();
    }

    /**
     * 初始化证书与私钥
     * @throws Exception
     */
    private function initCertificate(): void
    {
        //读取商户API私钥
        $privateKeyContent = file_get_contents($this->merchantPrivateKeyFilePath);
        if ($privateKeyContent === false) {
            throw new Exception("无法读取商户API私钥文件");
        }
        
        $this->merchantPrivateKeyInstance = openssl_pkey_get_private($privateKeyContent);
        if (!$this->merchantPrivateKeyInstance) {
            throw new Exception("商户API私钥错误");
        }

        //读取微信支付公钥或平台证书
        if (!empty($this->platformPublicKeyFilePath) && file_exists($this->platformPublicKeyFilePath) && !empty($this->platformCertificateSerial)) {
            $this->loadPlatformPublicKey();
        } else {
            $this->loadPlatformCertificate();
        }
    }

    /**
     * 加载微信支付公钥
     * @throws Exception
     */
    private function loadPlatformPublicKey(): void
    {
        $publicKey = file_get_contents($this->platformPublicKeyFilePath);
        if ($publicKey === false) {
            throw new Exception("无法读取微信支付公钥文件");
        }
        
        $this->platformPublicKeyInstance = openssl_pkey_get_public($publicKey);
        if (!$this->platformPublicKeyInstance) {
            throw new Exception("微信支付公钥错误");
        }
    }

    /**
     * 加载微信支付平台证书
     * @throws Exception
     */
    private function loadPlatformCertificate(): void
    {
        if (file_exists($this->platformCertificateFilePath)) {
            $certificate = file_get_contents($this->platformCertificateFilePath);
            if ($certificate === false) {
                throw new Exception("无法读取微信支付平台证书文件");
            }
            
            $this->platformPublicKeyInstance = openssl_pkey_get_public($certificate);
            if ($this->platformPublicKeyInstance) {
                $cert_info = openssl_x509_parse($certificate);
                if ($cert_info && isset($cert_info['serialNumberHex'])) {
                    $this->platformCertificateSerial = $cert_info['serialNumberHex'];
                }
            } else {
                throw new Exception("微信支付平台证书错误");
            }
        } else {
            $this->downloadCertificate();
        }
    }

    /**
     * 下载微信支付平台证书
     * @throws Exception
     */
    private function downloadCertificate(): void
    {
        $result = $this->execute('GET', $this->isGlobal ? '/v3/global/certificates' : '/v3/certificates');
        $effective_time = 0;
        $encert = [];
        
        foreach ($result['data'] as $item) {
            if (strtotime($item['effective_time']) > $effective_time) {
                $effective_time = strtotime($item['effective_time']);
                $encert = $item['encrypt_certificate'];
            }
        }

        if (empty($encert)) {
            throw new Exception('未找到有效的微信支付平台证书');
        }

        $certificate = $this->decryptToString($encert['ciphertext'], $encert['nonce'], $encert['associated_data']);
        if (!$certificate) {
            throw new Exception('微信支付平台证书解密失败');
        }
        
        if (!file_put_contents($this->platformCertificateFilePath, $certificate)) {
            throw new Exception('微信支付平台证书保存失败，可能无文件写入权限');
        }

        //从证书解析公钥与序列号
        $this->platformPublicKeyInstance = openssl_x509_read($certificate);
        if (!$this->platformPublicKeyInstance) {
            throw new Exception("微信支付平台证书错误");
        }
        
        $cert_info = openssl_x509_parse($certificate);
        if ($cert_info && isset($cert_info['serialNumberHex'])) {
            $this->platformCertificateSerial = $cert_info['serialNumberHex'];
        }
        
        $this->download_cert = true;
    }

    /**
     * 请求接口并解析返回数据
     * @param string $method 请求方式 GET POST PUT
     * @param string $path 请求路径
     * @param array $params 请求参数
     * @param bool $cert 是否包含平台公钥序列号
     * @return mixed
     * @throws Exception
     */
    public function execute(string $method, string $path, array $params = [], bool $cert = false)
    {
        $url = self::$GATEWAY . $path;
        $body = '';
        if ($method === 'GET' || $method === 'DELETE') {
            if (count($params) > 0) {
                $url .= '?' . http_build_query($params);
            }
        } elseif (!empty($params)) {
            $body = json_encode($params, JSON_UNESCAPED_UNICODE);
            if ($body === false) {
                throw new Exception('请求参数JSON编码失败');
            }
        }

        $authorization = $this->getAuthorization($method, $url, $body);
        $header = [];
        $header[] = 'Accept: application/json';
        $header[] = 'Authorization: WECHATPAY2-SHA256-RSA2048 ' . $authorization;
        
        if ($cert) {
            $header[] = 'Wechatpay-Serial: ' . $this->platformCertificateSerial;
        }
        
        if ($method === 'POST' || $method === 'PUT') {
            $header[] = 'Content-Type: application/json';
        }

        [$httpCode, $header, $response] = $this->curl($method, $url, $header, $body);
        $result = json_decode($response, true);
        
        if ($httpCode >= 200 && $httpCode <= 299) {
            if ($path !== '/v3/certificates' && $path !== '/v3/global/certificates' && !$this->checkResponseSign($response, $header)) {
                throw new Exception("微信支付返回数据验签失败");
            }
            return $result;
        }
        
        throw new WeChatPayException($result ?? [], $httpCode);
    }

    /**
     * 下载账单/图片
     * @param string $download_url 下载地址
     * @return mixed
     * @throws Exception
     */
    public function download(string $download_url)
    {
        $method = 'GET';
        $authorization = $this->getAuthorization($method, $download_url);
        $header = [];
        $header[] = 'Authorization: WECHATPAY2-SHA256-RSA2048 ' . $authorization;
        
        [$httpCode, $header, $response] = $this->curl($method, $download_url, $header);
        
        if ($httpCode >= 200 && $httpCode <= 299) {
            return $response;
        } else {
            $result = json_decode($response, true);
            throw new WeChatPayException($result ?? [], $httpCode);
        }
    }

    /**
     * 上传文件
     * @param string $path 请求路径
     * @param string $file_path 本地文件路径
     * @param string $file_name 文件名
     * @return mixed
     * @throws Exception
     */
    public function upload(string $path, string $file_path, string $file_name)
    {
        $url = self::$GATEWAY . $path;
        
        if (!file_exists($file_path)) {
            throw new Exception("文件不存在");
        }
        
        $meta = [
            'filename' => $file_name,
            'sha256' => hash_file("sha256", $file_path)
        ];
        $meta_json = json_encode($meta, JSON_UNESCAPED_UNICODE);
        
        if ($meta_json === false) {
            throw new Exception('元数据JSON编码失败');
        }
        
        $params = [
            'file' => new \CURLFile($file_path, '', $file_name),
            'meta' => $meta_json
        ];
        
        $authorization = $this->getAuthorization('POST', $url, $meta_json);
        $header = [];
        $header[] = 'Accept: application/json';
        $header[] = 'Authorization: WECHATPAY2-SHA256-RSA2048 ' . $authorization;
        
        [$httpCode, $header, $response] = $this->curl('POST', $url, $header, $params);
        $result = json_decode($response, true);
        
        if ($httpCode >= 200 && $httpCode <= 299) {
            if (!$this->checkResponseSign($response, $header)) {
                throw new Exception("微信支付返回数据验签失败");
            }
            return $result;
        }
        
        throw new WeChatPayException($result ?? [], $httpCode);
    }

    /**
     * 返回数据验签
     * @param string $body 返回内容
     * @param string $header 返回头部
     * @return bool
     * @throws Exception
     */
    protected function checkResponseSign(string $body, string $header): bool
    {
        if (!$this->platformCertificateSerial) {
            return true;
        }

        $signature = '';
        $nonce = '';
        $timestamp = '';
        $serial = '';
        
        if (preg_match('/Wechatpay-Signature: (.*?)
/', $header, $matches)) {
            $signature = $matches[1];
        }
        if (preg_match('/Wechatpay-Nonce: (.*?)
/', $header, $matches)) {
            $nonce = $matches[1];
        }
        if (preg_match('/Wechatpay-Timestamp: (.*?)
/', $header, $matches)) {
            $timestamp = $matches[1];
        }
        if (preg_match('/Wechatpay-Serial: (.*?)
/', $header, $matches)) {
            $serial = $matches[1];
        }

        if (empty($signature)) {
            return false;
        }
        
        if ($serial !== $this->platformCertificateSerial) {
            if (substr($serial, 0, 11) === 'PUB_KEY_ID_') {
                throw new Exception('微信支付公钥ID不匹配');
            } else {
                if (substr($this->platformCertificateSerial, 0, 11) === 'PUB_KEY_ID_') {
                    $this->loadPlatformCertificate();
                }
                
                if ($serial !== $this->platformCertificateSerial) {
                    if (!$this->download_cert) {
                        $this->downloadCertificate();
                    }
                    
                    if ($serial !== $this->platformCertificateSerial) {
                        throw new Exception('平台证书序列号不匹配');
                    }
                }
            }
        }

        return $this->checkSign($timestamp, $nonce, $body, $signature);
    }

    /**
     * 验证签名
     * @param string $timestamp 应答时间戳
     * @param string $nonce 应答随机串
     * @param string $body 应答报文主体
     * @param string $signature 应答签名
     * @return bool
     */
    protected function checkSign(string $timestamp, string $nonce, string $body, string $signature): bool
    {
        $message = $timestamp . "\n" . $nonce . "\n" . $body . "\n";
        $result = openssl_verify($message, base64_decode($signature), $this->platformPublicKeyInstance, OPENSSL_ALGO_SHA256);
        return $result === 1;
    }

    /**
     * 生成签名
     * @param array $arr - 待签名数组
     * @return string
     */
    protected function makeSign(array $arr): string
    {
        $message = implode("\n", array_merge($arr, ['']));
        openssl_sign($message, $sign, $this->merchantPrivateKeyInstance, OPENSSL_ALGO_SHA256);
        return base64_encode($sign);
    }

    /**
     * 生成authorization
     * @param string $method 请求方式 GET POST PUT
     * @param string $url 请求URL
     * @param string $body 请求内容 GET时留空
     * @return string
     */
    protected function getAuthorization(string $method, string $url, string $body = ''): string
    {
        $url_values = parse_url($url);
        $url_path = $url_values['path'] . (isset($url_values['query']) ? ('?' . $url_values['query']) : '');
        $timestamp = (string) time();
        $nonce = $this->getNonceStr();
        $sign = $this->makeSign([$method, $url_path, $timestamp, $nonce, $body]);
        
        return sprintf(
            'mchid="%s",nonce_str="%s",timestamp="%d",serial_no="%s",signature="%s"',
            $this->mchId,
            $nonce,
            (int) $timestamp,
            $this->merchantCertificateSerial,
            $sign
        );
    }

    /**
     * 异步回调处理
     * @return array 回调解密后的数据
     * @throws Exception
     */
    public function notify(): array
    {
        $inWechatpaySignature = $_SERVER['HTTP_WECHATPAY_SIGNATURE'] ?? '';
        $inWechatpayTimestamp = $_SERVER['HTTP_WECHATPAY_TIMESTAMP'] ?? '';
        $inWechatpaySerial = $_SERVER['HTTP_WECHATPAY_SERIAL'] ?? '';
        $inWechatpayNonce = $_SERVER['HTTP_WECHATPAY_NONCE'] ?? '';
        $inBody = file_get_contents('php://input');

        if (empty($inBody)) {
            throw new Exception('no data');
        }
        
        if ($this->platformCertificateSerial !== $inWechatpaySerial) {
            if (substr($inWechatpaySerial, 0, 11) === 'PUB_KEY_ID_') {
                throw new Exception('微信支付公钥ID不匹配');
            } else {
                if (substr($this->platformCertificateSerial, 0, 11) === 'PUB_KEY_ID_') {
                    $this->loadPlatformCertificate();
                }
                
                if ($inWechatpaySerial !== $this->platformCertificateSerial) {
                    throw new Exception('平台证书序列号不匹配');
                }
            }
        }

        // 使用平台API证书验签
        if (!$this->checkSign($inWechatpayTimestamp, $inWechatpayNonce, $inBody, $inWechatpaySignature)) {
            throw new Exception('签名校验失败');
        }

        // 转换通知的JSON文本消息为PHP Array数组
        $inBodyArray = (array) json_decode($inBody, true);
        
        // 使用PHP7的数据解构语法，从Array中解构并赋值变量
        if (!isset($inBodyArray['resource']['ciphertext'], $inBodyArray['resource']['nonce'], $inBodyArray['resource']['associated_data'])) {
            throw new Exception('回调数据格式不正确');
        }
        
        ['resource' => [
            'ciphertext'      => $ciphertext,
            'nonce'           => $nonce,
            'associated_data' => $associated_data
        ]] = $inBodyArray;
        
        // 加密文本消息解密
        $inBodyResource = $this->decryptToString($ciphertext, $nonce, $associated_data);
        if ($inBodyResource === null) {
            throw new Exception('数据解密失败');
        }
        
        // 把解密后的文本转换为PHP Array数组
        return json_decode($inBodyResource, true);
    }

    /**
     * 回复通知
     * @param bool $isSuccess 是否成功
     * @param string|null $msg 失败原因
     */
    public function replyNotify(bool $isSuccess = true, ?string $msg = ''): void
    {
        $data = [];
        if ($isSuccess) {
            $data['code'] = 'SUCCESS';
        } else {
            http_response_code(499);
            $data['code'] = 'FAIL';
            $data['message'] = $msg;
        }
        
        $json = json_encode($data, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            $json = json_encode(['code' => 'FAIL', 'message' => 'JSON编码失败']);
        }
        
        echo $json;
    }

    /**
     * 产生随机字符串，不长于32位
     * @param int $length
     * @return string 产生的随机字符串
     */
    protected function getNonceStr(int $length = 32): string
    {
        $chars = "abcdefghijklmnopqrstuvwxyz0123456789";
        $str = "";
        for ($i = 0; $i < $length; $i++) {
            $str .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $str;
    }

    /**
     * 敏感信息RSA加密
     * @param string $str
     * @return string|false
     */
    public function rsaEncrypt(string $str)
    {
        $result = openssl_public_encrypt($str, $encrypted, $this->platformPublicKeyInstance, OPENSSL_PKCS1_OAEP_PADDING);
        if ($result) {
            return base64_encode($encrypted);
        }
        return false;
    }

    /**
     * 敏感信息RSA解密
     * @param string $str
     * @return string|false
     */
    public function rsaDecrypt(string $str)
    {
        $result = openssl_private_decrypt(base64_decode($str), $decrypted, $this->merchantPrivateKeyInstance, OPENSSL_PKCS1_OAEP_PADDING);
        if ($result) {
            return $decrypted;
        }
        return false;
    }

    /**
     * 解密AEAD AES 256gcm密文
     * @param string $ciphertext AES GCM cipher text
     * @param string $nonceStr AES GCM nonce
     * @param string $associatedData AES GCM additional authentication data
     *
     * @return string|false Decrypted string on success or FALSE on failure
     * @throws Exception
     */
    protected function decryptToString(string $ciphertext, string $nonceStr, string $associatedData)
    {
        $ciphertext = base64_decode($ciphertext);
        if ($ciphertext === false || strlen($ciphertext) <= 16) {
            return false;
        }

        if (function_exists('sodium_crypto_aead_aes256gcm_is_available') && sodium_crypto_aead_aes256gcm_is_available()) {
            return sodium_crypto_aead_aes256gcm_decrypt($ciphertext, $associatedData, $nonceStr, $this->apiKey);
        }
        
        if (PHP_VERSION_ID >= 70100 && in_array('aes-256-gcm', openssl_get_cipher_methods(), true)) {
            $ctext = substr($ciphertext, 0, -16);
            $authTag = substr($ciphertext, -16);

            return openssl_decrypt($ctext, 'aes-256-gcm', $this->apiKey, OPENSSL_RAW_DATA, $nonceStr, $authTag, $associatedData);
        }

        throw new Exception('AEAD_AES_256_GCM需要PHP 7.1以上或者安装libsodium-php');
    }

    /**
     * 发起curl请求
     * @param string $method 请求方式 GET POST PUT
     * @param string $url 请求URL
     * @param array $header 请求头部
     * @param string|null|array $body POST内容
     * @param int $timeout 超时时间
     * @return array [http状态码,响应头部,响应数据]
     * @throws Exception
     */
    protected function curl(string $method, string $url, array $header, $body = null, int $timeout = 10): array
    {
        $ch = curl_init();
        $curlVersion = curl_version();
        $ua = "wechatpay-php/" . self::$VERSION . " curl/" . $curlVersion['version'] . " (" . PHP_OS . "/" . php_uname('r') . ") PHP/" . PHP_VERSION;

        curl_setopt_array($ch, [
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_URL => $url,
            CURLOPT_HTTPHEADER => $header,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_USERAGENT => $ua,
            CURLOPT_HEADER => true,
            CURLOPT_RETURNTRANSFER => true,
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        } elseif ($method === 'PUT') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }

        $data = curl_exec($ch);
        if ($data === false) {
            $errmsg = curl_error($ch);
            //curl_close($ch);
            throw new Exception($errmsg, 0);
        }
        
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $header = substr($data, 0, $headerSize);
        $body = substr($data, $headerSize);
        //curl_close($ch);
        
        return [$httpCode, $header, $body];
    }
}
