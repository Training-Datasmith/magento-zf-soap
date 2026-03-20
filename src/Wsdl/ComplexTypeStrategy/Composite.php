<?php

declare (strict_types=1);
namespace Laminas\Soap\Wsdl\Complex_Type_Strategy;

use function class_exists;
use function is_string;
use Laminas\Soap\Exception;
use Laminas\Soap\Wsdl;
use Laminas\Soap\Wsdl\Complex_Type_Strategy\Complex_Type_Strategy_Interface as ComplexTypeStrategy;
use function sprintf;
class Composite implements Complex_Type_Strategy
{
    /**
     * Typemap of Complex Type => Strategy pairs.
     *
     * @var array
     */
    protected $type_map = [];
    /**
     * Context WSDL file that this composite serves
     *
     * @var Wsdl|null
     */
    protected $context;
    /**
     * Construct Composite WSDL Strategy.
     *
     * @param string|ComplexTypeStrategy $defaultStrategy
     */
    public function __construct(
        array $type_map = [],
        /**
         * Default Strategy of this composite
         */
        protected $default_strategy = Default_Complex_Type::class
    )
    {
        foreach ($type_map as $type => $strategy) {
            $this->connect_type_to_strategy($type, $strategy);
        }
    }
    /**
     * Connect a complex type to a given strategy.
     *
     * @param  string $type
     * @param  string|ComplexTypeStrategy $strategy
     * @throws Exception\InvalidArgumentException
     */
    public function connect_type_to_strategy($type, $strategy): static
    {
        if (!is_string($type)) {
            throw new Exception\InvalidArgumentException('Invalid type given to Composite Type Map.');
        }
        $this->type_map[$type] = $strategy;
        return $this;
    }
    /**
     * Return default strategy of this composite
     *
     * @return ComplexTypeStrategy
     * @throws Exception\InvalidArgumentException
     */
    public function get_default_strategy()
    {
        $strategy = $this->default_strategy;
        if (is_string($strategy) && class_exists($strategy)) {
            $strategy = new $strategy();
        }
        if (!$strategy instanceof Complex_Type_Strategy) {
            throw new Exception\InvalidArgumentException('Default Strategy for Complex Types is not a valid strategy object.');
        }
        $this->default_strategy = $strategy;
        return $strategy;
    }
    /**
     * Return specific strategy or the default strategy of this type.
     *
     * @param string $type
     * @return ComplexTypeStrategy
     * @throws Exception\InvalidArgumentException
     */
    public function get_strategy_of_type($type)
    {
        if (isset($this->type_map[$type])) {
            $strategy = $this->type_map[$type];
            if (is_string($strategy) && class_exists($strategy)) {
                $strategy = new $strategy();
            }
            if (!$strategy instanceof Complex_Type_Strategy) {
                throw new Exception\InvalidArgumentException(sprintf('Strategy for Complex Type "%s" is not a valid strategy object.', $type));
            }
            $this->type_map[$type] = $strategy;
        } else {
            $strategy = $this->get_default_strategy();
        }
        return $strategy;
    }
    /**
     * Method accepts the current WSDL context file.
     */
    public function set_context(Wsdl $context): static
    {
        $this->context = $context;
        return $this;
    }
    /**
     * Create a complex type based on a strategy
     *
     * @param  string $type
     * @return string XSD type
     * @throws Exception\InvalidArgumentException
     */
    public function add_complex_type($type)
    {
        if (!$this->context instanceof Wsdl) {
            throw new Exception\InvalidArgumentException(sprintf('Cannot add complex type "%s", no context is set for this composite strategy.', $type));
        }
        $strategy = $this->get_strategy_of_type($type);
        $strategy->set_context($this->context);
        return $strategy->add_complex_type($type);
    }
}