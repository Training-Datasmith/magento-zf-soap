<?php

declare (strict_types=1);
namespace Laminas\Soap\Auto_Discover\Discovery_Strategy;

use Laminas\Server\Reflection\Abstract_Function;
use Laminas\Server\Reflection\Prototype;
use Laminas\Server\Reflection\ReflectionParameter;
/**
 * Describes how types, return values and method details are detected during
 * AutoDiscovery of a WSDL.
 */
interface Discovery_Strategy_Interface
{
    /**
     * Get the function parameters php type.
     *
     * Default implementation assumes the default param doc-block tag.
     *
     * @return string
     */
    public function get_function_parameter_type(ReflectionParameter $param);
    /**
     * Get the functions return php type.
     *
     * Default implementation assumes the value of the return doc-block tag.
     *
     * @return string
     */
    public function get_function_return_type(Abstract_Function $function, Prototype $prototype);
    /**
     * Detect if the function is a one-way or two-way operation.
     *
     * Default implementation assumes one-way, when return value is "void".
     *
     * @return bool
     */
    public function is_function_one_way(Abstract_Function $function, Prototype $prototype);
    /**
     * Detect the functions documentation.
     *
     * Default implementation uses docblock description.
     *
     * @return string
     */
    public function get_function_documentation(Abstract_Function $function);
}