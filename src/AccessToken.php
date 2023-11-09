<?php
declare(strict_types=1);
/**
 * Author: Weida
 * Date: 2023/11/9 22:50
 * Email: sgenmi@gmail.com
 */

namespace Weida\PindoudouCore;

use Psr\SimpleCache\CacheInterface;
use RuntimeException;
use Throwable;
use Weida\Oauth2Core\Contract\HttpClientInterface;
use Weida\PindoudouCore\Contract\AccessTokenInterface;

class AccessToken implements AccessTokenInterface
{
    private int $clientId;
    private string $clientSecret;
    private int $uid;
    private string $refreshToken;
    private string $accessToken='';
    private ?CacheInterface $cache;
    private ?HttpClientInterface $httpClient;
    private $callback;
    private string $cacheKey='';
    public function __construct(
        int $clientId,string $clientSecret,int $uid, string $refreshToken,
        ?CacheInterface $cache=null, ?HttpClientInterface $httpClient=null,?callable $callback=null
    )
    {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->uid = $uid;
        $this->refreshToken = $refreshToken;
        $this->cache = $cache;
        $this->httpClient = $httpClient;
        $this->callback = $callback;
    }

    /**
     * @param bool $isRefresh
     * @return string
     * @throws Throwable
     * @author Weida
     */
    public function getToken(bool $isRefresh = false): string
    {
        if(!empty($this->accessToken)){
            return $this->accessToken;
        }
        if(!$isRefresh){
            $token = $this->cache->get($this->getCacheKey());
            if (!empty($token)) {
                return $token;
            }
        }
        $params=[
            'type'=>'pdd.pop.auth.token.refresh',
            'refresh_token'=>$this->refreshToken,
            'data_type'=>'JSON',
            'client_id'=>$this->getConfig()->get('client_id'),
            'version'=>'V1',
            'timestamp'=>time(),
        ];
        $params['sign'] = Encryptor::Sign($params,$this->clientSecret);

        $url = "https://gw-api.pinduoduo.com/api/router";

        $resp = $this->httpClient->request('POST',$url,[
            'headers'=>[
                'Content-Type'=>'application/json'
            ],
            'body'=>json_encode($params)
        ]);

        if($resp->getStatusCode()!=200){
            throw new RuntimeException('Request access_token exception');
        }
        $arr = json_decode($resp->getBody()->getContents(),true);

        if (empty($arr['pop_auth_token_refresh_response']['access_token'])) {
            throw new RuntimeException('Failed to get access_token: ' . json_encode($arr, JSON_UNESCAPED_UNICODE));
        }
        $res = $arr['pop_auth_token_refresh_response'];
        //走刷新流程，这里刷新和其他一般的oauth2不太一样。存在同时刷新access_token和refresh_token,
        //如果用于保存refresh_token,这里走回调处理
        if($this->callback && is_callable($this->callback)){
            try {
                call_user_func($this->callback,$res);
            }catch (Throwable $e){
            }
        }
        $this->cache->set($this->getCacheKey(), $res['access_token'], intval($res['expires_in'])-10);
        return $arr['data']['access_token'];
    }

    /**
     * @param string $accessToken
     * @return $this
     * @author Weida
     */
    public function setToken(string $accessToken): static
    {
        $this->accessToken = $accessToken;
        return $this;
    }

    /**
     * @return int
     * @author Weida
     */
    public function expiresTime(): int
    {
        return  $this->cache->ttl($this->getCacheKey());
    }

    /**
     * @return array
     * @author Weida
     */
    public function getParams(): array
    {
        return [
            'client_id'=>$this->clientId,
            'secret'=>$this->clientSecret,
            'refresh_token'=>$this->refreshToken,
            'uid'=>$this->uid,
            'cache'=>$this->cache,
            'httpClient'=>$this->httpClient
        ];
    }

    /**
     * @return string
     * @author Weida
     */
    public function getCacheKey(): string
    {
        if(!$this->uid){
            throw new RuntimeException('uid not fund');
        }
        if(empty($this->cacheKey)){
            $this->cacheKey = sprintf("access_token:%s:%s", $this->clientId,$this->uid);
        }
        return $this->cacheKey;
    }

    /**
     * @param string $key
     * @return $this
     * @author Weida
     */
    public function setCacheKey(string $key): static
    {
        $this->cacheKey = $key;
        return $this;
    }

    /**
     * @param int $uid
     * @param string $accessToken
     * @param int $expiresIn
     * @return bool
     * @throws Throwable
     * @author Weida
     */
    public function saveCache(int $uid,string $accessToken,int $expiresIn):bool {
        $this->uid = $uid;
        $this->cache->set($this->getCacheKey(), $accessToken, $expiresIn-10);
        return true;
    }
}