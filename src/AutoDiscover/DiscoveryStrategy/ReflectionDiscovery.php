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
class Reflection_Discovery implements Discovery_Strategy_Interface
{
    /**
     * Returns description from phpdoc block
     *
     * @return string
     */
    public function get_function_documentation(Abstract_Function $function)
    {
        return $function->get_description();
    }
    /**
     * Return parameter type
     *
     * @return string
     */
    public function get_function_parameter_type(ReflectionParameter $param)
    {
        return $param->get_type();
    }
    /**
     * Return function return type
     *
     * @return string
     */
    public function get_function_return_type(Abstract_Function $function, Prototype $prototype)
    {
        return $prototype->get_return_type();
    }
    /**
     * Return true if function is one way (return nothing)
     */
    public function is_function_one_way(Abstract_Function $function, Prototype $prototype): bool
    {
        return $prototype->get_return_type() === 'void';
    }
}