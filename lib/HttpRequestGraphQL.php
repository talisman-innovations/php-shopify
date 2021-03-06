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
    const MAX_REQUESTED_COST = 500;
    const MAX_ACTUAL_COST = 200;

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

            $cost = $response['extensions']['cost'];

            if ($cost['requestedQueryCost'] >= self::MAX_REQUESTED_COST || $cost['actualQueryCost'] >= self::MAX_ACTUAL_COST) {
                $logger->warning('Shopify GraphQL query is expensive', $cost);
            }

            $wait = is_array($response) ? ceil(self::checkForThrottle($response)) : 0;

            if ($wait <= 0) {
                break;
            }

            $logger->info("Shopify GraphQL throttle, sleeping $wait seconds");
            sleep($wait);
        }

        return $response;
    }

    /**
     * @param array $response
     * @return float seconds
     */
    public static function checkForThrottle(array $response)
    {
        // Check for throttling https://help.shopify.com/api/graphql-admin-api/graphql-admin-api-rate-limits"
        if (!isset($response['errors'])) {
            return 0;
        }

        foreach ($response['errors'] as $error) {
            if ($error['extensions']['code'] == 'THROTTLED') {
                $throttled = true;
            }
        }

        if (!isset($throttled)) {
            return 0;
        }

        $cost = $response['extensions']['cost'];

        return ($cost['requestedQueryCost'] - $cost['throttleStatus']['currentlyAvailable']) / $cost['throttleStatus']['restoreRate'];
    }

}