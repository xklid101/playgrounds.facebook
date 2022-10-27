<?php

namespace App\Services\Facebook;

use LogicException;
use RuntimeException;
use GuzzleHttp\Client as GuzzleHttpClient;
use GuzzleHttp\Middleware as GuzzleHttpMiddleware;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;
use App\Services\Log\Logger;

class Api
{
    private $httpClient;

    private $log;

    private $isInited = false;

    private $baseEndpointCustom = null;

    /**
     * wait seconds between api requests looping
     *     (paging, async jobs reports requests, ...)
     *
     * sleep 1 is too small
     * sleep 3 was giving Job Failed fromt time to time (sometimes more often)
     * sleep 5 is too much (?)
     */
    const SLEEP_API_REQUEST_LOOP = 4;

    private $config = [
        'app_id' => '',
        'app_secret' => '',
        'adaccount_id' => '',
        'access_token' => '',
        'marketingapi_version' => '',
        'api_cert_verify' => true,
        'api_cert_path' => '',
        'api_url' => 'https://graph.facebook.com',
        'api_port' => null,
        'base_endpoint' => null,
    ];

    public function __construct(GuzzleHttpClient $httpClient, Logger $log)
    {
        $this->httpClient = $httpClient;
        $this->log = $log;
    }

    public function getLongLivedAccessToken()
    {
        /**
         * @see https://developers.facebook.com/docs/facebook-login/guides/access-tokens/get-long-lived/
         */
        // curl -i -X GET "https://graph.facebook.com/{graph-api-version}/oauth/access_token?
        //     grant_type=fb_exchange_token&
        //     client_id={app-id}&
        //     client_secret={app-secret}&
        //     fb_exchange_token={your-access-token}"

        $this->setBaseEndpoint('oauth');

        $params = [
            'grant_type' => 'fb_exchange_token',
            'client_id' => $this->getAppId(),
            'client_secret' => $this->getAppSecret(),
            'fb_exchange_token' => $this->getAccessToken(),
            'appsecret_proof' => $this->getAppSecretProof()
        ];

        $resp = $this->sendRequest(
            'get',
            'access_token',
            $params
        );

        $this->restoreBaseEndpoint();

        return $this->getParsedBody($resp);
    }

    public function init(array $config)
    {
        if ($this->isInited()) {
            // // just silently continue
            // //  due to backwards compatibility code usage, it is not possible to throw an exception
            // return $this;
            throw new LogicException(__METHOD__ . ' call is allowed only once for the instance!');
        }

        foreach ($config as $key => $value) {
            if (isset($this->config[$key])) {
                $this->config[$key] = $value;
            }
        }

        $this->setIsInited();

        return $this;
    }

    /**
     * [getDataAllAsync description]
     * @param  string $endpointName         eg 'insights', ...
     *                                      (going to be changed to call $this->postInsights and then passed to $this->getDataAll, ...)
     * @param  array  $params       [description]
     * @return array               [description]
     */
    public function getDataAllAsync(
        $endpointName,
        array $params
    ) {
        $methodName = 'post' . ucfirst($endpointName);
        $limit = isset($params['limit']) ? $params['limit'] : 303;
        unset($params['limit']);

        $respAsync = $this->$methodName(
            [],
            $params
        );

        if (isEdgeDebugOn()) {
            $this->log->debug(
                'ASYNC RESPONSE',
                $respAsync
            );
        }

        if (!isset($respAsync['report_run_id']) || !$respAsync['report_run_id']) {
            return [];
        }

        $this->setBaseEndpoint($respAsync['report_run_id']);
        $respReport = $this->get();
        if (isEdgeDebugOn()) {
            $this->log->debug(
                'ASYNC REPORT RESPONSE',
                $respReport
            );
        }

        $listAll = [];
        $i = 0;
        while (
            isset($respReport['async_status'])
            && isset($respReport['async_percent_completion'])
            && !stripos($respReport['async_status'], 'complete')
            && intval($respReport['async_percent_completion']) < 100
        ) {
            $i++;
            if (
                !stripos($respReport['async_status'], 'started')
                && !stripos($respReport['async_status'], 'running')
                && !stripos($respReport['async_status'], 'complete')
            ) {
                $this->log->error(
                    'error getting facebook async report data'
                        . ' - report status does not contain "started", "running", nor "complete" - while loop break',
                    [
                        'loop-time-seconds' => $i * self::SLEEP_API_REQUEST_LOOP,
                        'respReport-last' => $respReport
                    ]
                );
                break;
            }

            sleep(self::SLEEP_API_REQUEST_LOOP);
            $respReport = $this->get();
            if (isEdgeDebugOn()) {
                $this->log->debug(
                    'ASYNC REPORT RESPONSE',
                    $respReport
                );
            }
            // prevent some api bugs and incompatible changes
            if ($i >= 600) {
                $this->log->error(
                    'error getting facebook async report data - unfinished async business - while loop break',
                    [
                        'loop-time-seconds' => $i * self::SLEEP_API_REQUEST_LOOP,
                        'respReport-last' => $respReport
                    ]
                );
                break;
            }
        }

        if (isset($respReport['id'])) {
            $this->setBaseEndpoint($respReport['id']);
            $listAll = $this->getDataAll(
                $endpointName,
                [
                    'limit' => $limit
                ]
            );
        }
        $this->restoreBaseEndpoint();

        return $listAll;
    }

