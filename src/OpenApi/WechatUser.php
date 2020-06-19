<?php
/**
 * Created by PhpStorm.
 * User: huangkaiwang
 * Date: 2019/2/18
 * Time: 15:58
 */

namespace Wannabing\Wechat\OpenApi;


use Wannabing\Wechat\Wechat;
use Log;

class WechatUser
{
    private $GET_OAUTH_USER_INFO   = 'https://api.weixin.qq.com/sns/userinfo?access_token=%s&openid=%s&lang=zh_CN';
    private $GET_USER_INFO         = 'https://api.weixin.qq.com/cgi-bin/user/info?access_token=%s&openid=%s&lang=zh_CN';
    private $GET_ACCESS_TOKEN_CODE = 'https://api.weixin.qq.com/sns/oauth2/access_token?appid=%s&secret=%s&code=%s&grant_type=authorization_code';
    private $GET_USER_SUB          = 'https://api.weixin.qq.com/cgi-bin/user/get?access_token=%s';
    private $GET_USER_INFO_BATCH   = 'https://api.weixin.qq.com/cgi-bin/user/info/batchget?access_token=%s';
    private $GEN_QR_LIMIT_SCENE    = 'https://api.weixin.qq.com/cgi-bin/qrcode/create?access_token=%s';
    private $SHOW_QRCODE           = 'https://mp.weixin.qq.com/cgi-bin/showqrcode?ticket=%s';
    private $wechat;

    function __construct(Wechat $wechat)
    {
        $this->wechat = $wechat;
    }
    /**
     * 获取网页授权的access_token
     * @author  hkw <hkw925@qq.com>
     */
    public function getUserAccessToken($code)
    {
        $result = curlGet(sprintf($this->GET_ACCESS_TOKEN_CODE, $this->wechat->appid, $this->wechat->appSecret, $code));
        $result = json_decode($result);
        if (!empty($result->access_token)) {
            return ['access_token' => $result->access_token, 'openid' => $result->openid];
        } else {
            Log::error('获取UserAccessToken失败：错误码' . $result->errcode . '，信息：' . $result->errmsg);
            return false;
        }
    }

    /**
     * 获取网页授权的userinfo
     * @author  hkw <hkw925@qq.com>
     */
    public function getUserInfo($code)
    {
        $result = $this->getUserAccessToken($code);
        if ($result) {
            if ($base_info = $this->getUserInfoBase($result['openid'])) {
                return $base_info;
            }
            $result = json_decode(curlGet(sprintf($this->GET_OAUTH_USER_INFO, $result['access_token'], $result['openid'])), true);
            if (!empty(@$result['errcode'])) {
                Log::error('获取UserInfo失败：错误码' . $result->errcode . '，信息：' . $result->errmsg);
                return false;
            } else {
                return $result;
            }
        } else {
            return false;
        }
    }

    /**
     * 通过openid获取关注用户的信息
     * @author  hkw <hkw925@qq.com>
     * @param $openid
     * @return bool|mixed
     */
    public function getUserInfoBase($openid)
    {
        $result = json_decode(curlGet(sprintf($this->GET_USER_INFO, $this->wechat->getAccessToken(), $openid)), true);
        if (@$result['subscribe'] == 1) {
            return $result;
        } else {
            return false;
        }
    }

    /**
     * 获取关注者的openid信息
     * @author  hkw <hkw925@qq.com>
     * @param null $next_openid
     * @return mixed
     */
    public function getSubscribeOpenid($next_openid = null)
    {
        $url = sprintf($this->GET_USER_SUB, $this->wechat->getAccessToken());
        if ($next_openid) {
            $url .= '&next_openid=' . $next_openid;
        }
        $result = json_decode(curlGet($url), true);
        return $result;
    }

    /**
     * 批量获取关注者的信息
     * @author  hkw <hkw925@qq.com>
     * @param array $openid
     */
    public function getUserInfoBatch(array $openid)
    {
        $post_data['user_list'] = [];
        foreach ($openid as $item) {
            $post_data['user_list'][] = [
                'openid' => $item,
                'lang'   => 'zh_CN'
            ];
        }
        $result = json_decode(curlPost(sprintf($this->GET_USER_INFO_BATCH, $this->wechat->getAccessToken()), $post_data, 'json'), true);
        return $result['user_info_list'];
    }

    public function genQrcode(int $scene)
    {
        $path     = storage_path('app/public/agent_qrcode');
        $filename = $scene . '_qrcode.jpg';
        if (!file_exists($path)) {
            mkdir($path);
        }
        if (file_exists($path . DIRECTORY_SEPARATOR . $filename)) {
            return $path . DIRECTORY_SEPARATOR . $filename;
        }
        $post_data = [
            'action_name' => 'QR_LIMIT_SCENE',
            'action_info' => [
                'scene' => [
                    'scene_id' => $scene
                ]
            ]
        ];
        $result    = json_decode(curlPost(sprintf($this->GEN_QR_LIMIT_SCENE, $this->wechat->getAccessToken()), $post_data, 'json'), true);
        //通过ticket换取二维码
        $img = curlGet(sprintf($this->SHOW_QRCODE, urlencode($result['ticket'])));
        file_put_contents($path . DIRECTORY_SEPARATOR . $filename, $img);
        return $path . DIRECTORY_SEPARATOR . $filename;
    }

    /**
     * 生成字符串参数二维码
     * @author wanna <hkw925@qq.com>
     * @param string $scene
     * @return string
     */
    public function genQrcodeStr(string $scene)
    {
        $path     = storage_path('app/public/agent_qrcode');
        $filename = $scene . '_str_qrcode.jpg';
        if (!file_exists($path)) {
            mkdir($path);
        }
        if (file_exists($path . DIRECTORY_SEPARATOR . $filename)) {
            return $path . DIRECTORY_SEPARATOR . $filename;
        }
        $post_data = [
            'action_name' => 'QR_LIMIT_STR_SCENE',
            'action_info' => [
                'scene' => [
                    'scene_str' => $scene
                ]
            ]
        ];
        $result    = json_decode(curlPost(sprintf($this->GEN_QR_LIMIT_SCENE, $this->wechat->getAccessToken()), $post_data, 'json'), true);
        //通过ticket换取二维码
        $img = curlGet(sprintf($this->SHOW_QRCODE, urlencode($result['ticket'])));
        file_put_contents($path . DIRECTORY_SEPARATOR . $filename, $img);
        return $path . DIRECTORY_SEPARATOR . $filename;
    }
}
