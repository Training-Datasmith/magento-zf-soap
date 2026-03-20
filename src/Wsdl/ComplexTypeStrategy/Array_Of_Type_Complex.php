<?php

declare (strict_types=1);
namespace Laminas\Soap\Wsdl\Complex_Type_Strategy;

use Laminas\Soap\Exception;
use Laminas\Soap\Wsdl;
use function str_replace;
use function substr_count;
class Array_Of_Type_Complex extends Default_Complex_Type
{
    /**
     * Add an ArrayOfType based on the xsd:complexType syntax if type[] is
     * detected in return value doc comment.
     *
     * @param  string $type
     * @return string tns:xsd-type
     * @throws Exception\InvalidArgumentException
     */
    public function add_complex_type($type)
    {
        if (($soap_type = $this->scan_registered_types($type)) !== null) {
            return $soap_type;
        }
        $singular_type = $this->get_singular_php_type($type);
        $nesting_level = $this->get_nested_count($type);
        if ($nesting_level === 0) {
            return parent::add_complex_type($singular_type);
        }
        if ($nesting_level !== 1) {
            throw new Exception\InvalidArgumentException('ArrayOfTypeComplex cannot return nested ArrayOfObject deeper than one level. ' . 'Use array object properties to return deep nested data.');
        }
        // The following blocks define the Array of Object structure
        return $this->add_array_of_complex_type($singular_type, $type);
    }
    /**
     * Add an ArrayOfType based on the xsd:complexType syntax if type[] is
     * detected in return value doc comment.
     *
     * @param  string $singularType   e.g. '\MyNamespace\MyClassname'
     * @param  string $type           e.g. '\MyNamespace\MyClassname[]'
     * @return string tns:xsd-type   e.g. 'tns:ArrayOfMyNamespace.MyClassname'
     */
    protected function add_array_of_complex_type($singular_type, $type)
    {
        if (($soap_type = $this->scan_registered_types($type)) !== null) {
            return $soap_type;
        }
        $xsd_complex_type_name = 'ArrayOf' . $this->get_context()->translate_type($singular_type);
        $xsd_complex_type = Wsdl::TYPES_NS . ':' . $xsd_complex_type_name;
        // Register type here to avoid recursion
        $this->get_context()->add_type($type, $xsd_complex_type);
        // Process singular type using DefaultComplexType strategy
        parent::add_complex_type($singular_type);
        // Add array type structure to WSDL document
        $dom = $this->get_context()->to_dom_document();
        $complex_type = $dom->create_element_ns(Wsdl::XSD_NS_URI, 'complexType');
        $this->get_context()->get_schema()->append_child($complex_type);
        $complex_type->set_attribute('name', $xsd_complex_type_name);
        $complex_content = $dom->create_element_ns(Wsdl::XSD_NS_URI, 'complexContent');
        $complex_type->append_child($complex_content);
        $xsd_restriction = $dom->create_element_ns(Wsdl::XSD_NS_URI, 'restriction');
        $complex_content->append_child($xsd_restriction);
        $xsd_restriction->set_attribute('base', Wsdl::SOAP_ENC_NS . ':Array');
        $xsd_attribute = $dom->create_element_ns(Wsdl::XSD_NS_URI, 'attribute');
        $xsd_restriction->append_child($xsd_attribute);
        $xsd_attribute->set_attribute('ref', Wsdl::SOAP_ENC_NS . ':arrayType');
        $xsd_attribute->set_attribute_ns(Wsdl::WSDL_NS_URI, 'arrayType', Wsdl::TYPES_NS . ':' . $this->get_context()->translate_type($singular_type) . '[]');
        return $xsd_complex_type;
    }
    /**
     * From a nested definition with type[], get the singular PHP Type
     *
     * @param  string $type
     */
    protected function get_singular_php_type($type): string
    {
        return str_replace('[]', '', $type);
    }
    /**
     * Return the array nesting level based on the type name
     *
     * @param  string $type
     */
    protected function get_nested_count($type): int
    {
        return substr_count($type, '[]');
    }
}