    /**
     * get all response data based on $methodName (endpoint @see $this->__call)
     *
     * @param  string $endpointName         eg 'campaigns' or 'insights', ...
     *                                      (going to be changed to call $this->getCampaigns or $this->getInsights, ...)
     * @param  array  $params             [description]
     * @param  int|null $getTotalPagesCount limit $pages returned from response
     *                                      if NULL - all data will be returned
     * @return array                     [description]
     */
    public function getDataAll(
        $endpointName,
        array $params = [],
        $getTotalPagesCount = null
    ) {
        if (!isset($params['limit'])) {
            //default limit is 25, better get more on one request
            //  (limit param is just for paging, it is not limiting the whole result)
            $params['limit'] = 101;
        }

        $methodName = 'get' . ucfirst($endpointName);
        $res = $this->$methodName(
            $params
        );

        // file_put_contents(dir_temp() . '/DEBUG.getDataAll-res', json_encode($res));

        $list = [];
        $i = 0;
        while (isset($res['data']) && $res['data']) {
            foreach ($res['data'] as $key => $values) {
               $list[] = $values;
            }
            $i++;
            if ($getTotalPagesCount !== null && $getTotalPagesCount <= $i) {
                break;
            }

            /**
             * [next] is an absolute url pointing to the next page of results,
             *     if missing, there is not next data (due to some experiments with an api)
             *
             * but this url is missing [appsecret_proof] param
             * so we will use [cursors][after] param to set proper url with all params kept
             */
            if (
                !isset($res['paging']['next'])
                || !$res['paging']['next']
                || !isset($res['paging']['cursors']['after'])
                || !$res['paging']['cursors']['after']
            ) {
                break;
            }
            sleep(self::SLEEP_API_REQUEST_LOOP);

            $res = $this->$methodName(
                array_merge(
                    $params,
                    [
                        'after' => $res['paging']['cursors']['after']
                    ]
                )
            );

            // file_put_contents(dir_temp() . '/DEBUG.getDataAll-res', "\n\n----------nextpage--------------\n\n", FILE_APPEND);
            // file_put_contents(dir_temp() . '/DEBUG.getDataAll-res', json_encode($res), FILE_APPEND);
        }

        // file_put_contents(dir_temp() . '/DEBUG.getDataAll-list', json_encode($list));

        return $list;
    }

    public function getApiPort()
    {
        if ($this->config['api_port'] === null) {
            return null;
        }
        return intval($this->config['api_port']);
    }

    public function getApiUrl()
    {
        $url = rtrim($this->config['api_url'], '/');
        $port = $this->getApiPort();
        if ($port) {
            $url .= (":" . strval($port));
        }
        return $url;
    }

    public function isApiCertVerify()
    {
        return $this->config['api_cert_verify'] ? true : false;
    }

    public function getApiCertPath()
    {
        return $this->config['api_cert_path'];
    }

    public function getAddAccountId()
    {
        return $this->config['adaccount_id'];
    }

    public function getMarketingApiVersion()
    {
        return $this->config['marketingapi_version'];
    }

    public function isInited()
    {
        return $this->isInited;
    }

    private function setIsInited()
    {
        $this->isInited = true;
    }

    public function getAccessToken()
    {
        return $this->config['access_token'];
    }

    public function getAppSecret()
    {
        return $this->config['app_secret'];
    }

