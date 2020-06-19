<?php


namespace Wannabing\Wechat;


use Huangkaiwang\Wechat\OpenApi\Card;
use Huangkaiwang\Wechat\OpenApi\WechatJs;
use Huangkaiwang\Wechat\OpenApi\WechatUser;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;

class Wechat
{
    public $appid;
    public $appSecret;

    public $mch_id;
    public $key;

    public $sslCertPath;
    public $sslKeyPath;

    public $card = null;
    public $js = null;
    public $media = null;
    public $menu = null;
    public $message = null;
    public $oauth = null;
    public $pay = null;
    public $user = null;

    private $GET_ACCESS_TOKEN = 'https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=%s&secret=%s';

    public function __construct()
    {
        $this->appid     = config('wechat.app_id');
        $this->appSecret = config('wechat.secret');
        $this->mch_id    = config('wechat.payment.merchant_id');
        $this->key       = config('wechat.payment.key');
        $this->card      = new Card($this);
        $this->js        = new WechatJs($this);
        $this->user      = new WechatUser();
    }


    /**
     * 生成随机字符串
     * @param int $length
     * @return string
     * @author  hkw <hkw925@qq.com>
     */
    public function createNonceStr($length = 16)
    {
        $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
        $str   = "";
        for ($i = 0; $i < $length; $i++) {
            $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
        }
        return $str;
    }

    public function getCurrentUrl()
    {
        $theme = strpos(URL::current(), 'https://') !== false ? 'https' : 'http';
        return $theme . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
    }

    /**
     * 输出xml字符
     * @param array $data
     * @return string
     */
    public function toXml(array $data)
    {
        $xml = "<xml>";
        foreach ($data as $key => $val) {
            if (is_numeric($val)) {
                $xml .= "<" . $key . ">" . $val . "</" . $key . ">";
            } else {
                $xml .= "<" . $key . "><![CDATA[" . $val . "]]></" . $key . ">";
            }
        }
        $xml .= "</xml>";
        return $xml;
    }

    /**
     * 获取客户端ip
     * @return string
     * @author  hkw <hkw925@qq.com>
     */
    public function get_client_ip()
    {
        if (isset($_SERVER['HTTP_CLIENT_IP']) && !empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        }
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return strtok($_SERVER['HTTP_X_FORWARDED_FOR'], ',');
        }
        if (isset($_SERVER['HTTP_PROXY_USER']) && !empty($_SERVER['HTTP_PROXY_USER'])) {
            return $_SERVER['HTTP_PROXY_USER'];
        }
        if (isset($_SERVER['REMOTE_ADDR']) && !empty($_SERVER['REMOTE_ADDR'])) {
            return $_SERVER['REMOTE_ADDR'];
        } else {
            return "0.0.0.0";
        }
    }

    /**
     * 将xml转为array
     */
    public function fromXml($xml)
    {
        //将XML转为array
        //禁止引用外部xml实体
        libxml_disable_entity_loader(true);
        $data = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        return $data;
    }

    /**
     * ASCII码排序
     * @param array $data
     * @return string
     * @author  hkw <hkw925@qq.com>
     */
    public function sortAscii(array $data)
    {
        //$data = array_filter($data);
        ksort($data);
        $buff = "";
        foreach ($data as $k => $v) {
            if ($k != "sign" && $v != "" && !is_array($v)) {
                $buff .= $k . "=" . $v . "&";
            }
        }
        $buff = trim($buff, "&");
        return $buff;
    }

    /**
     * 获取access token (redis)
     * @return bool
     * @author wanna <hkw925@qq.com>
     */
    public function getAccessToken()
    {
        $filename = 'access_token_' . $this->appid;
        if (Cache::has($filename)) return Cache::get($filename);
        $result = curlGet(sprintf($this->GET_ACCESS_TOKEN, $this->appid, $this->appSecret));
        $result = json_decode($result);
        if (!empty($result->access_token)) {
            Cache::put($filename, $result->access_token, 7000);
            return $result->access_token;
        } else {
            Log::error('获取AccessToken失败：错误码' . $result->errcode . '，信息：' . $result->errmsg);
            return false;
        }
    }


    /**
     * 以post方式提交xml到对应的接口url
     *
     * @param string $xml     需要post的xml数据
     * @param string $url     url
     * @param bool   $useCert 是否需要证书，默认不需要
     * @param int    $second  url执行超时时间，默认30s
     * @return mixed
     */
    public function postXmlCurl($url, $xml, $useCert = false, $second = 30)
    {
        $ch          = curl_init();
        $curlVersion = curl_version();
        $ua          = "WXPaySDK/3.0.9 (" . PHP_OS . ") PHP/" . PHP_VERSION . " CURL/" . $curlVersion['version'] . " " . $this->mch_id;

        //设置超时
        curl_setopt($ch, CURLOPT_TIMEOUT, $second);

        /*$proxyHost = "0.0.0.0";
        $proxyPort = 0;
        $config->GetProxy($proxyHost, $proxyPort);
        //如果有配置代理这里就设置代理
        if($proxyHost != "0.0.0.0" && $proxyPort != 0){
            curl_setopt($ch,CURLOPT_PROXY, $proxyHost);
            curl_setopt($ch,CURLOPT_PROXYPORT, $proxyPort);
        }*/
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, TRUE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);//严格校验
        curl_setopt($ch, CURLOPT_USERAGENT, $ua);
        //设置header
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        //要求结果为字符串且输出到屏幕上
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

        if ($useCert == true) {
            //设置证书
            //使用证书：cert 与 key 分别属于两个.pem文件
            //证书文件请放入服务器的非web目录下
            curl_setopt($ch, CURLOPT_SSLCERTTYPE, 'PEM');
            curl_setopt($ch, CURLOPT_SSLCERT, $this->sslCertPath);
            curl_setopt($ch, CURLOPT_SSLKEYTYPE, 'PEM');
            curl_setopt($ch, CURLOPT_SSLKEY, $this->sslKeyPath);
        }
        //post提交方式
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
        //运行curl
        $data = curl_exec($ch);
        //返回结果
        if ($data) {
            curl_close($ch);
            return $data;
        } else {
            $error = curl_errno($ch);
            curl_close($ch);
            Log::error("curl出错，错误码:$error");
        }
    }

    public function valid()
    {
        if (isset($_GET['echostr'])) {
            $echoStr = $_GET["echostr"];
            if ($this->checkSignature()) {
                echo $echoStr;
                exit;
            }
        }
    }

    private function checkSignature()
    {
        $signature = $_GET["signature"];
        $timestamp = $_GET["timestamp"];
        $nonce     = $_GET["nonce"];
        $token     = config('wechat.token');
        $tmpArr    = [$token, $timestamp, $nonce];
        sort($tmpArr);
        $tmpStr = implode($tmpArr);
        $tmpStr = sha1($tmpStr);
        if ($tmpStr == $signature) {
            return true;
        } else {
            return false;
        }
    }
}
