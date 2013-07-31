<?php
/**
 * 阿里云 开放存储服务 OSS
 *
 * @category Services
 * @package  Services_Aliyun_OSS
 * @author   sink <sinkcup@163.com>
 * @license  http://opensource.org/licenses/bsd-license.php New BSD License
 * @link     https://github.com/sinkcup/Services_Aliyun_OSS
 */

//阿里云OSS不能使用pear HTTP_Request2，阿里官方SDK使用的是amazon.com aws-sdk-for-php 中的 https://github.com/amazonwebservices/aws-sdk-for-php/blob/master/lib/requestcore/requestcore.class.php
require_once 'HTTP/Request.php';
require_once 'HTTP/Response.php';
require_once dirname(__FILE__) . '/OSS/Exception.php';

class Services_Aliyun_OSS
{
    private $bucket;
    private $conf = array(
        'accessKeyId' => 'foo',
        'accessKeySecret' => 'bar',
        'host' => array(
            'internet' => 'oss.aliyuncs.com', //外网
            'intranet' => 'oss-internal.aliyuncs.com', //内网
        ),
        'hostEnabled' => 'internet',
    );

    public function __construct($bucket=null, array $conf=array())
    {
        $this->bucket = $bucket;
        $this->conf = array_merge($this->conf, $conf);
    }

    /**
     * 获得所有buckets
     *
     * @return array (
          'Owner' => 
          array (
            'ID' => '123456',
            'DisplayName' => '123456',
          ),
          'Buckets' => 
          array (
            'Bucket' => 
            array (
              array (
                'Name' => 'com-example-dev',
                'CreationDate' => '2013-06-28T03:40:32.000Z',
              ),
              array (
                'Name' => 'com-example-img-agc',
                'CreationDate' => '2013-07-01T06:25:30.000Z',
              ),
              array (
                'Name' => 'com-example-dl',
                'CreationDate' => '2013-07-01T07:12:22.000Z',
              ),
            )
        )
      )
     */
    public function getBuckets()
    {
        $headers = array(
            'Content-Md5' => '',
            'Content-Type' => 'application/x-www-form-urlencoded',
            'Date' => gmdate('D, d M Y H:i:s \G\M\T'),
            'Host' => $this->conf['host'][$this->conf['hostEnabled']],
        );
        $uri = 'http://' . $this->conf['host'][$this->conf['hostEnabled']] . '/';
        $http = new HTTP_Request($uri);
        $http->setMethod(HTTP_Request::HTTP_GET);
        $signStr = 'GET' . "\n";
        uksort($headers, 'strnatcasecmp');
        foreach($headers as $k=>$v) {
            $http->addHeader($k, $v);
            if(in_array(strtolower($k), array('content-md5', 'content-type', 'date'))) {
                $signStr .= $v . "\n";
            }
        }
        $signStr .= '/';
        $sign = base64_encode(hash_hmac('sha1', $signStr, $this->conf['accessKeySecret'], true));
        $auth = $this->conf['accessKeyId'] . ':' . $sign;
        $http->addHeader('Authorization' ,'OSS ' . $auth);
        $http->sendRequest();
        $r =  new HTTP_Response ( $http->getResponseHeader() , $http->getResponseBody (), $http->getResponseCode () );
        if($r->status == 200) {
            return json_decode(json_encode(simplexml_load_string($http->getResponseBody())), true);
        }
        throw new Services_Aliyun_OSS_Exception($r->body);
    }

