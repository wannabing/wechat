<?php
/**
 * Created by PhpStorm.
 * User: huangkaiwang
 * Date: 2019/2/16
 * Time: 16:49
 */

namespace Huangkaiwang\Wechat\OpenApi;

use Huangkaiwang\Wechat\Wechat;
use Illuminate\Support\Facades\Cache;
use Log;

class Card
{


    private $GET_CARD = 'https://api.weixin.qq.com/card/batchget?access_token=%s';
    private $GET_CARD_INFO = 'https://api.weixin.qq.com/card/get?access_token=%s';
    private $SEND_CARD = 'https://api.weixin.qq.com/cgi-bin/message/custom/send?access_token=%s';             //客服消息发放卡券
    public static $CARD_KEY = 'get_card';                                                                     //获取会员卡的key
    public static $SCAN_CODE_KEY = 'scan_code';                                                               //扫一扫的key
    private $UPDATE_CARD = 'https://api.weixin.qq.com/card/membercard/updateuser?access_token=%s';            //更新会员卡信息
    private $GET_USER_CARD = 'https://api.weixin.qq.com/card/user/getcardlist?access_token=%s';               //获取用户领取的卡券
    private $GET_USER_CARD_DETAIL = 'https://api.weixin.qq.com/card/membercard/userinfo/get?access_token=%s'; //获取用户的会员卡详情
    private $API_TICKET = 'https://api.weixin.qq.com/cgi-bin/ticket/getticket?access_token=%s&type=wx_card';  //卡券apiticket获取
    private $wechat;
    function __construct(Wechat $wechat)
    {
        $this->wechat = $wechat;
    }

    public function cardList()
    {
        $data      = [
            'offset'      => 0,
            'count'       => 50,
            'status_list' => ['CARD_STATUS_VERIFY_OK', 'CARD_STATUS_DISPATCH']
        ];
        $card_list = [];
        $result    = json_decode(curlPost(sprintf($this->GET_CARD, $this->wechat->getAccessToken()), json_encode($data)), true);
        if ($result['errcode'] == 0) {
            $card_list = array_merge($card_list, $result['card_id_list']);
            if ($result['total_num'] > $data['count']) {
                //个数超过限制的条数 循环获取 排除offset=0的情况
                for ($i = 1; $i < ceil($result['total_num'] / $data['count']); $i++) {
                    $data['offset'] = $i * $data['count'];
                    $more_result    = json_decode(curlPost(sprintf($this->GET_CARD, $this->wechat->getAccessToken()), json_encode($data)), true);
                    if (!empty($more_result['card_id_list'])) {
                        $card_list = array_merge($card_list, $more_result['card_id_list']);
                    }
                }
            }
            return $card_list;
        }
        exit($result['errmsg']);
    }

    /**
     * 获取卡券信息接口
     * @param $card_id
     * @return mixed
     * @author  hkw <hkw925@qq.com>
     */
    public function cardInfo($card_id)
    {
        $data   = [
            'card_id' => $card_id
        ];
        $result = json_decode(curlPost(sprintf($this->GET_CARD_INFO, $this->wechat->getAccessToken()), json_encode($data)), true);
        if ($result['errcode']) {
            Log::error($result['errmsg'] . ' in ' . __FILE__ . ' at ' . __LINE__);
            return false;
        }
        return $result['card'];
    }

    public function userCardInfo($card_id, $card_code)
    {
        $data   = [
            'card_id' => $card_id,
            'code'    => $card_code,
        ];
        $result = json_decode(curlPost(sprintf($this->GET_USER_CARD_DETAIL, $this->wechat->getAccessToken()), json_encode($data)), true);
        if ($result['errcode']) {
            Log::error($result['errmsg'] . ' in ' . __FILE__ . ' at ' . __LINE__);
            return false;
        }
        return $result;
    }

    /**
     * 获取用户领取的会员卡
     * @param      $openid
     * @param null $card_id
     * @return mixed
     * @author  hkw <hkw925@qq.com>
     */
    public function getUserCard($openid, $card_id = null)
    {
        $data   = [
            'openid'  => $openid,
            'card_id' => $card_id,
        ];
        $result = json_decode(curlPost(sprintf($this->GET_USER_CARD, $this->wechat->getAccessToken()), json_encode($data)), true);
        if ($result['errcode']) {
            Log::error($result['errmsg'] . ' in ' . __FILE__ . ' at ' . __LINE__);
            return [];
        }
        return @$result['card_list'];
    }

    /**
     * 发放会员卡
     * @param $openid
     * @return bool
     * @author  hkw <hkw925@qq.com>
     */
    public function sendVipCard($openid)
    {
        $data   = [
            'touser'  => $openid,
            'msgtype' => 'wxcard',
            'wxcard'  => [
                'card_id' => Card::$CARD_ID
            ]
        ];
        $result = json_decode(curlPost(sprintf($this->SEND_CARD, $this->wechat->getAccessToken()), json_encode($data)), true);
        if ($result['errcode']) {
            Log::error($result['errmsg'] . ' in ' . __FILE__ . ' at ' . __LINE__);
        }
        return true;
    }

    /**
     * @param $code
     * @param $bonus
     * @param $add_bonus
     * @param $record_bonus
     * @return bool
     * @author  hkw <hkw925@qq.com>
     */
    public function updateIntegral($code, $bonus, $add_bonus, $record_bonus)
    {
        $data   = [
            'code'         => $code,
            'card_id'      => Card::$CARD_ID,
            'bonus'        => $bonus,
            'add_bonus'    => $add_bonus,
            'record_bonus' => $record_bonus,
        ];
        $result = json_decode(curlPost(sprintf($this->UPDATE_CARD, $this->wechat->getAccessToken()), json_encode($data, JSON_UNESCAPED_UNICODE)), true);
        if ($result['errcode']) {
            Log::error($result['errmsg'] . ' in ' . __FILE__ . ' at ' . __LINE__);
            return false;
        }
        return true;
    }

    /**
     * 卡券apiticket
     * @author  hkw <hkw925@qq.com>
     */
    public function apiTicket()
    {
        $filename = $this->wechat->appid . '_api_ticket';
        if (Cache::has($filename)) return Cache::get($filename);
        $result = json_decode(curlGet(sprintf($this->API_TICKET, $this->wechat->getAccessToken())), true);
        if ($result['errcode']) {
            Log::error('获取卡券apiTicket失败：错误码' . $result->errcode . '，信息：' . $result->errmsg);
            return false;
        }
        Cache::put($filename, $result['ticket'], 7000);
        return $result['ticket'];
    }


    public function cardExt($openid, $card_id)
    {
        $nonce_str = $this->wechat->createNonceStr();
        $timestamp = time();
        $data      = [
            'api_ticket' => $this->apiTicket(),
            'timestamp'  => $timestamp,
            'nonce_str'  => $nonce_str,
            'card_id'    => $card_id,
            'openid'     => $openid
        ];
        sort($data, SORT_STRING);
        $str = '';
        foreach ($data as $v) {
            $str .= $v;
        }
        $sign = sha1($str);
        return json_encode([
            'openid'    => $openid,
            'timestamp' => $timestamp,
            'nonce_str' => $nonce_str,
            'signature' => $sign
        ]);
    }
}
