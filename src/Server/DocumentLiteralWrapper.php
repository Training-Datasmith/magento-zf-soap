<?php

declare (strict_types=1);
namespace Laminas\Soap\Server;

use function call_user_func_array;
use function count;
use function get_object_vars;
use Laminas\Soap\Exception;
use Reflection_Object;
use function sprintf;
/**
 * Wraps WSDL Document/Literal Style service objects to hide SOAP request
 * message abstraction from the actual service object.
 *
 * When using the document/literal SOAP message pattern you end up with one
 * object passed to your service methods that contains all the parameters of
 * the method. This obviously leads to a problem since Laminas\Soap\Wsdl tightly
 * couples method parameters to request message parameters.
 *
 * Example:
 *
 * <code>
 *
 * {
 *     /**
 *      * @param int $x
 *      * @param int $y
 *      * @return int
 *      *
 *     public function add($x, $y)
 *     {
 *     }
 * }
 * </code>
 *
 * The document/literal wrapper pattern would lead php ext/soap to generate a
 * single "request" object that contains $x and $y properties. To solve this a
 * wrapper service is needed that extracts the properties and delegates a
 * proper call to the underlying service.
 *
 * The input variable from a document/literal SOAP-call to the client
 * MyCalculatorServiceClient#add(10, 20) would lead PHP ext/soap to create
 * the following request object:
 *
 * <code>
 * $addRequest = new \stdClass;
 * $addRequest->x = 10;
 * $addRequest->y = 20;
 * </code>
 *
 * This object does not match the signature of the server-side
 * MyCalculatorService and lead to failure.
 *
 * Also the response object in this case is supposed to be an array
 * or object with a property "addResult":
 *
 * <code>
 * $addResponse = new \stdClass;
 * $addResponse->addResult = 30;
 * </code>
 *
 * To keep your service object code free from this implementation detail
 * of SOAP this wrapper service handles the parsing between the formats.
 *
 * @example
 * <code>
 *  $service = new MyCalculatorService();
 *  $soap = new \Laminas\Soap\Server($wsdlFile);
 *  $soap->setObject(new \Laminas\Soap\Server\DocumentLiteralWrapper($service));
 *  $soap->handle();
 * </code>
 */
class Document_Literal_Wrapper
{
    protected \Reflection_Object $reflection;
    /**
     * Pass Service object to the constructor
     *
     * @param object $object
     */
    public function __construct(protected $object)
    {
        $this->reflection = new Reflection_Object($this->object);
    }
    /**
     * Proxy method that does the heavy document/literal decomposing.
     *
     * @param  array $args
     * @return mixed
     */
    public function __call(string $method, array $args)
    {
        $this->assert_only_one_argument($args);
        $this->assert_service_delegate_has_method($method);
        $delegate_args = $this->parse_arguments($method, $args[0]);
        $ret = call_user_func_array([$this->object, $method], $delegate_args);
        return $this->get_result_message($method, $ret);
    }
    /**
     * Parse the document/literal wrapper into arguments to call the real
     * service.
     *
     * @param  string $method
     * @param  object $document
     * @throws Exception\UnexpectedValueException
     */
    protected function parse_arguments($method, $document): array
    {
        $refl_method = $this->reflection->get_method($method);
        $params = [];
        foreach ($refl_method->get_parameters() as $param) {
            $params[$param->get_name()] = $param;
        }
        $delegate_args = [];
        foreach (get_object_vars($document) as $arg_name => $arg_value) {
            if (!isset($params[$arg_name])) {
                throw new Exception\UnexpectedValueException(sprintf('Received unknown argument %s which is not an argument to %s::%s', $arg_name, $this->object::class, $method));
            }
            $delegate_args[$params[$arg_name]->get_position()] = $arg_value;
        }
        return $delegate_args;
    }
    /**
     * Returns result message content
     *
     * @param  mixed $ret
     */
    protected function get_result_message(string $method, $ret): array
    {
        return [$method . 'Result' => $ret];
    }
    /**
     * @param  string $method
     * @throws Exception\BadMethodCallException
     */
    protected function assert_service_delegate_has_method($method)
    {
        if (!$this->reflection->has_method($method)) {
            throw new Exception\BadMethodCallException(sprintf('Method %s does not exist on delegate object %s', $method, $this->object::class));
        }
    }
    /**
     * @throws Exception\UnexpectedValueException
     */
    protected function assert_only_one_argument(array $args)
    {
        if (count($args) !== 1) {
            throw new Exception\UnexpectedValueException(sprintf('Expecting exactly one argument that is the document/literal wrapper, got %d', count($args)));
        }
    }
}