    /**
     * 上传文件到 private或public-read 的bucket，要auth认证
     * @example curl -i -X 'PUT' -T '2.jpg' 'http://com-example-dl.oss.aliyuncs.com/2.jpg' -H 'Content-Type:image/jpeg' -H 'Date: Thu, 18 Jul 2013 08:21:08 GMT' -H 'Authorization: OSS Asdf:qwer'
     * @example ./osscmd put --content-type=application/vnd.android.package-archive ./example-1.6.3.apk oss://com-example-dl/
     * @example ./osscmd put --content-type=application/octet-stream ./example-1.7.7.ipa oss://com-example-dl/
     * @example ./osscmd put --content-type=application/xml ./example.plist oss://com-example-dl/
     * @return array array(
        'internet' => 'http://com-example-dev.oss.aliyuncs.com/2.jpg'
        'intranet' => 'http://com-example-dev.oss-internal.aliyuncs.com/2.jpg'
        }
     */
    public function put($localPath, $remotePath, $headers=array())
    {
        $uri = 'http://' . str_replace('//', '/', $this->bucket . '.' . $this->conf['host'][$this->conf['hostEnabled']] . '/' . $remotePath);
        $http = new HTTP_Request($uri);
        $http->setMethod(HTTP_Request::HTTP_PUT);
        $http->setReadFile($localPath);
        $length = $http->readStreamSize;
        $http->setReadStreamSize($length);

        $headers = array_merge(
            array(
                'Content-Md5' => '',
                'Content-Type' => 'application/octet-stream',
                'Date' => gmdate('D, d M Y H:i:s \G\M\T'),
            ),
            $headers
        );

        $signStr = 'PUT' . "\n";
        uksort($headers, 'strnatcasecmp');
        foreach($headers as $k=>$v) {
            $http->addHeader($k, $v);
            if(in_array(strtolower($k), array('content-md5', 'content-type', 'date'))) {
                $signStr .= $v . "\n";
            }
        }
        $signStr .= str_replace('//', '/', '/' . $this->bucket . '/' . $remotePath);
        $sign = base64_encode(hash_hmac('sha1', $signStr, $this->conf['accessKeySecret'], true));
        $auth = $this->conf['accessKeyId'] . ':' . $sign;
        $http->addHeader('Authorization' ,'OSS ' . $auth);
        $http->sendRequest();
        $r =  new HTTP_Response ( $http->getResponseHeader() , $http->getResponseBody (), $http->getResponseCode () );
        if($r->status == 200) {
            return array(
                'internet' => 'http://' . str_replace('//', '/', $this->bucket . '.' . $this->conf['host']['internet'] . '/' . $remotePath),
                'intranet' => 'http://' . str_replace('//', '/', $this->bucket . '.' . $this->conf['host']['intranet'] . '/' . $remotePath),
            );
        }
        throw new Services_Aliyun_OSS_Exception($r->status);
    }


    /**
     * 上传文件到 pubic-read-write 的bucket，无需认证
     * @example curl -i -X 'PUT' -T '2.jpg' 'http://example.oss.aliyuncs.com/2.jpg' -H 'Content-Type:image/jpeg'
     * @return string uri
     */
    public function putPublicWrite($localPath, $remotePath, $headers=array())
    {
        $uri = 'http://' . str_replace('//', '/', $this->bucket . '.' . $this->conf['host'][$this->conf['hostEnabled']] . '/' . $remotePath);
        $http = new HTTP_Request($uri);
        $http->setMethod(HTTP_Request::HTTP_PUT);
        $http->setReadFile($localPath);
        $length = $http->readStreamSize;
        $http->setReadStreamSize($length);

        $headers = array_merge(
            array(
                //'Content-Length' => $length, //不用传，http库会自动计算加上
                'Content-Type' => 'application/octet-stream',
            ),
            $headers
        );
        
        foreach($headers as $k=>$v) {
            $http->addHeader($k, $v);
        }

        $http->sendRequest();
        $r =  new HTTP_Response ( $http->getResponseHeader() , $http->getResponseBody (), $http->getResponseCode () );
        if($r->status == 200) {
            return array(
                'internet' => 'http://' . str_replace('//', '/', $this->bucket . '.' . $this->conf['host']['internet'] . '/' . $remotePath),
                'intranet' => 'http://' . str_replace('//', '/', $this->bucket . '.' . $this->conf['host']['intranet'] . '/' . $remotePath),
            );
        }
        throw new Services_Aliyun_OSS_Exception($r->status);
    }

    public function setBucket($bucket)
    {
        $this->bucket = $bucket;
    }
}
?>
