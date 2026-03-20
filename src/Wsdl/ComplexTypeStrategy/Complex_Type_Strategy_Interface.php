<?php

declare (strict_types=1);
namespace Laminas\Soap\Wsdl\Complex_Type_Strategy;

use Laminas\Soap\Wsdl;
/**
 * Interface strategies that generate an XSD-Schema for complex data types in WSDL files.
 */
interface Complex_Type_Strategy_Interface
{
    /**
     * Method accepts the current WSDL context file.
     */
    public function set_context(Wsdl $context);
    /**
     * Create a complex type based on a strategy
     *
     * @param  string $type
     * @return string XSD type
     */
    public function add_complex_type($type);
}