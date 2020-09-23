<?php
/**
 * Created by PhpStorm.
 * @author Tareq Mahmood <tareqtms@yahoo.com>
 * Created at: 8/24/16 9:03 AM UTC+06:00
 */

namespace PHPShopify;

use Psr\Log\LoggerInterface;

/**
 * Class HttpRequestJson
 *
 * Prepare the data / headers for JSON requests, make the call and decode the response
 * Accepts data in array format returns data in array format
 *
 * @uses CurlRequest
 *
 * @package PHPShopify
 */
class HttpRequestJson
{

    /**
     * HTTP request headers
     *
     * @var array
     */
    protected static $httpHeaders;

    /**
     * Prepared JSON string to be posted with request
     *
     * @var string
     */
    private static $postDataJSON;


    /**
     * Prepare the data and request headers before making the call
     *
     * @param array $httpHeaders
     * @param array $dataArray
     *
     * @return void
     */
    protected static function prepareRequest($httpHeaders = array(), $dataArray = array())
    {

        self::$postDataJSON = json_encode($dataArray);

        self::$httpHeaders = $httpHeaders;

        if (!empty($dataArray)) {
            self::$httpHeaders['Content-type'] = 'application/json';
            self::$httpHeaders['Content-Length'] = strlen(self::$postDataJSON);
        }
    }

    /**
     * Implement a GET request and return json decoded output
     *
     * @param LoggerInterface $logger
     * @param string $url
     * @param array $httpHeaders
     *
     * @return array
     *
     * @throws Exception\CurlException
     * @throws Exception\ResourceRateLimitException
     */
    public static function get($logger, $url, $httpHeaders = array())
    {
        self::prepareRequest($httpHeaders);

        $response = CurlRequest::get($logger, $url, self::$httpHeaders);

        return self::processResponse($response);
    }

    /**
     * Implement a POST request and return json decoded output
     *
     * @param LoggerInterface $logger
     * @param string $url
     * @param array $dataArray
     * @param array $httpHeaders
     *
     * @return array
     * @throws Exception\CurlException
     * @throws Exception\ResourceRateLimitException
     */
    public static function post($logger, $url, $dataArray, $httpHeaders = array())
    {
        self::prepareRequest($httpHeaders, $dataArray);

        $response = CurlRequest::post($logger, $url, self::$postDataJSON, self::$httpHeaders);

        return self::processResponse($response);
    }

    /**
     * Implement a PUT request and return json decoded output
     *
     * @param LoggerInterface $logger
     * @param string $url
     * @param array $dataArray
     * @param array $httpHeaders
     *
     * @return array
     * @throws Exception\CurlException
     * @throws Exception\ResourceRateLimitException
     */
    public static function put($logger, $url, $dataArray, $httpHeaders = array())
    {
        self::prepareRequest($httpHeaders, $dataArray);

        $response = CurlRequest::put($logger, $url, self::$postDataJSON, self::$httpHeaders);

        return self::processResponse($response);
    }

    /**
     * Implement a DELETE request and return json decoded output
     *
     * @param LoggerInterface $logger
     * @param string $url
     * @param array $httpHeaders
     *
     * @return array
     *
     * @throws Exception\CurlException
     * @throws Exception\ResourceRateLimitException
     */
    public static function delete($logger, $url, $httpHeaders = array())
    {
        self::prepareRequest($httpHeaders);

        $response = CurlRequest::delete($logger, $url, self::$httpHeaders);

        return self::processResponse($response);
    }

    /**
     * Decode JSON response
     *
     * @param string $response
     *
     * @return array
     */
    protected static function processResponse($response)
    {
        return json_decode($response, true);
    }

}