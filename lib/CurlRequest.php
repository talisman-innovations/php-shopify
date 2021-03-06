<?php
/**
 * Created by PhpStorm.
 * @author Tareq Mahmood <tareqtms@yahoo.com>
 * Created at 8/17/16 2:50 PM UTC+06:00
 */

namespace PHPShopify;

use PHPShopify\Exception\CurlException;
use Psr\Log\LoggerInterface;

/*
|--------------------------------------------------------------------------
| CurlRequest
|--------------------------------------------------------------------------
|
| This class handles get, post, put, delete HTTP requests
|
*/

class CurlRequest
{
    /**
     * HTTP Code of the last executed request
     *
     * @var integer
     */
    public static $lastHttpCode;

    /**
     * HTTP response headers of last executed request
     *
     * @var array
     */
    public static $lastHttpResponseHeaders = array();

    const MAX_RETRIES = 5;

    /**
     * Initialize the curl resource
     *
     * @param string $url
     * @param array $httpHeaders
     *
     * @return resource
     */
    protected static function init($url, $httpHeaders = array())
    {
        // Create Curl resource
        $ch = curl_init();

        // Set URL
        curl_setopt($ch, CURLOPT_URL, $url);

        //Return the transfer as a string
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'PHPClassic/PHPShopify');
        curl_setopt($ch, CURLOPT_COOKIESESSION, true);

        $headers = array();
        foreach ($httpHeaders as $key => $value) {
            $headers[] = "$key: $value";
        }
        //Set HTTP Headers
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        return $ch;
    }

    /**
     * Implement a GET request and return output
     *
     * @param LoggerInterface $logger
     * @param string $url
     * @param array $httpHeaders
     *
     * @return string
     *
     * @throws CurlException
     */
    public static function get($logger, $url, $httpHeaders = array())
    {
        //Initialize the Curl resource
        $ch = self::init($url, $httpHeaders);

        $response = self::processRequest($ch, 'GET', $url, $httpHeaders, null, $logger);

        return $response->getBody();
    }

    /**
     * Implement a POST request and return output
     *
     * @param LoggerInterface $logger
     * @param string $url
     * @param string $data
     * @param array $httpHeaders
     *
     * @return string
     *
     * @throws CurlException
     */
    public static function post($logger, $url, $data, $httpHeaders = array())
    {
        $ch = self::init($url, $httpHeaders);
        //Set the request type
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

        $response = self::processRequest($ch, 'POST', $url, $httpHeaders, $data, $logger);

        return $response->getBody();
    }

    /**
     * Implement a PUT request and return output
     *
     * @param LoggerInterface $logger
     * @param string $url
     * @param string $data
     * @param array $httpHeaders
     *
     * @return string
     *
     * @throws CurlException
     */
    public static function put($logger, $url, $data, $httpHeaders = array())
    {
        $ch = self::init($url, $httpHeaders);
        //set the request type
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

        $response = self::processRequest($ch, 'PUT', $url, $httpHeaders, $data, $logger);

        return $response->getBody();
    }

    /**
     * Implement a DELETE request and return output
     *
     * @param LoggerInterface $logger
     * @param string $url
     * @param array $httpHeaders
     *
     * @return string
     *
     * @throws CurlException
     */
    public static function delete($logger, $url, $httpHeaders = array())
    {
        $ch = self::init($url, $httpHeaders);
        //set the request type
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');

        $response = self::processRequest($ch, 'DELETE', $url, $httpHeaders, null, $logger);

        return $response->getBody();
    }

    /**
     * Execute a request, release the resource and return output
     *
     * @param resource $ch
     * @param $method
     * @param string $url
     * @param array $httpHeaders
     * @param string $data
     * @param LoggerInterface $logger
     * @return CurlResponse
     *
     * @throws CurlException if curl request is failed with error
     */
    protected static function processRequest($ch, $method, $url, $httpHeaders, $data, $logger)
    {
        # Check for 429 leaky bucket error
        for ($retries = 0; $retries < self::MAX_RETRIES; $retries++) {
            $output = curl_exec($ch);
            $response = new CurlResponse($output);
            $info = curl_getinfo($ch);

            self::logRequest($logger, $method, $url, $httpHeaders, $data, $info, $response);

            self::$lastHttpCode = $info['http_code'];

            switch (self::$lastHttpCode) {
                case 503:
                case 502:
                case 520:
                case 406:
                    $sleep = 1 << $retries;
                    $logger->info("Shopify unavailable, retry after $sleep seconds");
                    sleep($sleep);
                    break;
                case 429:
                    $sleep = ceil($response->getHeader('Retry-After'));
                    $logger->info("Shopify rate limiter, retry after $sleep seconds, retry $retries");
                    sleep($sleep);
                    break;
                default:
                    $usage = $response->getHeader('X-Shopify-Shop-Api-Call-Limit');

                    if (!$usage) {
                        break 2;
                    }

                    list($used, $total) = explode('/', $usage);

                    if ($total - $used <= 2) {
                        $logger->info("Shopify rate limiter, used $usage, waiting 1 second");
                        sleep(1);
                    }
                    break 2;
            }
        }

        if (curl_errno($ch)) {
            throw new Exception\CurlException(curl_errno($ch) . ' : ' . curl_error($ch));
        }

        // close curl resource to free up system resources
        curl_close($ch);

        return $response;
    }

    /**
     * @param LoggerInterface $logger
     * @param string $method
     * @param string $url
     * @param array $httpHeaders
     * @param string $data
     * @param array $info
     * @param CurlResponse $response
     */
    protected static function logRequest($logger, $method, $url, $httpHeaders, $data, $info, $response)
    {
        if (!$logger) {
            return;
        }

        $message = $method . ' ' . $url;

        $context['url'] = $url;
        $context['method'] = $method;
        $context['request_headers'] = $httpHeaders;

        $body = json_decode($data, TRUE);
        $context['request_body'] = $body ? $body : $data;

        $context['status'] = $info['http_code'];
        $context['elapsed_time'] = $info['total_time'];
        $context['response_headers'] = $response->getHeaders();

        $body = json_decode($response->getBody(), TRUE);
        $context['response_body'] = $body ? $body : $response->getBody();

        $logger->info($message, $context);
    }
}
