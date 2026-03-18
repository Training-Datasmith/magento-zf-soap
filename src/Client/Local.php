<?php

namespace Laminas\Soap\Client;

use Laminas\Soap\Client as SOAPClient;
use Laminas\Soap\Server as SOAPServer;

/**
 * Class is intended to be used as local SOAP client which works
 * with a provided Server object.
 *
 * Could be used for development or testing purposes.
 */
class Local extends SOAPClient
{
    /**
     * Local client constructor
     *
     * @param string $wsdl
     * @param array $options
     */
    public function __construct(/**
     * Server object
     */
    protected \SOAPServer $server, $wsdl, $options = null)
    {
        // Use Server specified SOAP version as default
        $this->setSoapVersion($this->server->getSoapVersion());

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
    public function _doRequest(Common $client, $request, $location, $action, $version, $oneWay = null)
    {
        // Perform request as is
        ob_start();
        $this->server->handle($request);
        $response = ob_get_clean();

        if ($response === '') {
            $serverResponse = $this->server->getResponse();
            if ($serverResponse !== null) {
                $response = $serverResponse;
            }
        }

        return $response;
    }
    // @codingStandardsIgnoreEnd
}
