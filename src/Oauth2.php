<?php
declare(strict_types=1);
/**
 * Author: Weida
 * Date: 2023/11/9 21:53
 * Email: sgenmi@gmail.com
 */

namespace Weida\PindoudouCore;

use InvalidArgumentException;
use RuntimeException;
use Throwable;
use Weida\Oauth2Core\Contract\ConfigInterface;
use Weida\Oauth2Core\Contract\UserInterface;
use Weida\Oauth2Core\AbstractApplication;
use Weida\Oauth2Core\User;
use Weida\PindoudouCore\Contract\AccessTokenInterface;

class Oauth2 extends AbstractApplication
{

    private AccessTokenInterface $accessToken;
    public function __construct(array|ConfigInterface $config,AccessTokenInterface $accessToken)
    {
        parent::__construct($config);
        $this->accessToken = $accessToken;
    }

    /**
     * @return string
     * @author Weida
     */
    protected function getAuthUrl(): string
    {
        $params=[
            'client_id'=>$this->getConfig()->get('client_id'),
            'redirect_uri'=>$this->getConfig()->get('redirect'),
            'response_type'=>'code',
            'scope'=>implode(',',$this->scopes),
            'state'=> $this->state,
            'view'=>'web'
        ];
        $appType = $this->getConfig()->get('app_type','fuwu');
        $authUrl='';
        switch ($appType){
            case 'fuwu':
                $authUrl = 'https://fuwu.pinduoduo.com/service-market/auth';
                break;
            case 'mai':
                $authUrl = 'https://mai.pinduoduo.com/h5-login.html';
                $params['view']='h5';
                break;
            case 'jinbao':
                $authUrl = 'https://jinbao.pinduoduo.com/open.html';
                break;
            case 'ktt':
                $authUrl = 'https://oauth.pinduoduo.com/authorize/ktt';
                break;
            case 'wb':
                $authUrl='https://wb.pinduoduo.com/logistics/auth';
                break;
        }
        if($authUrl){
            throw new InvalidArgumentException("app_type not fund");
        }
        return sprintf("%s?%s",$authUrl,http_build_query($params));
    }

    protected function getTokenUrl(string $code): string
    {
        $params=[
            'type'=>'pdd.pop.auth.token.create',
            'data_type'=>'JSON',
            'client_id'=>$this->getConfig()->get('client_id'),
            'code'=>$code,
            'version'=>'V1',
            'timestamp'=>time(),
        ];
        $params['sign'] = Encryptor::Sign($params,$this->getConfig()->get('client_secret'));
        return 'https://gw-api.pinduoduo.com/api/router?'.http_build_query($params);
    }

    protected function getUserInfoUrl(string $accessToken): string
    {
        return '';
    }

    /**
     * @param string $accessToken
     * @return UserInterface
     * @throws Throwable
     * @author Weida
     */
    public function userFromToken(string $accessToken): UserInterface
    {
        throw new \Exception("官方api不支持，只能授权时获取用户消息");
    }

    /**
     * @param string $code
     * @return UserInterface
     * @throws Throwable
     * @author Weida
     */
    public function userFromCode(string $code): UserInterface
    {
        $res = $this->tokenFromCode($code);
        return new User(array_merge([
            'uid'=>$res['owner_id'],
            'nickname'=>$res['owner_name'],
        ],$res));
    }

    /**
     * @param string $code
     * @return array
     * @throws Throwable
     * @author Weida
     */
    public function tokenFromCode(string $code): array
    {
        $url =  $this->getTokenUrl($code);
        $resp = $this->getHttpClient()->request('GET',$url);
        if($resp->getStatusCode()!=200){
            throw new RuntimeException('Request access_token exception');
        }
        $arr = json_decode($resp->getBody()->getContents(),true);
        if (empty($arr['pop_auth_token_create_response']['access_token'])) {
            throw new RuntimeException('Failed to get access_token: ' . json_encode($arr, JSON_UNESCAPED_UNICODE));
        }
        $res = $arr['pop_auth_token_create_response'];

        $callback = $this->getConfig()->get('access_token_callback');
        if($callback && is_callable($callback)){
            try {
                call_user_func($callback,$res);
            }catch (Throwable $e){
            }
        }
        $this->accessToken->saveCache(intval($res['owner_id']),$res['access_token'],intval($res['expires_in']));
        return $arr;
    }
}