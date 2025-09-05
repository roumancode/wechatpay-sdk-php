<?php

declare(strict_types=1);

namespace WeChatPay\V3;

use Exception;

/**
 * 消费者投诉服务类
 * @see https://pay.weixin.qq.com/wiki/doc/apiv3/open/pay/chapter6_2_5.shtml
 */
class ComplainService extends BaseService
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
     * 查询投诉单列表
     *
     * @param string $begin_date 开始日期，格式为yyyy-MM-DD
     * @param string $end_date 结束日期，格式为yyyy-MM-DD
     * @param int $page_no 分页号，从1开始
     * @param int $page_size 分页大小1-50，默认为10
     * @return array<string, mixed> {"limit":10,"offset":0,"total_count":100,"data":[]}
     * @throws Exception
     */
    public function batchQuery(string $begin_date, string $end_date, int $page_no = 1, int $page_size = 10): array
    {
        $path = '/v3/merchant-service/complaints-v2';
        $offset = $page_size * ($page_no - 1);
        $params = [
            'limit' => $page_size,
            'offset' => $offset,
            'begin_date' => $begin_date,
            'end_date' => $end_date
        ];
        
        return $this->execute('GET', $path, $params);
    }

    /**
     * 查询投诉单详情
     *
     * @param string $complaint_id 投诉单号
     * @return array<string, mixed>
     * @throws Exception
     */
    public function query(string $complaint_id): array
    {
        if ($complaint_id === '') {
            throw new Exception('投诉单号不能为空');
        }
        
        $path = '/v3/merchant-service/complaints-v2/' . $complaint_id;
        return $this->execute('GET', $path);
    }

    /**
     * 查询投诉协商历史
     *
     * @param string $complaint_id 投诉单号
     * @return array<string, mixed>
     * @throws Exception
     */
    public function queryHistorys(string $complaint_id): array
    {
        if ($complaint_id === '') {
            throw new Exception('投诉单号不能为空');
        }
        
        $path = '/v3/merchant-service/complaints-v2/' . $complaint_id . '/negotiation-historys';
        return $this->execute('GET', $path);
    }

    /**
     * 创建投诉通知回调地址
     *
     * @param string $url 通知地址
     * @return array<string, mixed>
     * @throws Exception
     */
    public function createNotifications(string $url): array
    {
        if ($url === '') {
            throw new Exception('通知地址不能为空');
        }
        
        $path = '/v3/merchant-service/complaint-notifications';
        $params = [
            'url' => $url
        ];
        
        return $this->execute('POST', $path, $params);
    }

    /**
     * 查询投诉通知回调地址
     *
     * @return array<string, mixed> {"mchid":"商户号","url":"通知地址"}
     * @throws Exception
     */
    public function queryNotifications(): array
    {
        $path = '/v3/merchant-service/complaint-notifications';
        return $this->execute('GET', $path);
    }

    /**
     * 更新投诉通知回调地址
     *
     * @param string $url 通知地址
     * @return array<string, mixed>
     * @throws Exception
     */
    public function updateNotifications(string $url): array
    {
        if ($url === '') {
            throw new Exception('通知地址不能为空');
        }
        
        $path = '/v3/merchant-service/complaint-notifications';
        $params = [
            'url' => $url
        ];
        
        return $this->execute('PUT', $path, $params);
    }

    /**
     * 删除投诉通知回调地址
     *
     * @return void
     * @throws Exception
     */
    public function deleteNotifications(): void
    {
        $path = '/v3/merchant-service/complaint-notifications';
        $this->execute('DELETE', $path);
    }

    /**
     * 回复用户
     *
     * @param string $complaint_id 投诉单号
     * @param string $complainted_mchid 被诉商户号
     * @param string $response_content 回复内容
     * @param array<string, string> $response_images 回复图片列表
     * @return void
     * @throws Exception
     */
    public function response(string $complaint_id, string $complainted_mchid, string $response_content, array $response_images): void
    {
        if ($complaint_id === '') {
            throw new Exception('投诉单号不能为空');
        }
        
        if ($complainted_mchid === '') {
            throw new Exception('被诉商户号不能为空');
        }
        
        if ($response_content === '') {
            throw new Exception('回复内容不能为空');
        }
        
        $path = '/v3/merchant-service/complaints-v2/' . $complaint_id . '/response';
        $params = [
            'complainted_mchid' => $complainted_mchid,
            'response_content' => $response_content,
            'response_images' => $response_images,
        ];
        
        $this->execute('POST', $path, $params);
    }

    /**
     * 反馈处理完成
     *
     * @param string $complaint_id 投诉单号
     * @param string $complainted_mchid 被诉商户号
     * @return void
     * @throws Exception
     */
    public function complete(string $complaint_id, string $complainted_mchid): void
    {
        if ($complaint_id === '') {
            throw new Exception('投诉单号不能为空');
        }
        
        if ($complainted_mchid === '') {
            throw new Exception('被诉商户号不能为空');
        }
        
        $path = '/v3/merchant-service/complaints-v2/' . $complaint_id . '/complete';
        $params = [
            'complainted_mchid' => $complainted_mchid,
        ];
        
        $this->execute('POST', $path, $params);
    }

    /**
     * 更新退款审批结果
     *
     * @param string $complaint_id 投诉单号
     * @param array<string, mixed> $params 请求参数
     * @return void
     * @throws Exception
     */
    public function updateRefundProgress(string $complaint_id, array $params): void
    {
        if ($complaint_id === '') {
            throw new Exception('投诉单号不能为空');
        }
        
        if (empty($params)) {
            throw new Exception('请求参数不能为空');
        }
        
        $path = '/v3/merchant-service/complaints-v2/' . $complaint_id . '/update-refund-progress';
        $this->execute('POST', $path, $params);
    }

    /**
     * 上传反馈图片
     *
     * @param string $file_path 文件路径
     * @param string $file_name 文件名
     * @return string 媒体文件ID
     * @throws Exception
     */
    public function uploadImage(string $file_path, string $file_name): string
    {
        if (!file_exists($file_path)) {
            throw new Exception('文件不存在：' . $file_path);
        }
        
        if (!is_readable($file_path)) {
            throw new Exception('文件不可读：' . $file_path);
        }
        
        $path = '/v3/merchant-service/images/upload';
        $result = $this->upload($path, $file_path, $file_name);
        
        if (!isset($result['media_id']) || !is_string($result['media_id'])) {
            throw new Exception('上传成功但未返回媒体文件ID');
        }
        
        return $result['media_id'];
    }

    /**
     * 下载图片
     *
     * @param string $media_id 媒体文件标识ID
     * @return string 图片内容
     * @throws Exception
     */
    public function getImage(string $media_id): string
    {
        if ($media_id === '') {
            throw new Exception('媒体文件标识ID不能为空');
        }
        
        $url = self::$GATEWAY . '/v3/merchant-service/images/' . urlencode($media_id);
        return $this->download($url);
    }
}