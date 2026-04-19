<?php
/**
 * WPOSS API - 阿里云 OSS 接口封装
 * 保持与旧版一致的 options 传递方式，仅修复 OSSClient 拼写及 PHP 兼容性
 */
namespace WPOSS;

if (is_file(__DIR__ . '/sdk/aliyun-oss-php-sdk/autoload.php')) {
    require_once __DIR__ . '/sdk/aliyun-oss-php-sdk/autoload.php';
}

use OSS\OssClient;
use OSS\Core\OssException;


class Api {

    private $options = array();
    private $client;
    private $errors = array();

    public function __construct($options = array()) {
        $this->options = $options;
        if (!is_array($this->options)) {
            $this->options = array();
        }

        try {
            $accessKeyId = isset($this->options['accessKeyId']) ? $this->options['accessKeyId'] : '';
            $accessKeySecret = isset($this->options['accessKeySecret']) ? $this->options['accessKeySecret'] : '';
            $endpoint = isset($this->options['endpoint']) ? $this->options['endpoint'] : '';
            $bucket = isset($this->options['bucket']) ? $this->options['bucket'] : '';
            $cname = isset($this->options['cname']) ? $this->options['cname'] : false;

            if (empty($accessKeyId) || empty($accessKeySecret) || empty($endpoint) || empty($bucket)) {
                $this->errors[] = '配置不完整，请检查 Bucket/Endpoint/AccessKey 是否已填写';
                return;
            }

            $this->client = new OssClient($accessKeyId, $accessKeySecret, $endpoint, $cname);

            try {
                if (!$this->client->doesBucketExist($bucket)) {
                    $this->client = null;
                    $this->errors[] = "Bucket 不存在或无法访问: {$bucket}";
                }
            } catch (OssException $e) {
                $msg = trim($e->getMessage());
                // 空消息或仅 " :  RequestId: " 多为网络/连接失败，保留 client 让首次上传尝试
                if ($msg === '' || preg_match('/^[\s:]*RequestId:\s*$/i', $msg)) {
                    if (defined('WP_DEBUG') && WP_DEBUG && function_exists('error_log')) {
                        $httpStatus = method_exists($e, 'getHTTPStatus') ? $e->getHTTPStatus() : '';
                        $code = method_exists($e, 'getErrorCode') ? $e->getErrorCode() : '';
                        error_log('[WPOSS] Bucket 检测跳过(疑似网络/连接异常): HTTP=' . $httpStatus . ' Code=' . $code . ' endpoint=' . $endpoint);
                    }
                    // 保留 client，首次上传时再判断
                } else {
                    $this->client = null;
                    $this->errors[] = $msg ?: 'Bucket 检测失败，请检查网络与 Endpoint';
                }
            }
        } catch (OssException $e) {
            $this->client = null;
            $msg = trim($e->getMessage());
            $this->errors[] = $msg !== '' ? $msg : 'OSS 连接异常，请检查 AccessKey 和 Endpoint';
        } catch (\Throwable $e) {
            $this->client = null;
            $this->errors[] = '初始化失败: ' . $e->getMessage();
        }
    }

    public function is_client() {
        return $this->client instanceof OssClient;
    }

    static public function does_bucket_exist($accessKeyId, $accessKeySecret, $endpoint, $bucket) {
        try {
            $client = new OssClient($accessKeyId, $accessKeySecret, $endpoint, false);
            if ($client->doesBucketExist($bucket)) {
                return array("status" => 1, "msg" => "Bucket 存在!");
            } else {
                return array("status" => 0, "msg" => "Bucket 不存在!");
            }
        } catch (OssException $e) {
            return array("status" => -1, "msg" => $e->getMessage());
        }
    }

    public function Upload($object, $filePath) {
        if ($this->client === null) {
            $err = implode('; ', $this->errors);
            if ($err === '' && defined('WP_DEBUG') && WP_DEBUG && function_exists('error_log')) {
                $err = 'options keys: ' . implode(',', array_keys($this->options));
            }
            if (defined('WP_DEBUG') && WP_DEBUG && function_exists('error_log')) {
                error_log('[WPOSS] 客户端未初始化: ' . $err);
            }
            throw new \RuntimeException($err ?: 'OSS 客户端未初始化');
        }
        try {
            $path = is_string($filePath) && $filePath !== '' ? $filePath : '';
            if ($path === '' || !file_exists($path)) {
                throw new \InvalidArgumentException('文件不存在: ' . $filePath);
            }
            $path = realpath($path) ?: $path;
            $this->client->uploadFile($this->options['bucket'], $object, $path);
        } catch (OssException $e) {
            $msg = trim($e->getMessage());
            $detail = $msg;
            if ($msg === '' || preg_match('/^[\s:]*RequestId:\s*$/i', $msg)) {
                $parts = array();
                if (method_exists($e, 'getHTTPStatus')) {
                    $parts[] = 'HTTP=' . $e->getHTTPStatus();
                }
                if (method_exists($e, 'getErrorCode')) {
                    $parts[] = 'Code=' . $e->getErrorCode();
                }
                $detail = 'OSS 请求失败(网络/连接异常) ' . implode(' ', $parts) . ' endpoint=' . ($this->options['endpoint'] ?? '');
            }
            $this->errors[] = $detail;
            throw $e;
        }
    }

    public function Delete($objects) {
        if ($this->client === null || empty($objects)) {
            return;
        }
        try {
            $this->client->deleteObjects($this->options['bucket'], $objects);
        } catch (OssException $e) {
            $this->errors[] = $e->getMessage();
        }
    }

    public function hasExist($object) {
        if ($this->client === null) {
            return false;
        }
        try {
            return $this->client->doesObjectExist($this->options['bucket'], $object);
        } catch (OssException $e) {
            return false;
        }
    }
}
