<?php
namespace Overtrue\Wechat\Services;

use Overtrue\Wechat\Exception;
use Overtrue\Wechat\Utils\Bag;
use Overtrue\Wechat\Wechat;

/**
 * 网页授权
 */
class Auth
{
    const API_URL       = 'https://open.weixin.qq.com/connect/oauth2/authorize';
    const API_TOKEN_GET = 'https://api.weixin.qq.com/sns/oauth2/access_token';
    const API_USER      = 'https://api.weixin.qq.com/sns/userinfo';

    /**
     * 授权结果
     *
     * {
     *     "access_token":"ACCESS_TOKEN",
     *     "expires_in":7200,
     *     "refresh_token":"REFRESH_TOKEN",
     *     "openid":"OPENID",
     *     "scope":"SCOPE"
     *  }
     *
     * @var array|boolean
     */
    protected $authResult;

    /**
     * 已授权用户
     *
     * @var \Overtrue\Wechat\Utils\Bag
     */
    protected $authorizedUser;


    /**
     * 判断是否已经授权
     *
     * @return boolean
     */
    public function authorized()
    {
        if ($this->authResult) {
            return true;
        }

        if (!($code = Wechat::input('code', null))) {
            return false;
        }

        return (bool) $this->authorize($code);
    }

    /**
     * 生成outh URL
     *
     * @param string  $to
     * @param string  $state
     * @param string  $scope
     *
     * @return string
     */
    public function url($to, $scope = 'snsapi_base', $state = 'STATE')
    {
        $params = array(
                   'appid'         => Wechat::option('appId'),
                   'redirect_uri'  => $to,
                   'response_type' => 'code',
                   'scope'         => $scope,
                   'state'         => $state,
                  );

        return self::API_URL . '?' . http_build_query($params) . '#wechat_redirect';
    }

    /**
     * 直接跳转
     *
     * @param string  $to
     * @param string  $scope
     * @param string  $state
     *
     * @return void
     */
    public function redirect($to, $scope = 'snsapi_base', $state = 'STATE')
    {
        header('Location:' . $this->url($to, $scope, $state));exit;
    }

    /**
     * 获取已授权用户
     *
     * @return \Overtrue\Wechat\Utils\Bag
     */
    public function user()
    {
        if ($this->authorizedUser) {
            return $this->authorizedUser;
        }

        if (!$this->authorized()) {
            throw new Exception("未授权");
        }

        if ($this->authResult['scope'] != 'snsapi_userinfo') {
            throw new Exception("OAuth授权类型为snsapi_userinfo时才能使用此接口获取用户信息");
        }

        $queries = array(
                   'access_token' => $this->authResult['access_token'],
                   'openid'       => $this->authResult['openid'],
                   'lang'         => 'zh_CN',
                  );

        $url = self::API_USER . '?' . http_build_query($queries);

        return new Bag(Wechat::request('GET', $url));
    }

    /**
     * 获取access_token
     *
     * 注意：这个是OAuth2用的access_token，与普通access_token不一样
     *
     * @return string
     */
    public function getAccessToken()
    {
        $key = 'overtrue.wechat.oauth2.access_token';
        $cache = Wechat::service('cache');

        return $cache->get($key, function($key) use ($cache) {

            $cache->set($key, $this->authResult['access_token'], $this->authResult['expires_in']);

            return $this->authResult['access_token'];
        });
    }

    /**
     * 通过code授权
     *
     * @param string $code
     *
     * @return array
     */
    protected function authorize($code)
    {
        if ($this->authResult) {
            return $this->authResult;
        }

        // 关闭自动加access_token参数
        Wechat::autoRequestToken(false);

        $params = array(
                   'appid'      => Wechat::option('appId'),
                   'secret'     => Wechat::option('secret'),
                   'code'       => $code,
                   'grant_type' => 'authorization_code',
                  );

        $authResult = Wechat::request('GET', self::API_TOKEN_GET, $params);

         // 开启自动加access_token参数
        Wechat::autoRequestToken(true);

        //TODO:refresh_token机制
        return $this->authResult = $authResult;
    }
}
