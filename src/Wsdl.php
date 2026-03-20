<?php

declare (strict_types=1);
namespace Laminas\Soap;

use function count;
use Dom_Document;
use Dom_Document_Fragment;
use Dom_Element;
use Dom_Node;
use Domx_Path;
use const ENT_QUOTES;
use function file_put_contents;
use function htmlspecialchars;
use function in_array;
use function is_array;
use function is_string;
use Laminas\Soap\Wsdl\Complex_Type_Strategy\Complex_Type_Strategy_Interface as ComplexTypeStrategy;
use Laminas\Uri\Uri;
use const SOAP_1_1;
use const SOAP_1_2;
use function str_replace;
use function strlen;
use function strrpos;
use function strtolower;
use function substr;
use function trim;
class Wsdl
{
    /**#@+
     * XML Namespace uris and prefixes.
     */
    public const XML_NS = 'xmlns';
    public const XML_NS_URI = 'http://www.w3.org/2000/xmlns/';
    public const WSDL_NS = 'wsdl';
    public const WSDL_NS_URI = 'http://schemas.xmlsoap.org/wsdl/';
    public const SOAP_11_NS = 'soap';
    public const SOAP_11_NS_URI = 'http://schemas.xmlsoap.org/wsdl/soap/';
    public const SOAP_12_NS = 'soap12';
    public const SOAP_12_NS_URI = 'http://schemas.xmlsoap.org/wsdl/soap12/';
    public const SOAP_ENC_NS = 'soap-enc';
    public const SOAP_ENC_URI = 'http://schemas.xmlsoap.org/soap/encoding/';
    public const XSD_NS = 'xsd';
    public const XSD_NS_URI = 'http://www.w3.org/2001/XMLSchema';
    public const TYPES_NS = 'tns';
    /**
     * DOM Instance
     *
     * @var DOMDocument
     */
    protected $dom;
    /**
     * Types defined on schema
     *
     * @var array
     */
    protected $included_types = [];
    /** @var DOMElement */
    protected $schema;
    /**
     * Strategy for detection of complex types
     *
     * @var null|ComplexTypeStrategy
     */
    protected $strategy;
    /**
     * URI where the WSDL will be available
     *
     * @var string
     */
    protected $uri;
    /**
     * Root XML_Tree_Node
     *
     * @var DOMElement WSDL
     */
    protected $wsdl;
    /**
     * @param string  $name Name of the Web Service being Described
     * @param string|Uri $uri URI where the WSDL will be available
     * @param null|ComplexTypeStrategy $strategy Strategy for detection of complex types
     * @param null|array $classMap Map of PHP Class names to WSDL QNames
     * @throws Exception\RuntimeException
     */
    public function __construct($name, $uri, ?Complex_Type_Strategy $strategy = null, protected array $class_map = [])
    {
        if ($uri instanceof Uri) {
            $uri = $uri->to_string();
        }
        $this->set_uri($uri);
        $this->dom = $this->get_dom_document($name, $this->get_uri());
        $this->wsdl = $this->dom->document_element;
        $this->set_complex_type_strategy($strategy ?: new Wsdl\Complex_Type_Strategy\Default_Complex_Type());
    }
    /**
     * Get the wsdl XML document with all namespaces and required attributes
     *
     * @param string $uri
     * @param string $name
     */
    protected function get_dom_document($name, $uri = null): \Dom_Document
    {
        $dom = new Dom_Document();
        // @todo new option for debug mode ?
        $dom->preserve_white_space = false;
        $dom->format_output = false;
        $dom->resolve_externals = false;
        $dom->encoding = 'UTF-8';
        $dom->substitute_entities = false;
        $definitions = $dom->create_element_ns(self::WSDL_NS_URI, 'definitions');
        $dom->append_child($definitions);
        $uri = $this->sanitize_uri($uri);
        $this->set_attribute_with_sanitization($definitions, 'name', $name);
        $this->set_attribute_with_sanitization($definitions, 'targetNamespace', $uri);
        $definitions->set_attribute_ns(self::XML_NS_URI, 'xmlns:' . self::WSDL_NS, self::WSDL_NS_URI);
        $definitions->set_attribute_ns(self::XML_NS_URI, 'xmlns:' . self::TYPES_NS, $uri);
        $definitions->set_attribute_ns(self::XML_NS_URI, 'xmlns:' . self::SOAP_11_NS, self::SOAP_11_NS_URI);
        $definitions->set_attribute_ns(self::XML_NS_URI, 'xmlns:' . self::XSD_NS, self::XSD_NS_URI);
        $definitions->set_attribute_ns(self::XML_NS_URI, 'xmlns:' . self::SOAP_ENC_NS, self::SOAP_ENC_URI);
        $definitions->set_attribute_ns(self::XML_NS_URI, 'xmlns:' . self::SOAP_12_NS, self::SOAP_12_NS_URI);
        return $dom;
    }
    /**
     * Retrieve target namespace of the WSDL document.
     *
     * @return string
     */
    public function get_target_namespace(): ?string
    {
        if ($this->wsdl !== null) {
            return $this->wsdl->get_attribute('targetNamespace');
        }
        return null;
    }
    /**
     * Get the class map of php to wsdl mappings..
     *
     * @return array
     */
    public function get_class_map()
    {
        return $this->class_map;
    }
    /**
     * Set the class map of php to wsdl mappings..
     */
    public function set_class_map(array $class_map): static
    {
        $this->class_map = $class_map;
        return $this;
    }
    /**
     * Set a new uri for this WSDL
     *
     * @param string|Uri $uri
     */
    public function set_uri($uri): static
    {
        if ($uri instanceof Uri) {
            $uri = $uri->to_string();
        }
        $uri = $this->sanitize_uri($uri);
        $old_uri = $this->uri;
        $this->uri = $uri;
        // namespace declarations are NOT true attributes so one must
        // explicitly set on root element xmlns:tns = $uri
        $this->dom->document_element->set_attribute_ns(self::XML_NS_URI, self::XML_NS . ':' . self::TYPES_NS, $uri);
        $xpath = new Domx_Path($this->dom);
        $xpath->register_namespace('default', self::WSDL_NS_URI);
        $xpath->register_namespace(self::TYPES_NS, $uri);
        $xpath->register_namespace(self::SOAP_11_NS, self::SOAP_11_NS_URI);
        $xpath->register_namespace(self::SOAP_12_NS, self::SOAP_12_NS_URI);
        $xpath->register_namespace(self::XSD_NS, self::XSD_NS_URI);
        $xpath->register_namespace(self::SOAP_ENC_NS, self::SOAP_ENC_URI);
        $xpath->register_namespace(self::WSDL_NS, self::WSDL_NS_URI);
        // Select only attribute nodes. Data nodes does not contain uri
        // except for documentation node but this is for the user to decide.
        // This list does not include xmlns:tsn attribute of document root.
        // That attribute is changed above.
        $attribute_nodes = $xpath->query('//attribute::*[contains(., "' . $old_uri . '")]');
        foreach ($attribute_nodes as $node) {
            $attribute_value = $this->dom->create_text_node(str_replace($old_uri, $uri, $node->node_value));
            $node->replace_child($attribute_value, $node->child_nodes->item(0));
        }
        return $this;
    }
    /**
     * Return WSDL uri
     *
     * @return string
     */
    public function get_uri()
    {
        return $this->uri;
    }
    /**
     * Function for sanitizing uri
     *
     * @param string|Uri $uri
     * @throws Exception\InvalidArgumentException
     */
    public function sanitize_uri($uri): string
    {
        if ($uri instanceof Uri) {
            $uri = $uri->to_string();
        }
        $uri = trim($uri);
        $uri = htmlspecialchars($uri, ENT_QUOTES, 'UTF-8', false);
        if (empty($uri)) {
            throw new Exception\InvalidArgumentException('Uri contains invalid characters or is empty');
        }
        return $uri;
    }
    /**
     * Set a strategy for complex type detection and handling
     */
    public function set_complex_type_strategy(Complex_Type_Strategy $strategy): static
    {
        $this->strategy = $strategy;
        return $this;
    }
    /**
     * Get the current complex type strategy
     *
     * @return ComplexTypeStrategy
     */
    public function get_complex_type_strategy()
    {
        return $this->strategy;
    }
    /**
     * Add a {@link http://www.w3.org/TR/wsdl#_messages message} element to the WSDL
     *
     * @param  string $messageName Name for the {@link http://www.w3.org/TR/wsdl#_messages message}
     * @param  array $parts An array of {@link http://www.w3.org/TR/wsdl#_message parts}
     *     The array is constructed like:
     *     - 'name of part' => 'part xml schema data type' or
     *     - 'name of part' => array('type' => 'part xml schema type')  or
     *     - 'name of part' => array('element' => 'part xml element name')
     * @return DOMElement The new message's XML_Tree_Node for use in {@link function addDocumentation}
     */
    public function add_message($message_name, $parts)
    {
        $message = $this->dom->create_element_ns(self::WSDL_NS_URI, 'message');
        $message->set_attribute('name', $message_name);
        if (count($parts) > 0) {
            foreach ($parts as $name => $type) {
                $part = $this->dom->create_element_ns(self::WSDL_NS_URI, 'part');
                $message->append_child($part);
                $part->set_attribute('name', $name);
                if (is_array($type)) {
                    $this->array_to_attributes($part, $type);
                } else {
                    $this->set_attribute_with_sanitization($part, 'type', $type);
                }
            }
        }
        $this->wsdl->append_child($message);
        return $message;
    }
    /**
     * Add a {@link http://www.w3.org/TR/wsdl#_porttypes portType} element to the WSDL
     *
     * @param string $name portType element's name
     * @return DOMElement The new portType's XML_Tree_Node for use in
     *     {@link addPortOperation} and {@link addDocumentation}
     */
    public function add_port_type($name)
    {
        $port_type = $this->dom->create_element_ns(self::WSDL_NS_URI, 'portType');
        $this->wsdl->append_child($port_type);
        $port_type->set_attribute('name', $name);
        return $port_type;
    }
    /**
     * Add an {@link http://www.w3.org/TR/wsdl#request-response operation} element to a portType element
     *
     * @param DOMElement $portType a portType XML_Tree_Node, from {@link function addPortType}
     * @param string      $name     Operation name
     * @param bool|string $input    Input Message
     * @param bool|string $output   Output Message
     * @param bool|string $fault    Fault Message
     * @return DOMElement The new operation's XML_Tree_Node for use in {@link function addDocumentation}
     */
    public function add_port_operation($port_type, $name, $input = false, $output = false, $fault = false)
    {
        $operation = $this->dom->create_element_ns(self::WSDL_NS_URI, 'operation');
        $port_type->append_child($operation);
        $operation->set_attribute('name', $name);
        if (is_string($input) && strlen(trim($input)) >= 1) {
            $node = $this->dom->create_element_ns(self::WSDL_NS_URI, 'input');
            $operation->append_child($node);
            $node->set_attribute('message', $input);
        }
        if (is_string($output) && strlen(trim($output)) >= 1) {
            $node = $this->dom->create_element_ns(self::WSDL_NS_URI, 'output');
            $operation->append_child($node);
            $node->set_attribute('message', $output);
        }
        if (is_string($fault) && strlen(trim($fault)) >= 1) {
            $node = $this->dom->create_element_ns(self::WSDL_NS_URI, 'fault');
            $operation->append_child($node);
            $node->set_attribute('message', $fault);
        }
        return $operation;
    }
    /**
     * Add a {@link http://www.w3.org/TR/wsdl#_bindings binding} element to WSDL
     *
     * @param  string $name Name of the Binding
     * @param  string $portType name of the portType to bind
     * @return DOMElement The new binding's XML_Tree_Node for use with
     *     {@link function addBindingOperation} and {@link function addDocumentation}
     */
    public function add_binding($name, $port_type)
    {
        $binding = $this->dom->create_element_ns(self::WSDL_NS_URI, 'binding');
        $this->wsdl->append_child($binding);
        $this->set_attribute($binding, 'name', $name);
        $this->set_attribute($binding, 'type', $port_type);
        return $binding;
    }
    /**
     * Add an operation to a binding element
     *
     * @param DOMElement $binding A binding XML_Tree_Node returned by
     *     {@link function addBinding}
     * @param string $name
     * @param array|bool $input  An array of attributes for the input element,
     *     allowed keys are: 'use', 'namespace', 'encodingStyle'.
     *     {@link http://www.w3.org/TR/wsdl#_soap:body More Information}
     * @param array|bool $output An array of attributes for the output element,
     *     allowed keys are: 'use', 'namespace', 'encodingStyle'.
     *     {@link http://www.w3.org/TR/wsdl#_soap:body More Information}
     * @param array|bool $fault  An array with attributes for the fault element,
     *     allowed keys are: 'name', 'use', 'namespace', 'encodingStyle'.
     *     {@link http://www.w3.org/TR/wsdl#_soap:body More Information}
     * @param int $soapVersion SOAP version: SOAP_1_1 or SOAP_1_2, default: SOAP_1_1
     * @return DOMElement The new Operation's XML_Tree_Node for use with {@link
     *     function addSoapOperation} and {@link function addDocumentation}
     */
    public function add_binding_operation($binding, $name, $input = false, $output = false, $fault = false, $soap_version = SOAP_1_1)
    {
        $operation = $this->dom->create_element_ns(self::WSDL_NS_URI, 'operation');
        $binding->append_child($operation);
        $this->set_attribute($operation, 'name', $name);
        if (is_array($input) && !empty($input)) {
            $node = $this->dom->create_element_ns(self::WSDL_NS_URI, 'input');
            $operation->append_child($node);
            $soap_node = $this->dom->create_element_ns($this->get_soap_namespace_uri_by_version($soap_version), 'body');
            $node->append_child($soap_node);
            $this->array_to_attributes($soap_node, $input);
        }
        if (is_array($output) && !empty($output)) {
            $node = $this->dom->create_element_ns(self::WSDL_NS_URI, 'output');
            $operation->append_child($node);
            $soap_node = $this->dom->create_element_ns($this->get_soap_namespace_uri_by_version($soap_version), 'body');
            $node->append_child($soap_node);
            $this->array_to_attributes($soap_node, $output);
        }
        if (is_array($fault) && !empty($fault)) {
            $node = $this->dom->create_element_ns(self::WSDL_NS_URI, 'fault');
            $operation->append_child($node);
            $this->array_to_attributes($node, $fault);
        }
        return $operation;
    }
    /**
     * Add a {@link http://www.w3.org/TR/wsdl#_soap:binding SOAP binding} element to a Binding element
     *
     * @param DOMElement $binding A binding XML_Tree_Node returned by {@link function addBinding}
     * @param string $style binding style, possible values are "rpc" (the default) and "document"
     * @param string $transport Transport method (defaults to HTTP)
     * @param int $soapVersion SOAP version: SOAP_1_1 or SOAP_1_2, default: SOAP_1_1
     * @return DOMElement
     */
    public function add_soap_binding($binding, $style = 'document', $transport = 'http://schemas.xmlsoap.org/soap/http', $soap_version = SOAP_1_1)
    {
        $soap_binding = $this->dom->create_element_ns($this->get_soap_namespace_uri_by_version($soap_version), 'binding');
        $binding->append_child($soap_binding);
        $soap_binding->set_attribute('style', $style);
        $soap_binding->set_attribute('transport', $transport);
        return $soap_binding;
    }
    /**
     * Add a {@link http://www.w3.org/TR/wsdl#_soap:operation SOAP operation} to an operation element
     *
     * @param DOMElement $operation An operation XML_Tree_Node returned by {@link function addBindingOperation}
     * @param string $soapAction SOAP Action
     * @param int $soapVersion SOAP version: SOAP_1_1 or SOAP_1_2, default: SOAP_1_1
     * @return DOMElement
     */
    public function add_soap_operation($operation, $soap_action, $soap_version = SOAP_1_1)
    {
        if ($soap_action instanceof Uri) {
            $soap_action = $soap_action->to_string();
        }
        $soap_operation = $this->dom->create_element_ns($this->get_soap_namespace_uri_by_version($soap_version), 'operation');
        $operation->insert_before($soap_operation, $operation->first_child);
        $this->set_attribute_with_sanitization($soap_operation, 'soapAction', $soap_action);
        return $soap_operation;
    }
    /**
     * Add a {@link http://www.w3.org/TR/wsdl#_services service} element to the WSDL
     *
     * @param string $name Service Name
     * @param string $portName Name of the port for the service
     * @param string $binding Binding for the port
     * @param string $location SOAP Address for the service
     * @param int $soapVersion SOAP version: SOAP_1_1 or SOAP_1_2, default: SOAP_1_1
     * @return DOMElement The new service's XML_Tree_Node for use with {@link function addDocumentation}
     */
    public function add_service($name, $port_name, $binding, $location, $soap_version = SOAP_1_1)
    {
        if ($location instanceof Uri) {
            $location = $location->to_string();
        }
        $service = $this->dom->create_element_ns(self::WSDL_NS_URI, 'service');
        $this->wsdl->append_child($service);
        $service->set_attribute('name', $name);
        $port = $this->dom->create_element_ns(self::WSDL_NS_URI, 'port');
        $service->append_child($port);
        $port->set_attribute('name', $port_name);
        $port->set_attribute('binding', $binding);
        $soap_address = $this->dom->create_element_ns($this->get_soap_namespace_uri_by_version($soap_version), 'address');
        $port->append_child($soap_address);
        $this->set_attribute_with_sanitization($soap_address, 'location', $location);
        return $service;
    }
    /**
     * Add a documentation element to any element in the WSDL.
     *
     * Note that the WSDL specification uses 'document', but the WSDL schema
     * uses 'documentation' instead.
     *
     * The WS-I Basic Profile 1.1 recommends using 'documentation'.
     *
     * @see http://www.w3.org/TR/wsdl#_documentation WSDL specification
     * @see http://schemas.xmlsoap.org/wsdl/ WSDL schema
     * @see http://www.ws-i.org/Profiles/BasicProfile-1.1-2004-08-24.html#WSDL_documentation_Element WS-I Basic
     *     Profile 1.1
     *
     * @param DOMElement $inputNode An XML_Tree_Node returned by another
     *     method to add the documentation to
     * @param string $documentation Human readable documentation for the node
     * @return DOMElement The documentation element
     */
    public function add_documentation($input_node, $documentation)
    {
        if ($input_node === $this) {
            $node = $this->dom->document_element;
        } else {
            $node = $input_node;
        }
        if ($node->namespace_uri === self::XSD_NS_URI) {
            // complex types require annotation element for documentation
            $doc = $this->dom->create_element_ns(self::XSD_NS_URI, 'documentation');
            $child = $this->dom->create_element_ns(self::XSD_NS_URI, 'annotation');
            $child->append_child($doc);
        } else {
            $doc = $child = $this->dom->create_element_ns(self::WSDL_NS_URI, 'documentation');
        }
        if ($node->has_child_nodes()) {
            $node->insert_before($child, $node->first_child);
        } else {
            $node->append_child($child);
        }
        // phpcs:disable WebimpressCodingStandard.NamingConventions.ValidVariableName.NotCamelCaps
        $doc_c_data = $this->dom->create_text_node(str_replace(["\r\n", "\r"], "\n", $documentation));
        $doc->append_child($doc_c_data);
        // phpcs:enable WebimpressCodingStandard.NamingConventions.ValidVariableName.NotCamelCaps
        return $doc;
    }
    /**
     * Add WSDL Types element
     *
     * @param DOMDocument|DOMNode|DOMElement|DOMDocumentFragment $types A
     *     DOMDocument|DOMNode|DOMElement|DOMDocumentFragment with all the XML
     *     Schema types defined in it
     */
    public function add_types(Dom_Node $types): void
    {
        if ($types instanceof Dom_Document) {
            $dom = $this->dom->import_node($types->document_element);
            $this->wsdl->append_child($dom);
        } elseif ($types instanceof Dom_Node || $types instanceof Dom_Element || $types instanceof Dom_Document_Fragment) {
            $dom = $this->dom->import_node($types);
            $this->wsdl->append_child($dom);
        }
    }
    /**
     * Add a complex type name that is part of this WSDL and can be used in signatures.
     *
     * @param string $type
     * @param string $wsdlType
     */
    public function add_type($type, $wsdl_type): static
    {
        if (!isset($this->included_types[$type])) {
            $this->included_types[$type] = $wsdl_type;
        }
        return $this;
    }
    /**
     * Return an array of all currently included complex types
     *
     * @return array
     */
    public function get_types()
    {
        return $this->included_types;
    }
    /**
     * Return the Schema node of the WSDL
     *
     * @return DOMElement
     */
    public function get_schema()
    {
        if ($this->schema === null) {
            $this->add_schema_type_section();
        }
        return $this->schema;
    }
    /**
     * Return the WSDL as XML
     *
     * @return string WSDL as XML
     */
    public function to_xml(): string|false
    {
        $this->dom->normalize_document();
        return $this->dom->save_xml();
    }
    /**
     * Return DOM Document
     *
     * @return DOMDocument
     */
    public function to_dom_document()
    {
        $this->dom->normalize_document();
        return $this->dom;
    }
    /**
     * Echo the WSDL as XML
     *
     * @param bool $filename
     * @return bool
     */
    public function dump($filename = false)
    {
        $this->dom->normalize_document();
        if (!$filename) {
            echo $this->to_xml();
            return true;
        }
        return (bool) file_put_contents($filename, $this->to_xml());
    }
    /**
     * Returns an XSD Type for the given PHP type
     *
     * @param string $type PHP Type to get the XSD type for
     * @return string
     */
    public function get_type($type)
    {
        return match (strtolower($type)) {
            'string', 'str' => self::XSD_NS . ':string',
            'long' => self::XSD_NS . ':long',
            'int', 'integer' => self::XSD_NS . ':int',
            'float' => self::XSD_NS . ':float',
            'double' => self::XSD_NS . ':double',
            'boolean', 'bool' => self::XSD_NS . ':boolean',
            'array' => self::SOAP_ENC_NS . ':Array',
            'object' => self::XSD_NS . ':struct',
            'mixed' => self::XSD_NS . ':anyType',
            'date' => self::XSD_NS . ':date',
            'datetime' => self::XSD_NS . ':dateTime',
            'void' => '',
            // delegate retrieval of complex type to current strategy
            default => $this->add_complex_type($type),
        };
    }
    /**
     * This function makes sure a complex types section and schema additions are set.
     */
    public function add_schema_type_section(): static
    {
        if ($this->schema === null) {
            $types = $this->dom->create_element_ns(self::WSDL_NS_URI, 'types');
            $this->wsdl->append_child($types);
            $this->schema = $this->dom->create_element_ns(self::XSD_NS_URI, 'schema');
            $types->append_child($this->schema);
            $this->set_attribute_with_sanitization($this->schema, 'targetNamespace', $this->get_uri());
        }
        return $this;
    }
    /**
     * Translate PHP type into WSDL QName
     *
     * @param string $type
     * @return string QName
     */
    public function translate_type($type)
    {
        if (isset($this->class_map[$type])) {
            return $this->class_map[$type];
        }
        $type = trim($type, '\\');
        // remove namespace,
        $pos = strrpos($type, '\\');
        if ($pos) {
            return substr($type, $pos + 1);
        }
        return $type;
    }
    /**
     * Add a {@link http://www.w3.org/TR/wsdl#_types types} data type definition
     *
     * @param string $type Name of the class to be specified
     * @return string XSD Type for the given PHP type
     */
    public function add_complex_type($type)
    {
        if (isset($this->included_types[$type])) {
            return $this->included_types[$type];
        }
        $this->add_schema_type_section();
        $strategy = $this->get_complex_type_strategy();
        $strategy->set_context($this);
        // delegates the detection of a complex type to the current strategy
        return $strategy->add_complex_type($type);
    }
    /**
     * Parse an xsd:element represented as an array into a DOMElement.
     *
     * @param array $element an xsd:element represented as an array
     * @return DOMElement parsed element
     * @throws Exception\RuntimeException If $element is not an array.
     */
    protected function parse_element($element)
    {
        if (!is_array($element)) {
            throw new Exception\RuntimeException('The "element" parameter needs to be an associative array.');
        }
        // phpcs:disable WebimpressCodingStandard.NamingConventions.ValidVariableName.NotCamelCaps
        $element_xml = $this->dom->create_element_ns(self::XSD_NS_URI, 'element');
        foreach ($element as $key => $value) {
            if (in_array($key, ['sequence', 'all', 'choice'])) {
                if (is_array($value)) {
                    $complex_type = $this->dom->create_element_ns(self::XSD_NS_URI, 'complexType');
                    if (count($value) > 0) {
                        $container = $this->dom->create_element_ns(self::XSD_NS_URI, $key);
                        foreach ($value as $sub_element) {
                            $sub_element_xml = $this->parse_element($sub_element);
                            $container->append_child($sub_element_xml);
                        }
                        $complex_type->append_child($container);
                    }
                    $element_xml->append_child($complex_type);
                }
            } else {
                $element_xml->set_attribute($key, $value);
            }
        }
        return $element_xml;
        // phpcs:enable WebimpressCodingStandard.NamingConventions.ValidVariableName.NotCamelCaps
    }
    /**
     * Prepare attribute value for specific attributes
     *
     * @param string $name
     * @param mixed $value
     * @return string safe value or original $value
     */
    protected function sanitize_attribute_value_by_name($name, $value)
    {
        return match (strtolower($name)) {
            'targetnamespace', 'encodingstyle', 'soapaction', 'location' => $this->sanitize_uri($value),
            default => $value,
        };
    }
    /**
     * Convert associative array to attributes of given node
     *
     * Optionally uses {@link function sanitizeAttributeValueByName}.
     *
     * @param bool $withSanitizer
     * @return void
     */
    protected function array_to_attributes(Dom_Node $node, array $attributes, $with_sanitizer = true)
    {
        foreach ($attributes as $attribute_name => $attribute_value) {
            if ($with_sanitizer) {
                $this->set_attribute_with_sanitization($node, $attribute_name, $attribute_value);
            } else {
                $this->set_attribute($node, $attribute_name, $attribute_value);
            }
        }
    }
    /**
     * Set attribute to given node using {@link function sanitizeAttributeValueByName}
     *
     * @param string $attributeName
     * @param mixed $attributeValue
     * @return void
     */
    protected function set_attribute_with_sanitization(Dom_Node $node, $attribute_name, $attribute_value)
    {
        $attribute_value = $this->sanitize_attribute_value_by_name($attribute_name, $attribute_value);
        $this->set_attribute($node, $attribute_name, $attribute_value);
    }
    /**
     * Set attribute to given node
     *
     * @param string $attributeName
     * @param mixed $attributeValue
     * @return void
     */
    protected function set_attribute(Dom_Node $node, $attribute_name, $attribute_value)
    {
        $attribute_node = $node->owner_document->create_attribute($attribute_name);
        $node->append_child($attribute_node);
        $attribute_node_value = $node->owner_document->create_text_node($attribute_value);
        $attribute_node->append_child($attribute_node_value);
    }
    /**
     * Return soap namespace uri according to $soapVersion
     *
     * @param int $soapVersion SOAP_1_1 or SOAP_1_2 constants
     * @throws Exception\InvalidArgumentException
     */
    protected function get_soap_namespace_uri_by_version($soap_version): string
    {
        if ($soap_version !== SOAP_1_1 && $soap_version !== SOAP_1_2) {
            throw new Exception\InvalidArgumentException('Invalid SOAP version, use constants: SOAP_1_1 or SOAP_1_2');
        }
        if ($soap_version === SOAP_1_1) {
            return self::SOAP_11_NS_URI;
        }
        return self::SOAP_12_NS_URI;
    }
    /**
     * Add an xsd:element represented as an array to the schema.
     *
     * Array keys represent attribute names and values their respective value.
     * The 'sequence', 'all' and 'choice' keys must have an array of elements as their value,
     * to add them to a nested complexType.
     *
     * Example:
     *
     * <code>
     * array(
     *     'name' => 'MyElement',
     *     'sequence' => array(
     *         array('name' => 'myString', 'type' => 'string'),
     *         array('name' => 'myInteger', 'type' => 'int')
     *     )
     * );
     * </code>
     *
     * Resulting XML:
     *
     * <code>
     * <xsd:element name="MyElement">
     *   <xsd:complexType><xsd:sequence>
     *     <xsd:element name="myString" type="string"/>
     *     <xsd:element name="myInteger" type="int"/>
     *   </xsd:sequence></xsd:complexType>
     * </xsd:element>
     * </code>
     *
     * @param array $element an xsd:element represented as an array
     * @return string xsd:element for the given element array
     */
    public function add_element(array $element): string
    {
        $schema = $this->get_schema();
        $element_xml = $this->parse_element($element);
        $schema->append_child($element_xml);
        return self::TYPES_NS . ':' . $element['name'];
    }
}