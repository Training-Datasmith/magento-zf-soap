<?php

declare (strict_types=1);
namespace Laminas\Soap\Wsdl\Complex_Type_Strategy;

use function class_exists;
use Dom_Element;
use Laminas\Soap\Exception;
use Laminas\Soap\Wsdl;
use Laminas\Soap\Wsdl\Documentation_Strategy\Documentation_Strategy_Interface;
use function preg_match_all;
use ReflectionClass;
use ReflectionProperty;
use function sprintf;
use function trim;
class Default_Complex_Type extends Abstract_Complex_Type_Strategy
{
    /**
     * Add a complex type by recursively using all the class properties fetched via Reflection.
     *
     * @param string $type Name of the class to be specified
     * @return string XSD Type for the given PHP type
     * @throws Exception\InvalidArgumentException If class does not exist.
     */
    public function add_complex_type($type)
    {
        if (!class_exists($type)) {
            throw new Exception\InvalidArgumentException(sprintf('Cannot add a complex type %s that is not an object or where ' . 'class could not be found in "DefaultComplexType" strategy.', $type));
        }
        $class = new ReflectionClass($type);
        $php_type = $class->get_name();
        if (($soap_type = $this->scan_registered_types($php_type)) !== null) {
            return $soap_type;
        }
        $dom = $this->get_context()->to_dom_document();
        $soap_type_name = $this->get_context()->translate_type($php_type);
        $soap_type = Wsdl::TYPES_NS . ':' . $soap_type_name;
        // Register type here to avoid recursion
        $this->get_context()->add_type($php_type, $soap_type);
        $default_properties = $class->get_default_properties();
        $complex_type = $dom->create_element_ns(Wsdl::XSD_NS_URI, 'complexType');
        $complex_type->set_attribute('name', $soap_type_name);
        $all = $dom->create_element_ns(Wsdl::XSD_NS_URI, 'all');
        foreach ($class->get_properties() as $property) {
            if ($property->is_public() && preg_match_all('/@var\s+([^\s]+)/m', $property->get_doc_comment(), $matches)) {
                /**
                 * @todo check if 'xsd:element' must be used here (it may not be
                 * compatible with using 'complexType' node for describing other
                 * classes used as attribute types for current class
                 */
                $element = $dom->create_element_ns(Wsdl::XSD_NS_URI, 'element');
                $element->set_attribute('name', $property_name = $property->get_name());
                $element->set_attribute('type', $this->get_context()->get_type(trim($matches[1][0])));
                // If the default value is null, then this property is nillable.
                if ($default_properties[$property_name] === null) {
                    $element->set_attribute('nillable', 'true');
                }
                $this->add_property_documentation($property, $element);
                $all->append_child($element);
            }
        }
        $complex_type->append_child($all);
        $this->add_complex_type_documentation($class, $complex_type);
        $this->get_context()->get_schema()->append_child($complex_type);
        return $soap_type;
    }
    private function add_property_documentation(ReflectionProperty $property, Dom_Element $element): void
    {
        if ($this->documentation_strategy instanceof Documentation_Strategy_Interface) {
            $documentation = $this->documentation_strategy->get_property_documentation($property);
            if ($documentation) {
                $this->get_context()->add_documentation($element, $documentation);
            }
        }
    }
    private function add_complex_type_documentation(ReflectionClass $class, Dom_Element $element): void
    {
        if ($this->documentation_strategy instanceof Documentation_Strategy_Interface) {
            $documentation = $this->documentation_strategy->get_complex_type_documentation($class);
            if ($documentation) {
                $this->get_context()->add_documentation($element, $documentation);
            }
        }
    }
}