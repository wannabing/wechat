<?php
/**
 * Created by PhpStorm.
 * User: huangkaiwang
 * Date: 2018/9/18
 * Time: 17:40
 */

namespace Huangkaiwang\Wechat\OpenApi;


use Huangkaiwang\Wechat\Wechat;
use Illuminate\Support\Facades\Cache;
use Log;

class WechatJs
{
    private $GET_JS_TICKET = 'https://api.weixin.qq.com/cgi-bin/ticket/getticket?type=jsapi&access_token=%s';
    private $wechat;

    function __construct(Wechat $wechat)
    {
        $this->wechat = $wechat;
    }

    /**
     * 获取jsticket(保存redis)
     * @return bool
     * @author wanna <hkw925@qq.com>
     */
    public function getJsApiTicket()
    {
        $filename = 'js_ticket_' . $this->wechat->appid;
        if (Cache::has($filename)) return Cache::get($filename);
        $result = curlGet(sprintf($this->GET_JS_TICKET, $this->wechat->getAccessToken()));
        $result = json_decode($result);
        if ($result->errcode == 0) {
            Cache::put($filename, $result->ticket, 7000);
            return $result->ticket;
        } else {
            Log::error('获取JsApiTicket失败：错误码' . $result->errcode . '，信息：' . $result->errmsg);
            return false;
        }
    }

    /**
     * 获取jssdk的配置
     * @param null $url
     * @return array
     * @author  hkw <hkw925@qq.com>
     */
    public function jsConfig($url = null)
    {
        $jsapiTicket = $this->getJsApiTicket();
        if (!$url) $url = $this->wechat->getCurrentUrl();
        $timestamp = time();
        $nonceStr  = $this->wechat->createNonceStr();
        // 这里参数的顺序要按照 key 值 ASCII 码升序排序
        $string      = $this->wechat->sortAscii([
            'jsapi_ticket' => $jsapiTicket,
            'noncestr'     => $nonceStr,
            'timestamp'    => $timestamp,
            'url'          => $url
        ]);
        $signature   = sha1($string);
        $signPackage = [
            "appId"     => $this->wechat->appid,
            "nonceStr"  => $nonceStr,
            "timestamp" => $timestamp,
            "url"       => $url,
            "signature" => $signature,
            "rawString" => $string
        ];
        return $signPackage;
    }
}
