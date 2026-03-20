<?php

declare (strict_types=1);
namespace Laminas\Soap;

use function array_unique;
use function count;
use Dom_Element;
use const ENT_QUOTES;
use function function_exists;
use function header;
use function htmlspecialchars;
use function is_array;
use function is_string;
use function is_subclass_of;
use Laminas\Server\Reflection;
use Laminas\Soap\Auto_Discover\Discovery_Strategy\Discovery_Strategy_Interface as DiscoveryStrategy;
use Laminas\Soap\Auto_Discover\Discovery_Strategy\Reflection_Discovery;
use Laminas\Soap\Wsdl\Complex_Type_Strategy\Complex_Type_Strategy_Interface as ComplexTypeStrategy;
use Laminas\Uri;
use function preg_match;
use function sprintf;
use function strlen;
use function trim;
class Auto_Discover
{
    /** @var string */
    protected $service_name;
    /** @var Reflection */
    protected $reflection;
    /**
     * Service function names
     *
     * @var array
     */
    protected $functions = [];
    /**
     * Service class name
     *
     * @var string
     */
    protected $class;
    /** @var bool */
    protected $strategy;
    /**
     * Url where the WSDL file will be available at.
     *
     * @var Wsdl Uri
     */
    protected $uri;
    /**
     * soap:body operation style options
     *
     * @var array
     */
    protected $operation_body_style = ['use' => 'encoded', 'encodingStyle' => 'http://schemas.xmlsoap.org/soap/encoding/'];
    /**
     * soap:operation style
     *
     * @var array
     */
    protected $binding_style = ['style' => 'rpc', 'transport' => 'http://schemas.xmlsoap.org/soap/http'];
    /**
     * Name of the class to handle the WSDL creation.
     *
     * @var string
     */
    protected $wsdl_class = Wsdl::class;
    /**
     * Class Map of PHP to WSDL types.
     *
     * @var array
     */
    protected $class_map = [];
    /**
     * Discovery strategy for types and other method details.
     *
     * @var DiscoveryStrategy
     */
    protected $discovery_strategy;
    /**
     * Constructor
     *
     * @param null|string|Uri\Uri $endpointUri
     * @param null|string $wsdlClass
     * @param null|array $classMap
     */
    public function __construct(?Complex_Type_Strategy $strategy = null, $endpoint_uri = null, $wsdl_class = null, array $class_map = [])
    {
        $this->reflection = new Reflection();
        $this->set_discovery_strategy(new Reflection_Discovery());
        if (null !== $strategy) {
            $this->set_complex_type_strategy($strategy);
        }
        if (null !== $endpoint_uri) {
            $this->set_uri($endpoint_uri);
        }
        if (null !== $wsdl_class) {
            $this->set_wsdl_class($wsdl_class);
        }
        $this->set_class_map($class_map);
    }
    /**
     * Set the discovery strategy for method type and other information.
     */
    public function set_discovery_strategy(Discovery_Strategy $discovery_strategy): static
    {
        $this->discovery_strategy = $discovery_strategy;
        return $this;
    }
    /**
     * Get the discovery strategy.
     *
     * @return DiscoveryStrategy
     */
    public function get_discovery_strategy()
    {
        return $this->discovery_strategy;
    }
    /**
     * Get the class map of php to wsdl mappings.
     *
     * @return array
     */
    public function get_class_map()
    {
        return $this->class_map;
    }
    /**
     * Set the class map of php to wsdl mappings.
     *
     * @param  array $classMap
     * @throws Exception\InvalidArgumentException
     */
    public function set_class_map($class_map): static
    {
        if (!is_array($class_map)) {
            throw new Exception\InvalidArgumentException(sprintf('%s expects an array; received "%s"', __METHOD__, get_debug_type($class_map)));
        }
        $this->class_map = $class_map;
        return $this;
    }
    /**
     * Set service name
     *
     * @param string $serviceName
     * @throws Exception\InvalidArgumentException
     */
    public function set_service_name($service_name): static
    {
        $matches = [];
        // first character must be letter or underscore {@see http://www.w3.org/TR/wsdl#_document-n}
        $i = preg_match('/^[a-z\_]/ims', $service_name, $matches);
        if ($i !== 1) {
            throw new Exception\InvalidArgumentException('Service Name must start with letter or _');
        }
        $this->service_name = $service_name;
        return $this;
    }
    /**
     * Get service name
     *
     * @return string
     * @throws Exception\RuntimeException
     */
    public function get_service_name()
    {
        if ($this->service_name) {
            return $this->service_name;
        }
        if ($this->class) {
            return $this->reflection->reflect_class($this->class)->get_short_name();
        }
        throw new Exception\RuntimeException('No service name given. Call AutoDiscover::setServiceName().');
    }
    /**
     * Set the location at which the WSDL file will be available.
     *
     * @param  Uri\Uri|string $uri
     * @throws Exception\InvalidArgumentException
     */
    public function set_uri($uri): static
    {
        if (!is_string($uri) && !$uri instanceof Uri\Uri) {
            throw new Exception\InvalidArgumentException('Argument to \Laminas\Soap\AutoDiscover::setUri should be string or \Laminas\Uri\Uri instance.');
        }
        $uri = trim($uri);
        $uri = htmlspecialchars($uri, ENT_QUOTES, 'UTF-8', false);
        if (empty($uri)) {
            throw new Exception\InvalidArgumentException('Uri contains invalid characters or is empty');
        }
        $this->uri = $uri;
        return $this;
    }
    /**
     * Return the current Uri that the SOAP WSDL Service will be located at.
     *
     * @return Uri\Uri
     * @throws Exception\RuntimeException
     */
    public function get_uri()
    {
        if ($this->uri === null) {
            throw new Exception\RuntimeException('Missing uri. You have to explicitly configure the Endpoint Uri by calling AutoDiscover::setUri().');
        }
        if (is_string($this->uri)) {
            $this->uri = Uri\Uri_Factory::factory($this->uri);
        }
        return $this->uri;
    }
    /**
     * Set the name of the WSDL handling class.
     *
     * @param  string $wsdlClass
     * @throws Exception\InvalidArgumentException
     */
    public function set_wsdl_class($wsdl_class): static
    {
        if (!is_string($wsdl_class) && !is_subclass_of($wsdl_class, Wsdl::class)) {
            throw new Exception\InvalidArgumentException('No \Laminas\Soap\Wsdl subclass given to Laminas\Soap\AutoDiscover::setWsdlClass as string.');
        }
        $this->wsdl_class = $wsdl_class;
        return $this;
    }
    /**
     * Return the name of the WSDL handling class.
     *
     * @return string
     */
    public function get_wsdl_class()
    {
        return $this->wsdl_class;
    }
    /**
     * Set options for all the binding operations soap:body elements.
     *
     * By default the options are set to 'use' => 'encoded' and
     * 'encodingStyle' => "http://schemas.xmlsoap.org/soap/encoding/".
     *
     * @throws Exception\InvalidArgumentException
     */
    public function set_operation_body_style(array $operation_style = []): static
    {
        if (!isset($operation_style['use'])) {
            throw new Exception\InvalidArgumentException('Key "use" is required in Operation soap:body style.');
        }
        $this->operation_body_style = $operation_style;
        return $this;
    }
    /**
     * Set Binding soap:binding style.
     *
     * By default 'style' is 'rpc' and 'transport' is 'http://schemas.xmlsoap.org/soap/http'.
     */
    public function set_binding_style(array $binding_style = []): static
    {
        if (isset($binding_style['style'])) {
            $this->binding_style['style'] = $binding_style['style'];
        }
        if (isset($binding_style['transport'])) {
            $this->binding_style['transport'] = $binding_style['transport'];
        }
        return $this;
    }
    /**
     * Set the strategy that handles functions and classes that are added AFTER this call.
     */
    public function set_complex_type_strategy(Complex_Type_Strategy $strategy): static
    {
        $this->strategy = $strategy;
        return $this;
    }
    /**
     * Set the Class the SOAP server will use
     *
     * @param string $class Class Name
     */
    public function set_class($class): static
    {
        $this->class = $class;
        return $this;
    }
    /**
     * Add a Single or Multiple Functions to the WSDL
     *
     * @param  string $function Function Name
     * @throws Exception\InvalidArgumentException
     */
    public function add_function($function): static
    {
        if (is_array($function)) {
            foreach ($function as $row) {
                $this->add_function($row);
            }
        } elseif (is_string($function)) {
            if (function_exists($function)) {
                $this->functions[] = $function;
            } else {
                throw new Exception\InvalidArgumentException('Argument to Laminas\Soap\AutoDiscover::addFunction should be a valid function name.');
            }
        } else {
            throw new Exception\InvalidArgumentException('Argument to Laminas\Soap\AutoDiscover::addFunction should be string or array of strings.');
        }
        return $this;
    }
    /**
     * Generate the WSDL for a service class.
     *
     * @return Wsdl
     */
    protected function generate_class()
    {
        return $this->generate_wsdl($this->reflection->reflect_class($this->class)->get_methods());
    }
    /**
     * Generate the WSDL for a set of functions.
     *
     * @return Wsdl
     */
    protected function generate_functions()
    {
        $methods = [];
        foreach (array_unique($this->functions) as $func) {
            $methods[] = $this->reflection->reflect_function($func);
        }
        return $this->generate_wsdl($methods);
    }
    /**
     * Generate the WSDL for a set of reflection method instances.
     *
     * @return Wsdl
     */
    protected function generate_wsdl(array $reflection_methods): object
    {
        $uri = $this->get_uri();
        $service_name = $this->get_service_name();
        $wsdl = new $this->wsdl_class($service_name, $uri, $this->strategy, $this->class_map);
        // The wsdl:types element must precede all other elements (WS-I Basic Profile 1.1 R2023)
        $wsdl->add_schema_type_section();
        $port = $wsdl->add_port_type($service_name . 'Port');
        $binding = $wsdl->add_binding($service_name . 'Binding', Wsdl::TYPES_NS . ':' . $service_name . 'Port');
        $wsdl->add_soap_binding($binding, $this->binding_style['style'], $this->binding_style['transport']);
        $wsdl->add_service($service_name . 'Service', $service_name . 'Port', Wsdl::TYPES_NS . ':' . $service_name . 'Binding', $uri);
        foreach ($reflection_methods as $method) {
            $this->add_function_to_wsdl($method, $wsdl, $port, $binding);
        }
        return $wsdl;
    }
    /**
     * Add a function to the WSDL document.
     *
     * @param  Reflection\AbstractFunction $function function to add
     * @param  Wsdl $wsdl WSDL document
     * @param  DOMElement $port wsdl:portType
     * @param  DOMElement $binding wsdl:binding
     * @throws Exception\InvalidArgumentException
     */
    protected function add_function_to_wsdl(\Laminas\Server\Reflection\Abstract_Function $function, $wsdl, $port, $binding)
    {
        $uri = $this->get_uri();
        // We only support one prototype: the one with the maximum number of arguments
        $prototype = null;
        $max_num_arguments_of_prototype = -1;
        foreach ($function->get_prototypes() as $tmp_prototype) {
            $num_params = count($tmp_prototype->get_parameters());
            if ($num_params > $max_num_arguments_of_prototype) {
                $max_num_arguments_of_prototype = $num_params;
                $prototype = $tmp_prototype;
            }
        }
        if ($prototype === null) {
            throw new Exception\InvalidArgumentException(sprintf('No prototypes could be found for the "%s" function', $function->get_name()));
        }
        $function_name = $wsdl->translate_type($function->get_name());
        // Add the input message (parameters)
        $args = [];
        if ($this->binding_style['style'] === 'document') {
            // Document style: wrap all parameters in a sequence element
            $sequence = [];
            foreach ($prototype->get_parameters() as $param) {
                $sequence_element = ['name' => $param->get_name(), 'type' => $wsdl->get_type($this->discovery_strategy->get_function_parameter_type($param))];
                if ($param->is_optional()) {
                    $sequence_element['nillable'] = 'true';
                }
                $sequence[] = $sequence_element;
            }
            $element = ['name' => $function_name, 'sequence' => $sequence];
            // Add the wrapper element part, which must be named 'parameters'
            $args['parameters'] = ['element' => $wsdl->add_element($element)];
        } else {
            // RPC style: add each parameter as a typed part
            foreach ($prototype->get_parameters() as $param) {
                $args[$param->get_name()] = ['type' => $wsdl->get_type($this->discovery_strategy->get_function_parameter_type($param))];
            }
        }
        $wsdl->add_message($function_name . 'In', $args);
        $is_one_way_message = $this->discovery_strategy->is_function_one_way($function, $prototype);
        if ($is_one_way_message === false) {
            // Add the output message (return value)
            $args = [];
            if ($this->binding_style['style'] === 'document') {
                // Document style: wrap the return value in a sequence element
                $sequence = [];
                if ($prototype->get_return_type() !== 'void') {
                    $sequence[] = ['name' => $function_name . 'Result', 'type' => $wsdl->get_type($this->discovery_strategy->get_function_return_type($function, $prototype))];
                }
                $element = ['name' => $function_name . 'Response', 'sequence' => $sequence];
                // Add the wrapper element part, which must be named 'parameters'
                $args['parameters'] = ['element' => $wsdl->add_element($element)];
            } elseif ($prototype->get_return_type() !== 'void') {
                // RPC style: add the return value as a typed part
                $args['return'] = ['type' => $wsdl->get_type($this->discovery_strategy->get_function_return_type($function, $prototype))];
            }
            $wsdl->add_message($function_name . 'Out', $args);
        }
        // Add the portType operation
        if ($is_one_way_message === false) {
            $port_operation = $wsdl->add_port_operation($port, $function_name, Wsdl::TYPES_NS . ':' . $function_name . 'In', Wsdl::TYPES_NS . ':' . $function_name . 'Out');
        } else {
            $port_operation = $wsdl->add_port_operation($port, $function_name, Wsdl::TYPES_NS . ':' . $function_name . 'In', false);
        }
        $desc = $this->discovery_strategy->get_function_documentation($function);
        if (strlen($desc) > 0) {
            $wsdl->add_documentation($port_operation, $desc);
        }
        // When using the RPC style, make sure the operation style includes a 'namespace'
        // attribute (WS-I Basic Profile 1.1 R2717)
        $operation_body_style = $this->operation_body_style;
        if ($this->binding_style['style'] === 'rpc' && !isset($operation_body_style['namespace'])) {
            $operation_body_style['namespace'] = '' . $uri;
        }
        // Add the binding operation
        if ($is_one_way_message === false) {
            $operation = $wsdl->add_binding_operation($binding, $function_name, $operation_body_style, $operation_body_style);
        } else {
            $operation = $wsdl->add_binding_operation($binding, $function_name, $operation_body_style);
        }
        $wsdl->add_soap_operation($operation, $uri . '#' . $function_name);
    }
    /**
     * Generate the WSDL file from the configured input.
     *
     * @return Wsdl
     * @throws Exception\RuntimeException
     */
    public function generate()
    {
        if ($this->class && $this->functions) {
            throw new Exception\RuntimeException('Can either dump functions or a class as a service, not both.');
        }
        if ($this->class) {
            return $this->generate_class();
        }
        return $this->generate_functions();
    }
    /**
     * Proxy to WSDL dump function
     *
     * @param  string $filename
     * @return bool
     * @throws Exception\RuntimeException
     */
    public function dump($filename)
    {
        return $this->generate()->dump($filename);
    }
    /**
     * Proxy to WSDL toXml() function
     *
     * @return string
     * @throws Exception\RuntimeException
     */
    public function to_xml()
    {
        return $this->generate()->to_xml();
    }
    /**
     * Handle WSDL document.
     */
    public function handle(): void
    {
        header('Content-Type: text/xml');
        echo $this->to_xml();
    }
}