<?php

declare (strict_types=1);
namespace Laminas\Soap\Client;

use Laminas\Soap\Client as SOAPClient;
/**
 * Class is intended to be used as local SOAP client which works
 * with a provided Server object.
 *
 * Could be used for development or testing purposes.
 */
class Local extends Soap_Client
{
    /**
     * Local client constructor
     *
     * @param string $wsdl
     * @param array $options
     */
    public function __construct(
        /**
         * Server object
         */
        protected \Soap_Server $server,
        $wsdl,
        $options = null
    )
    {
        // Use Server specified SOAP version as default
        $this->set_soap_version($this->server->get_soap_version());
        parent::__construct($wsdl, $options);
    }
    // @codingStandardsIgnoreStart
    /**
     * Actual "do request" method.
     *
     * @param  string $request
     * @param  string $location
     * @param  string $action
     * @param  int    $version
     * @param  int    $oneWay
     * @return mixed
     */
    public function _do_request(Common $client, $request, $location, $action, $version, $one_way = null)
    {
        // Perform request as is
        ob_start();
        $this->server->handle($request);
        $response = ob_get_clean();
        if ($response === '') {
            $server_response = $this->server->get_response();
            if ($server_response !== null) {
                $response = $server_response;
            }
        }
        return $response;
    }
    // @codingStandardsIgnoreEnd
}