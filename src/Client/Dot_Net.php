<?php

declare (strict_types=1);
namespace Laminas\Soap\Client;

use InvalidArgumentException;
use Laminas\Http\Client\Adapter\Curl as CurlClient;
use Laminas\Http\Response as HttpResponse;
use Laminas\Soap\Client as SOAPClient;
use Laminas\Soap\Client\Common as CommonClient;
use Laminas\Soap\Exception;
use Laminas\Uri\Http as HttpUri;
use const SOAP_1_1;
use Traversable;
/**
 * .NET SOAP client
 *
 * Class is intended to be used with .NET Web Services.
 */
class Dot_Net extends Soap_Client
{
    /**
     * Curl HTTP client adapter.
     *
     * @var CurlClient
     */
    protected $curl_client;
    /**
     * The last request headers.
     *
     * @var string
     */
    protected $last_request_headers = '';
    /**
     * The last response headers.
     *
     * @var string
     */
    protected $last_response_headers = '';
    /**
     * SOAP client options.
     *
     * @var array
     */
    protected $options = [];
    /**
     * Should NTLM authentication be used?
     *
     * @var boolean
     */
    protected $use_ntlm = false;
    /**
     * Constructor
     *
     * @param string $wsdl
     * @param array $options
     */
    public function __construct($wsdl = null, $options = null)
    {
        // Use SOAP 1.1 as default
        $this->set_soap_version(SOAP_1_1);
        parent::__construct($wsdl, $options);
    }
    // @codingStandardsIgnoreStart
    /**
     * Do request proxy method.
     *
     * @param  CommonClient $client   Actual SOAP client.
     * @param  string       $request  The request body.
     * @param  string       $location The SOAP URI.
     * @param  string       $action   The SOAP action to call.
     * @param  int          $version  The SOAP version to use.
     * @param  int          $oneWay  (Optional) The number 1 if a response is not expected.
     * @return string The XML SOAP response.
     */
    public function _do_request(Common_Client $client, $request, $location, $action, $version, $one_way = null)
    {
        if (!$this->use_ntlm) {
            return parent::_do_request($client, $request, $location, $action, $version, $one_way);
        }
        $curl_client = $this->get_curl_client();
        // @todo persistent connection ?
        $headers = ['Content-Type' => 'text/xml; charset=utf-8', 'Method' => 'POST', 'SOAPAction' => '"' . $action . '"', 'User-Agent' => 'PHP-SOAP-CURL'];
        $uri = new Http_Uri($location);
        // @todo use parent set* options for ssl certificate authorization
        $curl_client->set_curl_option(CURLOPT_HTTPAUTH, CURLAUTH_NTLM)->set_curl_option(CURLOPT_SSL_VERIFYHOST, false)->set_curl_option(CURLOPT_SSL_VERIFYPEER, false)->set_curl_option(CURLOPT_USERPWD, sprintf('%s:%s', $this->options['login'], $this->options['password']));
        // Perform the cURL request and get the response
        $curl_client->connect($uri->get_host(), $uri->get_port());
        $curl_client->write('POST', $uri, 1.1, $headers, $request);
        $response = Http_Response::from_string($curl_client->read());
        // @todo persistent connection ?
        $curl_client->close();
        // Save headers
        $this->last_request_headers = $this->flatten_headers($headers);
        $this->last_response_headers = $response->get_headers()->to_string();
        // Return only the XML body
        return $response->get_body();
    }
    // @codingStandardsIgnoreEnd
    /**
     * Returns the cURL client that is being used.
     *
     * @return CurlClient
     */
    public function get_curl_client()
    {
        if ($this->curl_client === null) {
            $this->curl_client = new Curl_Client();
        }
        return $this->curl_client;
    }
    /**
     * Retrieve request headers.
     *
     * @return string Request headers.
     */
    public function get_last_request_headers()
    {
        return $this->last_request_headers;
    }
    /**
     * Retrieve response headers (as string)
     *
     * @return string Response headers.
     */
    public function get_last_response_headers()
    {
        return $this->last_response_headers;
    }
    /**
     * Sets the cURL client to use.
     *
     * @param  CurlClient $curlClient The cURL client.
     */
    public function set_curl_client(Curl_Client $curl_client): static
    {
        $this->curl_client = $curl_client;
        return $this;
    }
    /**
     * Sets options.
     *
     * Allows setting options as an associative array of option => value pairs.
     *
     * @param array|Traversable $options Options.
     * @throws InvalidArgumentException If an unsupported option is passed.
     * @return self
     */
    public function set_options($options)
    {
        if (isset($options['authentication']) && $options['authentication'] === 'ntlm') {
            $this->use_ntlm = true;
            unset($options['authentication']);
        }
        $this->options = $options;
        return parent::set_options($options);
    }
    // @codingStandardsIgnoreStart
    /**
     * Perform arguments pre-processing
     *
     * My be overridden in descendant classes
     *
     * @param  array $arguments
     * @return array
     * @throws Exception\RuntimeException
     */
    protected function _pre_process_arguments($arguments)
    {
        if (count($arguments) > 1 || count($arguments) == 1 && !is_array(reset($arguments))) {
            throw new Exception\RuntimeException('.Net webservice arguments must be grouped into an array: array("a" => $a, "b" => $b, ...).');
        }
        // Do nothing
        return $arguments;
    }
    // @codingStandardsIgnoreEnd
    // @codingStandardsIgnoreStart
    /**
     * Perform result pre-processing
     *
     * My be overridden in descendant classes
     *
     * @param  object $result
     * @return mixed
     */
    protected function _pre_process_result($result)
    {
        $result_property = $this->get_last_method() . 'Result';
        if (property_exists($result, $result_property)) {
            return $result->{$result_property};
        }
        return $result;
    }
    // @codingStandardsIgnoreEnd
    /**
     * Flattens an HTTP headers array into a string.
     *
     * @param  array $headers The headers to flatten.
     * @return string The headers string.
     */
    protected function flatten_headers(array $headers): string
    {
        $result = '';
        foreach ($headers as $name => $value) {
            $result .= $name . ': ' . $value . "\r\n";
        }
        return $result;
    }
}