    public function getAppId()
    {
        return $this->config['app_id'];
    }

    private function getAppSecretProof()
    {
        return hash_hmac('sha256', $this->getAccessToken(), $this->getAppSecret());
    }

    /**
     * [sendRequestAuthorized description]
     * @param  string $method   [description]
     * @param  string $endpoint [description]
     * @param  array  $params   [description]
     * @param  array  $data     [description]
     * @param  array  $headers     [description]
     * @return ResponseInterface           [description]
     * @throws GuzzleException [<description>]
     */
    public function sendRequestAuthorized(
        $method,
        $endpoint,
        array $params = [],
        array $data = [],
        array $headers = []
    ) {
        try {
            return $this->sendRequest(
                $method,
                $endpoint,
                array_merge(
                    [
                        'access_token' => $this->getAccessToken(),
                        /**
                         * secure api call by aditional parameter
                         * @see https://developers.facebook.com/docs/facebook-login/security#proof
                         *      (or find somewhere else in graph api docs if link changed - goole for "appsecret_proof" param)
                         */
                        'appsecret_proof' => $this->getAppSecretProof()
                    ],
                    $params
                ),
                $data,
                $headers
            );
        } catch (GuzzleException $e) {
            $this->guzzleExceptionExtend($e);
        }
    }

    /**
     * throw RuntimeException based on GuzzleException without truncated response body messages
     * @param  GuzzleException $e [description]
     * @return void             [description]
     * @throws RuntimeException [<description>]
     */
    public function guzzleExceptionExtend(GuzzleException $guzzleException)
    {
        if ($guzzleException->getResponse() && $guzzleException->getResponse()->getBody()) {
            throw new RuntimeException(
                $guzzleException->getResponse()->getBody()->__toString(),
                $guzzleException->getCode(),
                $guzzleException
            );
        }
        throw new RuntimeException(
            $guzzleException->getMessage(),
            $guzzleException->getCode(),
            $guzzleException
        );
    }

    /**
     * send request to proper endpoint and get result
     *
     * @param  string(enum) $method get | post | put | delete
     * @param  string  $endpoint /endpoint/path
     * @param  array  $params url parameters
     * @param  array  $data   post|put data
     * @return ResponseInterface         response parsed into array (response has to be json)
     */
    public function sendRequest(
        $method,
        $endpoint,
        array $params = [],
        array $data = [],
        array $headers = []
    ) {
        $optsExtend = [
            'query' => $params,
            'json' => $data,
            'headers' => $headers
        ];
        // if Content-Type headers is passed and is not
        foreach ($headers as $headerName => $headerValue) {
            if (
                strtolower($headerName) === 'content-type'
                && stripos($headerValue, 'application/json') === false
            ) {
                unset($optsExtend['json']);
                $optsExtend['body'] = implode('', $data);
                break;
            }
        }

        return $this->httpClientRequest(
            $method,
            $this->getApiUrlFullWithEndpoint($endpoint),
            array_filter($optsExtend)
        );
    }

    /**
     * remove baseEndpoint custom value
     * (initial baseEndpoint will be used after this method call)
     *
     * @return [type] [description]
     */
    public function restoreBaseEndpoint()
    {
        $this->baseEndpointCustom = null;
    }

    /**
     * set baseEndpoint custom (non initial) value
     * (this->baseEndpointCustom will be used as baseEndpoint after this method call)
     *
     * @param string $baseEndpoint [description]
     */
    public function setBaseEndpoint($baseEndpoint)
    {
        $this->baseEndpointCustom = $baseEndpoint;
    }

    private function getBaseEndpointDefault()
    {
        return 'act_' . $this->getAddAccountId();
    }

    private function getBaseEndpoint()
    {
        if ($this->baseEndpointCustom !== null) {
            return $this->baseEndpointCustom;
        }
        if ($this->config['base_endpoint'] === null) {
            return $this->getBaseEndpointDefault();
        }
        return $this->config['base_endpoint'];
    }

    private function getApiUrlFullWithEndpoint($endpoint)
    {
        return rtrim(
            $this->getApiUrl()
                . '/' . $this->getMarketingApiVersion()
                . '/' . $this->getBaseEndpoint()
                . '/' . ltrim($endpoint, '/'),
            '/'
        );
    }

