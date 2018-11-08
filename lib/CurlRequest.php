<?php
/**
 * Created by PhpStorm.
 * @author Tareq Mahmood <tareqtms@yahoo.com>
 * Created at 8/17/16 2:50 PM UTC+06:00
 */

namespace PHPShopify;


use ConnectorSupport\Curl\InjectVariables;
use PHPShopify\Exception\CurlException;

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
     * @param string $url
     * @param array $httpHeaders
     *
     * @return string
     */
    public static function get($url, $httpHeaders = array())
    {
        //Initialize the Curl resource
        $ch = self::init($url, $httpHeaders);

        ////
        $injector = InjectVariables::instance();
        $logger = $injector->logger;
        if ($logger !== null) {
            curl_setopt($ch, CURLOPT_HEADER, 1);
            $logger->logRequest('GET', $url, '', $httpHeaders, '');
        }
        ////

        return self::processRequest('GET', $url, $ch);
    }

    /**
     * Implement a POST request and return output
     *
     * @param string $url
     * @param array $data
     * @param array $httpHeaders
     *
     * @return string
     */
    public static function post($url, $data, $httpHeaders = array())
    {
        $ch = self::init($url, $httpHeaders);
        //Set the request type
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

        ////
        $injector = InjectVariables::instance();
        $logger = $injector->logger;
        if ($logger !== null) {
            curl_setopt($ch, CURLOPT_HEADER, 1);
            $logger->logRequest('POST', $url, '', $httpHeaders, '');
        }
        ////

        return self::processRequest('POST', $url, $ch);
    }

    /**
     * Implement a PUT request and return output
     *
     * @param string $url
     * @param array $data
     * @param array $httpHeaders
     *
     * @return string
     */
    public static function put($url, $data, $httpHeaders = array())
    {
        $ch = self::init($url, $httpHeaders);
        //set the request type
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

        ////
        $injector = InjectVariables::instance();
        $logger = $injector->logger;
        if ($logger !== null) {
            curl_setopt($ch, CURLOPT_HEADER, 1);
            $logger->logRequest('PUT', $url, '', $httpHeaders, '');
        }
        ////

        return self::processRequest('PUT', $url, $ch);
    }

    /**
     * Implement a DELETE request and return output
     *
     * @param string $url
     * @param array $httpHeaders
     *
     * @return string
     */
    public static function delete($url, $httpHeaders = array())
    {
        $ch = self::init($url, $httpHeaders);
        //set the request type
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');

        ////
        $injector = InjectVariables::instance();
        $logger = $injector->logger;
        if ($logger !== null) {
            curl_setopt($ch, CURLOPT_HEADER, 1);
            $logger->logRequest('DELETE', $url, '', $httpHeaders, '');
        }
        ////

        return self::processRequest('DELETE', $url, $ch);
    }

    /**
     * Execute a request, release the resource and return output
     *
     * @param string $method
     * @param string $url
     * @param resource $ch
     *
     * @throws CurlException if curl request is failed with error
     *
     * @return string
     */
    protected static function processRequest($method, $url, $ch)
    {
        # Check for 429 leaky bucket error
        while(1) {
             $output = curl_exec($ch);
             self::$lastHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
             if(self::$lastHttpCode != 429) {
                break;
             }
             usleep(500000);
        }

        ////
        $injector = InjectVariables::instance();
        $logger = $injector->logger;
        if ($logger !== null) {
            $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $headers = substr($output, 0, $header_size);
            $output = substr($output, $header_size);
            $logger->logResponse($method, $url, self::$lastHttpCode, explode("\r\n", $headers), $output);
        }
        ////

        if (curl_errno($ch)) {
            throw new Exception\CurlException(curl_errno($ch) . ' : ' . curl_error($ch));
        }

        // close curl resource to free up system resources
        curl_close($ch);

        return $output;
    }

}