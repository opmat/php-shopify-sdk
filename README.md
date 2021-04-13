# php-shopify-sdk
PHPShopifySDK is a simple miniature SDK implementation of Shopify API. It helps simplify making request to the Shopify API returning the header and body response. This SDK provides a generalized method making different forms of request to all endpoints on Shopify.

# Requirements
PHP Curl Extension is required by PHPShopifySDK to handle all http (POST/GET/PUT/DELETE) calls. The PHP Curl extension should be installed and enabled prior to working with this SDK.

# Usage
Simply instantiate the ShopifyRequest object from the class passing the Shopify shop base address and the shop credentials as it may be applicable.
>The shopCredentials is passed as Array : ['api_key' => '', 'api_secret' => '', 'api_token' => '']
>You can choose to pass only the applicable credentials and pass the non-applicable ones as empty string ('').
>

# Making Requests