    /**
     * send request using httpClient
     * @param  string $method     [description]
     * @param  string $url        [description]
     * @param  array  $optsExtend [description]
     * @return ResponseInterface  [description]
     */
    protected function httpClientRequest($method, $url, array $optsExtend)
    {
        $opts = [
            //handle http errors with exception throw (4xx and 5xx response codes)
            'http_errors' => true,
            'verify' => $this->isApiCertVerify(),
        ];

        if ($opts['verify'] && $this->getApiCertPath()) {
            $opts['verify'] = $this->getApiCertPath();
        }

        foreach ($optsExtend as $key => $value) {
            $opts[$key] = $value;
        }

        if (!isset($opts['headers']['accept'])) {
            /**
             * need accept header,
             * without this header facebook returns "text/javascript" Content-Type
             */
            $opts['headers']['accept'] = 'application/json';
        }

        if (isEdgeDebugOn()) {
            $this->logRequestDump($opts);
        }

        return $this->httpClient->request(
            $method,
            $url,
            $opts
        );
    }

    /**
     * DUMP SOME INFO
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    protected function logRequestDump(&$opts)
    {
        // Grab the client's handler instance.
        $clientHandler = $this->httpClient->getConfig('handler');
        // Create a middleware that dumps request and response
        $tapMiddleware = GuzzleHttpMiddleware::tap(function ($request, $options) {
            // $this->log->debug(
            //     __METHOD__ . '::logRequestDump-options',
            //     $options
            // );
            // $this->log->debug(
            //     __METHOD__ . '::logRequestDump-request-getMethod',
            //     [$request->getMethod()]
            // );
            // $this->log->debug(
            //     __METHOD__ . '::logRequestDump-request-getUri',
            //     [$request->getUri()->__toString()]
            // );
            // $this->log->debug(
            //     __METHOD__ . '::logRequestDump-request-getHeaders',
            //     $request->getHeaders()
            // );
            // $this->log->debug(
            //     __METHOD__ . '::logRequestDump-$request->getBody',
            //     [$request->getBody()->__toString()]
            // );

            $this->log->debug(
                'HEADERS',
                $request->getHeaders()
            );

            $this->log->debug(
                $request->getMethod(),
                [
                    'uri' => "\n" . $request->getUri()->__toString(),
                    'body' => "\n" . $request->getBody()->__toString()
                ]
            );
        });
        $opts['handler'] = $tapMiddleware($clientHandler);
    }

    /**
     * shortcuts for handling crud requests...
     *
     * @param  string $name [description]
     * @param  array $args [description]
     * @return mixed       array for json content, ResponseInterface for other content
     * @throws RuntimeException [<description>]
     */
    public function __call($name, array $args)
    {
        /**
         * if context-nonexistent method is called
         * (e.g. private/protected from outside of an object by mistake)
         * we do not want to send request to api endpint for this method
         */
        if(method_exists($this, $name)) {
            throw new LogicException("Method '$name' must not be called from outside of object context");
        }

        $matches = [];
        if (preg_match('#^(get|post|delete|put|patch)([A-Z0-9]{0,1}[A-Za-z0-9_]*)$#', $name, $matches)) {
            $endpoint = strtolower(
                preg_replace(
                    # '/(?<=\d)(?=[A-Za-z])|(?<=[A-Za-z])(?=\d)|(?<=[a-z])(?=[A-Z])/',
                    '/(?<=[a-z])(?=[A-Z])/',
                    '-',
                    $matches[2]
                )
            );

            $response = $this->sendRequestAuthorized(
                $matches[1],
                '/' . $endpoint,
                isset($args[0]) && $args[0] ? $args[0] : [],
                isset($args[1]) && $args[1] ? $args[1] : []
            );

            $contentType = $response->getHeaderLine('Content-Type') ?: 'application/json';
            if (strpos($contentType, 'application/json') !== false) {
                return $this->getParsedBody($response);
            }

            return $response;
        }

        throw new RuntimeException("Unknown method \"$name\"");

    }

    protected function getParsedBody(ResponseInterface $response)
    {
        if ($response->getBody()) {
            $contentType = $response->getHeaderLine('Content-Type') ?: 'application/json';
            if (strpos($contentType, 'application/json') !== false) {
                $data = json_decode(
                    $response->getBody()->__toString(),
                    true,
                    512
                );
                if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
                    throw new RuntimeException(json_last_error_msg(), json_last_error());
                }
                return $data;
            }
        }
        return [];
    }
}

