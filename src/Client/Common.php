<?php

declare (strict_types=1);
namespace Laminas\Soap\Client;

use function is_callable;
// phpcs:ignore SlevomatCodingStandard.Namespaces.UnusedUses.UnusedUse
use Laminas\Soap\Exception\InvalidArgumentException;
use function ltrim;
use Return_Type_Will_Change;
use Soap_Client;
class Common extends Soap_Client
{
    /**
     * doRequest() pre-processing method
     *
     * @var callable
     */
    protected $do_request_callback;
    /**
     * Common Soap Client constructor
     *
     * @param callable $doRequestCallback
     * @param string $wsdl
     */
    public function __construct($do_request_callback, $wsdl, array $options)
    {
        if (!is_callable($do_request_callback)) {
            throw new InvalidArgumentException('$doRequestCallback argument must be callable');
        }
        $this->do_request_callback = $do_request_callback;
        parent::__construct($wsdl, $options);
    }
    /**
     * Performs SOAP request over HTTP.
     * Overridden to implement different transport layers, perform additional
     * XML processing or other purpose.
     */
    #[Return_Type_Will_Change]
    public function __do_request(string $request, string $location, string $action, int $version, bool $one_way = false, ?string $uri_parser_class = null): ?string
    {
        // ltrim is a workaround for https://bugs.php.net/bug.php?id=63780
        return ($this->do_request_callback)($this, ltrim($request), $location, $action, $version, $one_way, $uri_parser_class);
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
    public function parent_do_request($request, $location, $action, $version, $one_way = null): ?string
    {
        if ($one_way === null) {
            return parent::__do_request($request, $location, $action, $version);
        }
        return parent::__do_request($request, $location, $action, $version, $one_way);
    }
}