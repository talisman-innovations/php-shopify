<?php
/**
 * Created by PhpStorm.
 * User: Tareq
 * Date: 5/30/2019
 * Time: 3:25 PM
 */

namespace PHPShopify;


use PHPShopify\Exception\SdkException;
use Psr\Log\LoggerInterface;

class HttpRequestGraphQL extends HttpRequestJson
{
    /**
     * Prepared GraphQL string to be posted with request
     *
     * @var string
     */
    private static $postDataGraphQL;

    const MAX_RETRIES = 3;

    /**
     * Prepare the data and request headers before making the call
     *
     * @param array $httpHeaders
     * @param mixed $data
     * @param array|null $variables
     *
     * @return void
     *
     * @throws SdkException if $data is not a string
     */
    protected static function prepareRequest($httpHeaders = array(), $data = array(), $variables = null)
    {
        if (is_string($data)) {
            self::$postDataGraphQL = $data;
        } else {
            throw new SdkException("Only GraphQL string is allowed!");
        }

        if (!isset($httpHeaders['X-Shopify-Access-Token'])) {
            throw new SdkException("The GraphQL Admin API requires an access token for making authenticated requests!");
        }

        self::$httpHeaders = $httpHeaders;

        if (is_array($variables)) {
            self::$postDataGraphQL = json_encode(['query' => $data, 'variables' => $variables]);
            self::$httpHeaders['Content-type'] = 'application/json';
        } else {
            self::$httpHeaders['Content-type'] = 'application/graphql';
        }
    }

    /**
     * Implement a POST request and return json decoded output
     *
     * @param LoggerInterface $logger
     * @param string $url
     * @param mixed $data
     * @param array $httpHeaders
     * @param array|null $variables
     *
     * @return array
     * @throws Exception\CurlException
     * @throws SdkException
     */
    public static function post($logger, $url, $data, $httpHeaders = array(), $variables = null)
    {
        self::prepareRequest($httpHeaders, $data, $variables);

        for ($retries = 0; $retries < self::MAX_RETRIES; $retries++) {
            $rawResponse = CurlRequest::post($logger, $url, self::$postDataGraphQL, self::$httpHeaders);

            $response = self::processResponse($rawResponse);

            $wait = ceil(self::waitForThrottle($response, $logger));

            if ($wait <= 0.0) {
                break;
            }

            $logger->info("Shopify GraphQL throttle, sleeping $wait seconds");
            sleep($wait);
        }

        return $response;
    }

    /**
     * @param array $response
     * @param LoggerInterface $logger
     * @return float seconds
     */
    public static function waitForThrottle(array $response, LoggerInterface $logger)
    {
        // Check for throttling https://help.shopify.com/api/graphql-admin-api/graphql-admin-api-rate-limits"
        if (!isset($response['errors'])) {
            $logger->info('No error, wait 0.0');
            return 0.0;
        }

        $throttled = false;
        foreach ($response['errors'] as $error) {
            if ($error['extensions']['code'] == 'THROTTLED') {
                $throttled = true;
            }
        }

        $logger->info("Checked error codes, throttled is $throttled");

        if (!$throttled) {
            return 0.0;
        }

        if (!isset($response['extensions']['cost'])) {
            $logger->info("No extensions cost block");
            return 0.0;
        }

        $cost = $response['extensions']['cost'];
        $logger->info("requestedQueryCost {$cost['requestedQueryCost']} currentlyAvailable {$cost['throttleStatus']['currentlyAvailable']} restoreRate {$cost['throttleStatus']['restoreRate']}");
        return ($cost['requestedQueryCost'] - $cost['throttleStatus']['currentlyAvailable']) / $cost['throttleStatus']['restoreRate'];
    }

}