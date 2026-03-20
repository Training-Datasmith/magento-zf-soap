<?php

declare (strict_types=1);
namespace Laminas\Soap\Wsdl\Documentation_Strategy;

use ReflectionClass;
use ReflectionProperty;
/**
 * Implement this interface to provide contents for <xsd:documentation> elements on complex types
 */
interface Documentation_Strategy_Interface
{
    /**
     * Returns documentation for complex type property
     *
     * @return string
     */
    public function get_property_documentation(ReflectionProperty $property);
    /**
     * Returns documentation for complex type
     *
     * @return string
     */
    public function get_complex_type_documentation(ReflectionClass $class);
}