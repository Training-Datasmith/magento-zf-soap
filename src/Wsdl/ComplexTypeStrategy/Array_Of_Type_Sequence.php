<?php

declare (strict_types=1);
namespace Laminas\Soap\Wsdl\Complex_Type_Strategy;

use Laminas\Soap\Wsdl;
use function str_repeat;
use function str_replace;
use function strpos;
use function substr;
use function substr_count;
use function ucfirst;
class Array_Of_Type_Sequence extends Default_Complex_Type
{
    /**
     * Add an unbounded ArrayOfType based on the xsd:sequence syntax if
     * type[] is detected in return value doc comment.
     *
     * @param  string $type
     * @return string tns:xsd-type
     */
    public function add_complex_type($type)
    {
        $nested_counter = $this->get_nested_count($type);
        if ($nested_counter > 0) {
            $singular_type = $this->get_singular_type($type);
            $complex_type = '';
            for ($i = 1; $i <= $nested_counter; $i++) {
                $complex_type = $this->get_type_based_on_nesting_level($singular_type, $i);
                $complex_type_php = $singular_type . str_repeat('[]', $i);
                $child_type = $this->get_type_based_on_nesting_level($singular_type, $i - 1);
                $this->add_sequence_type($complex_type, $child_type, $complex_type_php);
            }
            return $complex_type;
        }
        if (($soap_type = $this->scan_registered_types($type)) !== null) {
            // Existing complex type
            return $soap_type;
        }
        // New singular complex type
        return parent::add_complex_type($type);
    }
    /**
     * Return the ArrayOf or simple type name based on the singular xsdtype
     * and the nesting level
     *
     * @param  string $singularType
     * @param  int    $level
     * @return string
     */
    protected function get_type_based_on_nesting_level($singular_type, $level)
    {
        if ($level === 0) {
            // This is not an Array anymore, return the xsd simple type
            return $this->get_context()->get_type($singular_type);
        }
        return Wsdl::TYPES_NS . ':' . str_repeat('ArrayOf', $level) . ucfirst($this->get_context()->translate_type($singular_type));
    }
    /**
     * From a nested definition with type[], get the singular xsd:type
     *
     * @param  string $type
     */
    protected function get_singular_type($type): string
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
    /**
     * Append the complex type definition to the WSDL via the context access
     *
     * @param  string $arrayType      Array type name (e.g. 'tns:ArrayOfArrayOfInt')
     * @param  string $childType      Qualified array items type (e.g. 'xsd:int', 'tns:ArrayOfInt')
     * @param  string $phpArrayType   PHP type (e.g. 'int[][]', '\MyNamespace\MyClassName[][][]')
     */
    protected function add_sequence_type($array_type, $child_type, $php_array_type)
    {
        if ($this->scan_registered_types($php_array_type) !== null) {
            return;
        }
        // Register type here to avoid recursion
        $this->get_context()->add_type($php_array_type, $array_type);
        $dom = $this->get_context()->to_dom_document();
        $array_type_name = substr($array_type, strpos($array_type, ':') + 1);
        $complex_type = $dom->create_element_ns(Wsdl::XSD_NS_URI, 'complexType');
        $this->get_context()->get_schema()->append_child($complex_type);
        $complex_type->set_attribute('name', $array_type_name);
        $sequence = $dom->create_element_ns(Wsdl::XSD_NS_URI, 'sequence');
        $complex_type->append_child($sequence);
        $element = $dom->create_element_ns(Wsdl::XSD_NS_URI, 'element');
        $sequence->append_child($element);
        $element->set_attribute('name', 'item');
        $element->set_attribute('type', $child_type);
        $element->set_attribute('minOccurs', 0);
        $element->set_attribute('maxOccurs', 'unbounded');
    }
}