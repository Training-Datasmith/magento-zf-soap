<?php

namespace Laminas\Soap\Client;

use Laminas\Soap\Exception\InvalidArgumentException;
// phpcs:ignore SlevomatCodingStandard.Namespaces.UnusedUses.UnusedUse
use ReturnTypeWillChange;
use SoapClient;

use function is_callable;
use function ltrim;

class Common extends SoapClient
{
    /**
     * doRequest() pre-processing method
     *
     * @var callable
     */
    protected $doRequestCallback;

    /**
     * Common Soap Client constructor
     *
     * @param callable $doRequestCallback
     * @param string $wsdl
     */
    public function __construct($doRequestCallback, $wsdl, array $options)
    {
        if (! is_callable($doRequestCallback)) {
            throw new InvalidArgumentException('$doRequestCallback argument must be callable');
        }

        $this->doRequestCallback = $doRequestCallback;
        parent::__construct($wsdl, $options);
    }

    /**
     * Performs SOAP request over HTTP.
     * Overridden to implement different transport layers, perform additional
     * XML processing or other purpose.
     */
    #[ReturnTypeWillChange]
    public function __doRequest(
        string $request,
        string $location,
        string $action,
        int $version,
        bool $oneWay = false,
        ?string $uriParserClass = null
    ): ?string {
        // ltrim is a workaround for https://bugs.php.net/bug.php?id=63780
        return ($this->doRequestCallback)(
            $this,
            ltrim($request),
            $location,
            $action,
            $version,
            $oneWay,
            $uriParserClass
        );
    }

    /**
     * Performs SOAP request on parent class explicitly.
     * Required since PHP 8.2 due to a deprecation on call_user_func([$client, 'SoapClient::__doRequest'], ...)
     *
     * @internal
     *
     * @param  string   $request
     * @param  string   $location
     * @param  string   $action
     * @param  int      $version
     * @param  null|int $oneWay
     */
    public function parentDoRequest($request, $location, $action, $version, $oneWay = null): ?string
    {
        if ($oneWay === null) {
            return parent::__doRequest($request, $location, $action, $version);
        }

        return parent::__doRequest($request, $location, $action, $version, $oneWay);
    }
}
