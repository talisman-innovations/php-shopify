<?php
/**
 * Copyright Talisman Innovations Ltd. (2020). All rights reserved.
 */

namespace PHPShopify;


use PHPUnit\Framework\TestCase;

class GraphQLTest extends TestCase
{

    public function testThrottle()
    {
        $response = <<< EOD
{
  "errors": [
    {
      "message": "Throttled",
      "extensions": {
        "code": "THROTTLED",
        "documentation": "https://help.shopify.com/api/graphql-admin-api/graphql-admin-api-rate-limits"
      }
    }
  ],
  "extensions": {
    "cost": {
      "requestedQueryCost": 507,
      "actualQueryCost": null,
      "throttleStatus": {
        "maximumAvailable": 1000.0,
        "currentlyAvailable": 493,
        "restoreRate": 50.0
      }
    }
  }
}
EOD;
        $response = json_decode($response, true);
        $wait = HttpRequestGraphQL::checkForThrottle($response);
        $this->assertEquals(0.28, $wait);
    }

}