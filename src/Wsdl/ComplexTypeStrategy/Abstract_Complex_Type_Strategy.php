<?php

declare (strict_types=1);
namespace Laminas\Soap\Wsdl\Complex_Type_Strategy;

use function array_key_exists;
use Laminas\Soap\Wsdl;
use Laminas\Soap\Wsdl\Documentation_Strategy\Documentation_Strategy_Interface;
/**
 * Abstract class for Laminas\Soap\Wsdl\Strategy.
 */
abstract class Abstract_Complex_Type_Strategy implements Complex_Type_Strategy_Interface
{
    /**
     * Context object
     *
     * @var Wsdl
     */
    protected $context;
    /** @var DocumentationStrategyInterface */
    protected $documentation_strategy;
    /**
     * Set the WSDL Context object this strategy resides in.
     */
    public function set_context(Wsdl $context): void
    {
        $this->context = $context;
    }
    /**
     * Return the current WSDL context object
     *
     * @return Wsdl
     */
    public function get_context()
    {
        return $this->context;
    }
    /**
     * Look through registered types
     *
     * @param string $phpType
     * @return null|string
     */
    public function scan_registered_types($php_type)
    {
        if (array_key_exists($php_type, $this->get_context()->get_types())) {
            $soap_types = $this->get_context()->get_types();
            return $soap_types[$php_type];
        }
        return null;
    }
    /**
     * Sets the strategy for generating complex type documentation
     */
    public function set_documentation_strategy(Documentation_Strategy_Interface $documentation_strategy): void
    {
        $this->documentation_strategy = $documentation_strategy;
    }
}