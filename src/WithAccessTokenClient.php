<?php
declare(strict_types=1);
/**
 * Author: Weida
 * Date: 2023/11/9 23:12
 * Email: sgenmi@gmail.com
 */

namespace Weida\PindoudouCore;

use GuzzleHttp\Client;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Throwable;
use Weida\Oauth2Core\Contract\HttpClientInterface;
use Weida\PindoudouCore\Contract\AccessTokenInterface;
use Weida\PindoudouCore\Contract\ApiInterface;
use Weida\PindoudouCore\Contract\WithAccessTokenClientInterface;

class WithAccessTokenClient implements WithAccessTokenClientInterface
{
    private AccessTokenInterface $accessToken;
    private Client $client;
    public function __construct(HttpClientInterface $httpClient,AccessTokenInterface $accessToken)
    {
        $this->client = $httpClient->getClient();
        $this->accessToken = $accessToken;
    }

    /**
     * @param string $method
     * @param string|ApiInterface $uri
     * @param array $options
     * @return ResponseInterface
     * @throws Throwable
     * @author Weida
     */
    public function request(string $method, string|ApiInterface $uri, array $options = []): ResponseInterface
    {
        $method = strtoupper($method);
        return match ($method) {
            'GET' => $this->get($uri, $options),
            'POST' => $this->post($uri, $options),
            default => throw new InvalidArgumentException(sprintf("%s not supported", $method)),
        };
    }

    /**
     * @param string|ApiInterface $uri
     * @param array $options
     * @return ResponseInterface
     * @throws Throwable
     * @author Weida
     */
    public function get(string|ApiInterface $uri, array $options = []): ResponseInterface
    {
        if($uri instanceof ApiInterface){
            $options['query'] = $this->_gerParams($uri->getParams());
            $uri = $uri->getUrl();
        }else{
            if(!isset($options['query']) && $options){
                $options['query']= $this->_gerParams($options);
            }
        }
        return $this->client->get($uri,$options);
    }

    /**
     * @param string|ApiInterface $uri
     * @param array $options
     * @return ResponseInterface
     * @throws Throwable
     * @author Weida
     */
    public function post(string|ApiInterface $uri, array $options = []): ResponseInterface
    {
        if($uri instanceof ApiInterface){
            $options['body'] = json_encode($this->_gerParams($uri->getParams()));
            $uri = $uri->getUrl();
        }else{
            if(isset($options['body'])){
                $options['body'] = json_encode($this->_gerParams($options['body']));
            }
        }
        $options['headers']['Content-Type'] = 'application/json';
        return $this->client->post($uri,$options);
    }

    /**
     * @param string|ApiInterface $uri
     * @param array $postData
     * @return ResponseInterface
     * @throws Throwable
     * @author Weida
     */
    public function postJson(string|ApiInterface $uri, array $postData = []): ResponseInterface
    {
        if($uri instanceof ApiInterface){
            if(!empty($postData)){
                $options['body'] = json_encode($this->_gerParams($postData));
            }else{
                $options['body'] = json_encode($this->_gerParams($uri->getParams()));
            }
            $uri = $uri->getUrl();
        }else{
            $options['body'] = json_encode($postData);
        }
        $options['headers']['Content-Type'] = 'application/json';
        return $this->client->post($uri,$options);
    }

    /**
     * @param array|string $params
     * @return array
     * @author Weida
     */
    private function _gerParams(array|string $params): array
    {
        if(is_string($params)){
            $params = json_decode($params,true);
        }
        if(empty($params['type'])){
            throw new InvalidArgumentException("type not fund");
        }
        $_accessParams = $this->accessToken->getParams();
        $comParams["client_id"] = $_accessParams['client_id']??'';
        $comParams['data_type'] = 'JSON';
        $comParams["timestamp"] = time();
        $comParams['version']='V1';
        $comParams['access_token'] = $this->accessToken->getToken();
        $allPrams = array_merge($comParams, $params);
        //签名
        $allPrams["sign"] = Encryptor::sign($allPrams,$_accessParams['secret']);
        return $allPrams;
    }



}