<?php
/**
 * ShopifyRequest  class that can execute any GET and PUT request to shopify
 * 
 * @author OPMat
 */
class ShopifyRequest {
    
    /**
     * @var string The shop address (example.myshopify.com) to be connected to
     */
    private $shop_address;

    /**
     * @var string The API Key to use with these requests
     */
    private $api_key;

    /**
     * @var string The API Secret Key associated with the provided API Key
     */
    private $api_secret;

    /**
     * @var string The API Authentication Token to connect to the provided shop
     */
    private $api_token;
    
    
    /**
     * Initialize the ShopifyRequest object
     *
     * @param string $shopAddress The Shopify Shop domain to connect to: eg. example.myshopify.com
     * @param array $shopCredentials String: The access token for the provided Shop; Array: ['api_key' => '', 'api_secret' => '', 'api_token' => ''] The Shopify API credentials for your application
     * @return null
     */
    public function __construct(string $shopAddress, array $shopCredentials) {
        $this->shop_address = $shopAddress;
        $this->api_key = (isset($shopCredentials['api_key']))? $shopCredentials['api_key'] : "";
        $this->api_secret = (isset($shopCredentials['api_secret']))? $shopCredentials['api_secret'] : "";
        $this->api_token = (isset($shopCredentials['api_token']))? $shopCredentials['api_token'] : $this->api_token;
    }
    
    
    /**
     * Initiate a request or call to Shopify  API
     * 
     * @param string $endpoint The endpoint to access on Shopify (ex: "/admin/api/2019-10/products.json")
     * @param string $requestMethod The method (POST/GET/PUT/DELETE) to be used to call Shopify. 
     * @param array $params The parameters to send with the API request as key=>value array
     * @param string $page_info A unique ID used to access a certain page of results when pagination is required
     * @param integer $limit The maximum number of results to show. Default=50 and Maximum of 250
     * @return mixed return false is there is an error else returns an Array with the response from Shopify
     */
    public function makeRequest(string $endpoint, string $requestMethod, array $params = [], string $page_info = "", int $limit = 50) {
        
        $options = [];
        //concatenate the shop address with the endpoint to form Request URL
        $url = $this->shop_address . $endpoint; 
        $requestMethod = strtoupper($requestMethod);
        
        if (empty($this->api_token)) {
            $options['headers']['Authorization'] = 'Basic ' . base64_encode($this->api_key . ':' . $this->api_secret);
        } else {
            $options['headers']['X-Shopify-Access-Token'] = $this->api_token;
        }
        
        //if $page_info is set, pagination is enabled. A request that includes the 
        //page_info parameter can't include any other parameters except for limit and fields 
        //https://shopify.dev/tutorials/make-paginated-requests-to-rest-admin-api
        if ($page_info != ""){
            $fields = (isset($params['fields']))? $params['fields']: "";
            unset($params);
            $params['page_info'] = $page_info;            
            if ($fields != "") :
                $params['fields'] = $fields;
            endif;
        } 
        //Ensure limit is set between minimum 50 and maximum 250
        $params['limit'] = ($limit>250)? 250 : ($limit<=0)? 50 : $limit;
        
        // Prepare the request based on the method used. Ignore Delete and Post 
        // Request as they are not required for this Solution
        switch ($requestMethod) {
            case 'GET':
            case 'DELETE':
                $options['query'] = self::buildHTTPQuery($params);                
                break;

            case 'PUT':
            case 'POST':
                $options['body'] = json_encode($params);
                $options['headers']['Content-Type'] = 'application/json';
                $options['headers']['Content-Length'] = strlen($options['body']);
                break;
                        
            default:
                $err = "Request Method not Supported";
                return false;
        }
        
        return $this->__curl($url, $requestMethod, $options);
    }
    
    
    /**
     * Curl Helper function
     * 
     * @param string $url The full URL including shop address and endpoint to access on Shopify
     * @param string $requestMethod The method (POST/GET/PUT/DELETE) to be used to call Shopify
     * @param array $options The parameters to send with the API request
     * @return mixed return false is there is an error else returns an Array with the response
     */
    private function __curl(string $url, string $requestMethod, array $options) {
        
        // Create Curl resource
        $ch = curl_init();
        
        switch ($requestMethod) {
            case 'GET':
            case 'DELETE':
                $url .= (isset($options['query']))? "?" . $options['query'] : "";
                // Set URL
                curl_setopt($ch, CURLOPT_URL, $url);
                break;

            case 'PUT':
            case 'POST':
                // Set URL
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $options['body']);
                break;
        }

        //Return the transfer as a string
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, true);

        $headers = [];
        foreach ($options['headers'] as $key => $value) {
            $headers[] = "$key: $value";
        } 
        //Set HTTP Headers
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $requestMethod);
        
        $output   = curl_exec($ch);
        $resp = $this->__parseResponse($output);
        
        if (curl_errno($ch)) {
            return false; 
        }
        // close curl resource to free up system resources
        curl_close($ch);

        return $resp;
    }

    
    /**
     * Build a query string from a data array for GET request(s)
     * This is a replacement for http_build_query because that returns an url-encoded string.
     *
     * @param array $data Data array
     *
     * @return array
     */
    public static function buildHTTPQuery($data)
    {
        $params = [];
        foreach ($data as $key => $value) {
            $params[] = "$key=$value";
        }
        return join('&', $params);
    }
    
    
    /**
     * @param string $resp Curl Response to be parsed into header and body components
     */
    private function __parseResponse(string $resp)  {
        $myheaders = [];
        $response = \explode("\r\n\r\n", $resp);
        if (\count($response) > 1) {
            // Retrieve the last two parts
            $response = \array_slice($response, -2, 2);
            list($headers, $body) = $response;
            foreach (\explode("\r\n", $headers) as $header) {
                $pair = \explode(': ', $header, 2);
                if (isset($pair[1])) {
                    $headerKey = strtolower($pair[0]);
                    $myheaders[$headerKey] = $pair[1];
                }
            }
        } else {
            $body = $response[0];
        }
        
        return ["header"=>$myheaders, "body"=>json_decode($body, TRUE)];
    }

    
}
