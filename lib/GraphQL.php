<?php
/**
 * Created by PhpStorm.
 * User: Tareq
 * Date: 5/27/2019
 * Time: 12:36 PM
 *
 * @see https://help.shopify.com/en/api/graphql-admin-api GraphQL Admin API
 */

namespace PHPShopify;


use PHPShopify\Exception\SdkException;

class GraphQL extends ShopifyResource
{

    /**
     * @inheritdoc
     */
    protected function getResourcePath()
    {
        return 'graphql';
    }

    /**
     * Call POST method for any GraphQL request
     *
     * @param string $graphQL A valid GraphQL String. @see https://help.shopify.com/en/api/graphql-admin-api/graphiql-builder GraphiQL builder - you can build your graphql string from here.
     * @param string $url
     * @param bool $wrapData
     * @param array|null $variables
     *
     * @return array
     * @throws Exception\ApiException
     * @throws Exception\CurlException
     * @throws Exception\ResourceRateLimitException
     * @throws SdkException
     * @uses HttpRequestGraphQL::post() to send the HTTP request
     *
     */
    public function post($graphQL, $url = null, $wrapData = false, $variables = null)
    {
        if (!$url) $url = $this->generateUrl();

        $response = HttpRequestGraphQL::post($this->logger, $url, $graphQL, $this->httpHeaders, $variables);

        return $this->processResponse($response);
    }

    /**
     * @inheritdoc
     */
    public function get($urlParams = array(), $url = null, $dataKey = null)
    {
        throw new SdkException("Only POST method is allowed for GraphQL!");
    }

    /**
     * @inheritdoc
     */
    public function put($dataArray, $url = null, $wrapData = true)
    {
        throw new SdkException("Only POST method is allowed for GraphQL!");
    }

    /**
     * @inheritdoc
     */
    public function delete($urlParams = array(), $url = null)
    {
        throw new SdkException("Only POST method is allowed for GraphQL!");
    }
}