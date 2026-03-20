<?php

declare (strict_types=1);
namespace Laminas\Soap\Wsdl\Complex_Type_Strategy;

use Laminas\Soap\Wsdl;
class Any_Type implements Complex_Type_Strategy_Interface
{
    /**
     * Not needed in this strategy.
     */
    public function set_context(Wsdl $context)
    {
    }
    /**
     * Returns xsd:anyType regardless of the input.
     *
     * @param  string $type
     */
    public function add_complex_type($type): string
    {
        return Wsdl::XSD_NS . ':anyType';
    }
}