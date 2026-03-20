<?php

declare (strict_types=1);
namespace Laminas\Soap;

use function array_merge;
use function array_search;
use function array_slice;
use function array_unique;
use function array_unshift;
use function call_user_func_array;
use function class_exists;
use Dom_Document;
use Dom_Node;
use const E_USER_ERROR;
use Exception;
use function extension_loaded;
use function file_get_contents;
use function func_get_args;
use function func_num_args;
use function function_exists;
use function get_class_methods;
use function gettype;
use function in_array;
use function ini_get;
use function ini_set;
use function is_array;
use function is_callable;
use function is_object;
use function is_string;
use function is_subclass_of;
use Laminas\Server\Server as LaminasServerServer;
use Laminas\Soap\Exception\Extension_Not_Loaded_Exception;
use Laminas\Soap\Exception\InvalidArgumentException;
use Laminas\Soap\Exception\RuntimeException;
use Laminas\Stdlib\Array_Utils;
use function libxml_disable_entity_loader;
use const LIBXML_PARSEHUGE;
use const LIBXML_VERSION;
use function ob_get_clean;
use function ob_start;
use function parse_url;
use const PHP_URL_SCHEME;
use ReflectionClass;
use function restore_error_handler;
use function set_error_handler;
use Simple_Xml_Element;
use const SOAP_1_1;
use const SOAP_1_2;
use const SOAP_FUNCTIONS_ALL;
use const SOAP_PERSISTENCE_REQUEST;
use const SOAP_PERSISTENCE_SESSION;
use Soap_Fault;
use Soap_Server;
use function sprintf;
use stdClass;
use function strlen;
use function strtolower;
use Traversable;
use function trim;
use const XML_DOCUMENT_TYPE_NODE;
class Server implements Laminas_Server_Server
{
    /**
     * Actor URI
     *
     * @var string URI
     */
    protected $actor;
    /**
     * Class registered with this server
     *
     * @var string
     */
    protected $class;
    /**
     * Server instance
     *
     * @var SoapServer
     */
    protected $server;
    /**
     * Arguments to pass to {@link $class} constructor
     *
     * @var array
     */
    protected $class_args = [];
    /**
     * Array of SOAP type => PHP class pairings for handling return/incoming values
     *
     * @var array
     */
    protected $classmap;
    /**
     * Encoding
     *
     * @var string
     */
    protected $encoding;
    /**
     * Registered fault exceptions
     *
     * @var array
     */
    protected $fault_exceptions = [];
    /**
     * Container for caught exception during business code execution
     *
     * @var Exception
     */
    protected $caught_exception;
    /**
     * SOAP Server Features
     *
     * @var int
     */
    protected $features;
    /**
     * Functions registered with this server; may be either an array or the SOAP_FUNCTIONS_ALL constant
     *
     * @var array|int
     */
    protected $functions = [];
    /**
     * Object registered with this server
     *
     * @var object
     */
    protected $object;
    /**
     * Informs if the soap server is in debug mode
     *
     * @var bool
     */
    protected $debug = false;
    /**
     * Persistence mode; should be one of the SOAP persistence constants
     *
     * @var int
     */
    protected $persistence;
    /**
     * Request XML
     *
     * @var string
     */
    protected $request;
    /**
     * Response XML
     *
     * @var string
     */
    protected $response;
    /**
     * Flag: whether or not {@link handle()} should return a response instead of automatically emitting it.
     *
     * @var bool
     */
    protected $return_response = false;
    /**
     * SOAP version to use; SOAP_1_2 by default, to allow processing of headers
     *
     * @var int
     */
    protected $soap_version = SOAP_1_2;
    /**
     * Array of type mappings
     *
     * @var array
     */
    protected $typemap;
    /**
     * URI namespace for SOAP server
     *
     * @var string URI
     */
    protected $uri;
    /**
     * URI or path to WSDL
     *
     * @var string
     */
    protected $wsdl;
    /**
     * WSDL Caching Options of SOAP Server
     *
     * @var mixed
     */
    protected $wsdl_cache;
    /**
     * The send_errors Options of SOAP Server
     *
     * @var bool
     */
    protected $send_errors;
    /**
     * Allows LIBXML_PARSEHUGE Options of DOMDocument->loadXML( string $source [, int $options = 0 ] ) to be set
     *
     * @var bool
     */
    protected $parse_huge;
    /**
     * Constructor
     *
     * Sets display_errors INI setting to off (prevent client errors due to bad
     * XML in response). Registers {@link handlePhpErrors()} as error handler
     * for E_USER_ERROR.
     *
     * If $wsdl is provided, it is passed on to {@link setWSDL()}; if any
     * options are specified, they are passed on to {@link setOptions()}.
     *
     * @param  string $wsdl
     * @param  array $options
     * @throws ExtensionNotLoadedException
     */
    public function __construct($wsdl = null, ?array $options = null)
    {
        if (!extension_loaded('soap')) {
            throw new Extension_Not_Loaded_Exception('SOAP extension is not loaded.');
        }
        if (null !== $wsdl) {
            $this->set_wsdl($wsdl);
        }
        if (null !== $options) {
            $this->set_options($options);
        }
    }
    /**
     * Set Options
     *
     * Allows setting options as an associative array of option => value pairs.
     *
     * @param array|Traversable $options
     */
    public function set_options($options): static
    {
        if ($options instanceof Traversable) {
            $options = Array_Utils::iterator_to_array($options);
        }
        foreach ($options as $key => $value) {
            switch (strtolower($key)) {
                case 'actor':
                    $this->set_actor($value);
                    break;
                case 'classmap':
                case 'class_map':
                    $this->set_classmap($value);
                    break;
                case 'typemap':
                case 'type_map':
                    $this->set_typemap($value);
                    break;
                case 'encoding':
                    $this->set_encoding($value);
                    break;
                case 'soapversion':
                case 'soap_version':
                    $this->set_soap_version($value);
                    break;
                case 'uri':
                    $this->set_uri($value);
                    break;
                case 'wsdl':
                    $this->set_wsdl($value);
                    break;
                case 'cache_wsdl':
                    $this->set_wsdl_cache($value);
                    break;
                case 'features':
                    $this->set_soap_features($value);
                    break;
                case 'send_errors':
                    $this->set_send_errors($value);
                    break;
                case 'parse_huge':
                    $this->set_parse_huge($value);
                    break;
                default:
                    break;
            }
        }
        return $this;
    }
    /**
     * Return array of options suitable for using with SoapServer constructor
     */
    public function get_options(): array
    {
        $options = [];
        if (null !== $this->actor) {
            $options['actor'] = $this->get_actor();
        }
        if (null !== $this->classmap) {
            $options['classmap'] = $this->get_classmap();
        }
        if (null !== $this->typemap) {
            $options['typemap'] = $this->get_typemap();
        }
        if (null !== $this->encoding) {
            $options['encoding'] = $this->get_encoding();
        }
        if (null !== $this->soap_version) {
            $options['soap_version'] = $this->get_soap_version();
        }
        if (null !== $this->uri) {
            $options['uri'] = $this->get_uri();
        }
        if (null !== $this->features) {
            $options['features'] = $this->get_soap_features();
        }
        if (null !== $this->wsdl_cache) {
            $options['cache_wsdl'] = $this->get_wsdl_cache();
        }
        if (null !== $this->send_errors) {
            $options['send_errors'] = $this->get_send_errors();
        }
        if (null !== $this->parse_huge) {
            $options['parse_huge'] = $this->get_parse_huge();
        }
        return $options;
    }
    /**
     * Set encoding
     *
     * @param  string $encoding
     * @throws InvalidArgumentException With invalid encoding argument.
     */
    public function set_encoding($encoding): static
    {
        if (!is_string($encoding)) {
            throw new InvalidArgumentException('Invalid encoding specified');
        }
        $this->encoding = $encoding;
        return $this;
    }
    /**
     * Get encoding
     *
     * @return string
     */
    public function get_encoding()
    {
        return $this->encoding;
    }
    /**
     * Set SOAP version
     *
     * @param  int $version One of the SOAP_1_1 or SOAP_1_2 constants
     * @throws InvalidArgumentException With invalid soap version argument.
     */
    public function set_soap_version($version): static
    {
        if (!in_array($version, [SOAP_1_1, SOAP_1_2])) {
            throw new InvalidArgumentException('Invalid soap version specified');
        }
        $this->soap_version = $version;
        return $this;
    }
    /**
     * Get SOAP version
     *
     * @return int
     */
    public function get_soap_version()
    {
        return $this->soap_version;
    }
    /**
     * Check for valid URN
     *
     * @param  string $urn
     * @return true
     * @throws InvalidArgumentException On invalid URN.
     */
    public function validate_urn($urn): bool
    {
        $scheme = parse_url($urn, PHP_URL_SCHEME);
        if ($scheme === false || $scheme === null) {
            throw new InvalidArgumentException('Invalid URN');
        }
        return true;
    }
    /**
     * Set actor
     *
     * Actor is the actor URI for the server.
     *
     * @param  string $actor
     */
    public function set_actor($actor): static
    {
        $this->validate_urn($actor);
        $this->actor = $actor;
        return $this;
    }
    /**
     * Retrieve actor
     *
     * @return string
     */
    public function get_actor()
    {
        return $this->actor;
    }
    /**
     * Set URI
     *
     * URI in SoapServer is actually the target namespace, not a URI; $uri must begin with 'urn:'.
     *
     * @param  string $uri
     */
    public function set_uri($uri): static
    {
        $this->validate_urn($uri);
        $this->uri = $uri;
        return $this;
    }
    /**
     * Retrieve URI
     *
     * @return string
     */
    public function get_uri()
    {
        return $this->uri;
    }
    /**
     * Set classmap
     *
     * @param  array $classmap
     * @throws InvalidArgumentException For any invalid class in the class map.
     */
    public function set_classmap($classmap): static
    {
        if (!is_array($classmap)) {
            throw new InvalidArgumentException('Classmap must be an array');
        }
        foreach ($classmap as $class) {
            if (!class_exists($class)) {
                throw new InvalidArgumentException('Invalid class in class map');
            }
        }
        $this->classmap = $classmap;
        return $this;
    }
    /**
     * Retrieve classmap
     *
     * @return mixed
     */
    public function get_classmap()
    {
        return $this->classmap;
    }
    /**
     * Set typemap with xml to php type mappings with appropriate validation.
     *
     * @param  array $typeMap
     * @throws InvalidArgumentException
     */
    public function set_typemap($type_map): static
    {
        if (!is_array($type_map)) {
            throw new InvalidArgumentException('Typemap must be an array');
        }
        foreach ($type_map as $type) {
            if (!is_callable($type['from_xml'])) {
                throw new InvalidArgumentException(sprintf('Invalid from_xml callback for type: %s', $type['type_name']));
            }
            if (!is_callable($type['to_xml'])) {
                throw new InvalidArgumentException('Invalid to_xml callback for type: ' . $type['type_name']);
            }
        }
        $this->typemap = $type_map;
        return $this;
    }
    /**
     * Retrieve typemap
     *
     * @return array
     */
    public function get_typemap()
    {
        return $this->typemap;
    }
    /**
     * Set wsdl
     *
     * @param  string $wsdl  URI or path to a WSDL
     */
    public function set_wsdl($wsdl): static
    {
        $this->wsdl = $wsdl;
        return $this;
    }
    /**
     * Retrieve wsdl
     *
     * @return string
     */
    public function get_wsdl()
    {
        return $this->wsdl;
    }
    /**
     * Set the SOAP Feature options.
     *
     * @param  string|int $feature
     */
    public function set_soap_features($feature): static
    {
        $this->features = $feature;
        return $this;
    }
    /**
     * Return current SOAP Features options
     *
     * @return int
     */
    public function get_soap_features()
    {
        return $this->features;
    }
    /**
     * Set the SOAP WSDL Caching Options
     *
     * @param  string|int|bool $options
     */
    public function set_wsdl_cache($options): static
    {
        $this->wsdl_cache = $options;
        return $this;
    }
    /**
     * Get current SOAP WSDL Caching option
     *
     * @return null|string|int|bool
     */
    public function get_wsdl_cache()
    {
        return $this->wsdl_cache;
    }
    /**
     * Set the SOAP send_errors Option
     *
     * @param  bool $sendErrors
     */
    public function set_send_errors($send_errors): static
    {
        $this->send_errors = (bool) $send_errors;
        return $this;
    }
    /**
     * Get current SOAP send_errors option
     *
     * @return bool
     */
    public function get_send_errors()
    {
        return $this->send_errors;
    }
    /**
     * Set flag to allow DOMDocument->loadXML() to parse huge nodes
     *
     * @param  bool $parseHuge
     */
    public function set_parse_huge($parse_huge): static
    {
        $this->parse_huge = (bool) $parse_huge;
        return $this;
    }
    /**
     * Get flag to allow DOMDocument->loadXML() to parse huge nodes
     *
     * @return bool
     */
    public function get_parse_huge()
    {
        return $this->parse_huge;
    }
    /**
     * Attach a function as a server method
     *
     * @param  array|string $function Function name, array of function names to attach,
     *             or SOAP_FUNCTIONS_ALL to attach all functions
     * @param  string $namespace Ignored
     * @throws InvalidArgumentException On invalid functions.
     */
    public function add_function($function, $namespace = ''): static
    {
        // Bail early if set to SOAP_FUNCTIONS_ALL
        if ($this->functions === SOAP_FUNCTIONS_ALL) {
            return $this;
        }
        if (is_array($function)) {
            foreach ($function as $func) {
                if (is_string($func) && function_exists($func)) {
                    $this->functions[] = $func;
                } else {
                    throw new InvalidArgumentException('One or more invalid functions specified in array');
                }
            }
        } elseif (is_string($function) && function_exists($function)) {
            $this->functions[] = $function;
        } elseif ($function === SOAP_FUNCTIONS_ALL) {
            $this->functions = SOAP_FUNCTIONS_ALL;
        } else {
            throw new InvalidArgumentException('Invalid function specified');
        }
        if (is_array($this->functions)) {
            $this->functions = array_unique($this->functions);
        }
        return $this;
    }
    /**
     * Attach a class to a server
     *
     * Accepts a class name to use when handling requests. Any additional
     * arguments will be passed to that class' constructor when instantiated.
     *
     * See {@link setObject()} to set pre-configured object instances as request handlers.
     *
     * @param  string|object $class Class name or object instance which executes
     *             SOAP Requests at endpoint.
     * @param  string $namespace
     * @param  null|array $argv
     * @return self
     * @throws InvalidArgumentException If called more than once, or if class does not exist.
     */
    public function set_class($class, $namespace = '', $argv = null)
    {
        if (isset($this->class)) {
            throw new InvalidArgumentException('A class has already been registered with this soap server instance');
        }
        if (is_object($class)) {
            return $this->set_object($class);
        }
        if (!is_string($class)) {
            throw new InvalidArgumentException(sprintf('Invalid class argument (%s)', gettype($class)));
        }
        if (!class_exists($class)) {
            throw new InvalidArgumentException(sprintf('Class "%s" does not exist', $class));
        }
        $this->class = $class;
        if (2 < func_num_args()) {
            $argv = func_get_args();
            $this->class_args = array_slice($argv, 2);
        }
        return $this;
    }
    /**
     * Attach an object to a server
     *
     * Accepts an instantiated object to use when handling requests.
     *
     * @param  object $object
     * @throws InvalidArgumentException
     */
    public function set_object($object): static
    {
        if (!is_object($object)) {
            throw new InvalidArgumentException(sprintf('Invalid object argument (%s)', gettype($object)));
        }
        if (isset($this->object)) {
            throw new InvalidArgumentException('An object has already been registered with this soap server instance');
        }
        $this->object = $object;
        return $this;
    }
    /**
     * Return a server definition array
     *
     * Returns a list of all functions registered with {@link addFunction()},
     * merged with all public methods of the class set with {@link setClass()}
     * (if any).
     */
    public function get_functions(): array
    {
        $functions = [];
        if (null !== $this->class) {
            $functions = get_class_methods($this->class);
        } elseif (null !== $this->object) {
            $functions = get_class_methods($this->object);
        }
        return array_merge((array) $this->functions, $functions);
    }
    /**
     * Unimplemented: Load server definition
     *
     * @param  array $definition
     * @throws RuntimeException Unimplemented.
     */
    public function load_functions($definition): never
    {
        throw new RuntimeException('Unimplemented method.');
    }
    /**
     * Set server persistence
     *
     * @param  int $mode SOAP_PERSISTENCE_SESSION or SOAP_PERSISTENCE_REQUEST constants
     * @throws InvalidArgumentException
     */
    public function set_persistence($mode): static
    {
        if (!in_array($mode, [SOAP_PERSISTENCE_SESSION, SOAP_PERSISTENCE_REQUEST])) {
            throw new InvalidArgumentException('Invalid persistence mode specified');
        }
        $this->persistence = $mode;
        return $this;
    }
    /**
     * Get server persistence
     *
     * @return int
     */
    public function get_persistence()
    {
        return $this->persistence;
    }
    /**
     * Set request
     *
     * $request may be any of:
     * - DOMDocument; if so, then cast to XML
     * - DOMNode; if so, then grab owner document and cast to XML
     * - SimpleXMLElement; if so, then cast to XML
     * - stdClass; if so, calls __toString() and verifies XML
     * - string; if so, verifies XML
     *
     * @param DOMDocument|DOMNode|SimpleXMLElement|stdClass|string $request
     * @throws InvalidArgumentException
     */
    protected function set_request($request): static
    {
        $xml = null;
        if ($request instanceof Dom_Document) {
            $xml = $request->save_xml();
        } elseif ($request instanceof Dom_Node) {
            $xml = $request->owner_document->save_xml();
        } elseif ($request instanceof Simple_Xml_Element) {
            $xml = $request->as_xml();
        } elseif (is_object($request) || is_string($request)) {
            if (is_object($request)) {
                $xml = $request->__toString();
            } else {
                $xml = $request;
            }
            $xml = trim($xml);
            if (strlen($xml) === 0) {
                throw new InvalidArgumentException('Empty request');
            }
            $load_entities = $this->disable_entity_loader(true);
            $dom = new Dom_Document();
            if (true === $this->get_parse_huge()) {
                $load_status = $dom->load_xml($xml, LIBXML_PARSEHUGE);
            } else {
                $load_status = $dom->load_xml($xml);
            }
            $this->disable_entity_loader($load_entities);
            // @todo check libxml errors ? validate document ?
            if (!$load_status) {
                throw new InvalidArgumentException('Invalid XML');
            }
            foreach ($dom->child_nodes as $child) {
                if ($child->node_type === XML_DOCUMENT_TYPE_NODE) {
                    throw new InvalidArgumentException('Invalid XML: Detected use of illegal DOCTYPE');
                }
            }
        }
        $this->request = $xml;
        return $this;
    }
    /**
     * Retrieve request XML
     *
     * @return string
     */
    public function get_last_request()
    {
        return $this->request;
    }
    /**
     * Set return response flag
     *
     * If true, {@link handle()} will return the response instead of
     * automatically sending it back to the requesting client.
     *
     * The response is always available via {@link getResponse()}.
     *
     * @param  bool $flag
     */
    public function set_return_response($flag = true): static
    {
        $this->return_response = (bool) $flag;
        return $this;
    }
    /**
     * Retrieve return response flag
     *
     * @return bool
     */
    public function get_return_response()
    {
        return $this->return_response;
    }
    /**
     * Get response XML
     *
     * @return string
     */
    public function get_response()
    {
        return $this->response;
    }
    /**
     * Get SoapServer object
     *
     * Uses {@link $wsdl} and return value of {@link getOptions()} to instantiate
     * SoapServer object, and then registers any functions or class with it, as
     * well as persistence.
     *
     * @return SoapServer
     */
    public function get_soap()
    {
        if ($this->server instanceof Soap_Server) {
            return $this->server;
        }
        $options = $this->get_options();
        $server = new Soap_Server($this->wsdl, $options);
        if (!empty($this->functions)) {
            $server->add_function($this->functions);
        }
        if (!empty($this->class)) {
            $args = $this->class_args;
            array_unshift($args, $this->class);
            call_user_func_array($server->set_class(...), $args);
        }
        if (!empty($this->object)) {
            $server->set_object($this->object);
        }
        if (null !== $this->persistence) {
            $server->set_persistence($this->persistence);
        }
        $this->server = $server;
        return $this->server;
    }
    /**
    * Proxy for _getSoap method
    *
    * @see _getSoap
    *
    * @return SoapServer the soapServer instance
        public function getSoap()
        {
       return $this->_getSoap();
        }
    */
    /**
     * Handle a request
     *
     * Instantiates SoapServer object with options set in object, and
     * dispatches its handle() method.
     *
     * $request may be any of:
     * - DOMDocument; if so, then cast to XML
     * - DOMNode; if so, then grab owner document and cast to XML
     * - SimpleXMLElement; if so, then cast to XML
     * - stdClass; if so, calls __toString() and verifies XML
     * - string; if so, verifies XML
     *
     * If no request is passed, pulls request using php:://input (for
     * cross-platform compatibility purposes).
     *
     * @param DOMDocument|DOMNode|SimpleXMLElement|stdClass|string $request Optional request
     * @return void|string
     */
    public function handle($request = null)
    {
        if (null === $request) {
            $request = file_get_contents('php://input');
        }
        // Set Server error handler
        $display_errors_original_state = $this->initialize_soap_error_context();
        $set_request_exception = null;
        try {
            $this->set_request($request);
        } catch (Exception $e) {
            $set_request_exception = $e;
        }
        $soap = $this->get_soap();
        $fault = false;
        $this->response = '';
        if ($set_request_exception instanceof Exception) {
            // Create SOAP fault message if we've caught a request exception
            $fault = $this->fault($set_request_exception->get_message(), 'Sender');
        } else {
            ob_start();
            try {
                $soap->handle($this->request);
            } catch (Exception $e) {
                $fault = $this->fault($e);
            }
            $this->response = ob_get_clean();
        }
        // Restore original error handler
        restore_error_handler();
        ini_set('display_errors', (string) $display_errors_original_state);
        // Send a fault, if we have one
        if ($fault instanceof Soap_Fault && !$this->return_response) {
            $soap->fault($fault->faultcode, $fault->get_message());
            return;
        }
        // Echo the response, if we're not returning it
        if (!$this->return_response) {
            echo $this->response;
            return;
        }
        // Return a fault, if we have it
        if ($fault instanceof Soap_Fault) {
            return $fault;
        }
        // Return the response
        return $this->response;
    }
    /**
     * Method initializes the error context that the SOAPServer environment will run in.
     *
     * @return bool display_errors original value
     */
    protected function initialize_soap_error_context(): string|false
    {
        $display_errors_original_state = ini_get('display_errors');
        ini_set('display_errors', '0');
        set_error_handler($this->handle_php_errors(...), E_USER_ERROR);
        return $display_errors_original_state;
    }
    /**
     * Set the debug mode.
     * In debug mode, all exceptions are send to the client.
     *
     * @param  bool $debug
     */
    public function set_debug_mode($debug): static
    {
        $this->debug = $debug;
        return $this;
    }
    /**
     * Validate and register fault exception
     *
     * @param  string|array $class Exception class or array of exception classes
     * @throws InvalidArgumentException
     */
    public function register_fault_exception($class): static
    {
        if (is_array($class)) {
            foreach ($class as $row) {
                $this->register_fault_exception($row);
            }
        } elseif (is_string($class) && class_exists($class) && (is_subclass_of($class, 'Exception') || 'Exception' === $class)) {
            $ref = new ReflectionClass($class);
            $this->fault_exceptions[] = $ref->get_name();
            $this->fault_exceptions = array_unique($this->fault_exceptions);
        } else {
            throw new InvalidArgumentException('Argument for Laminas\Soap\Server::registerFaultException should be' . ' string or array of strings with valid exception names');
        }
        return $this;
    }
    /**
     * Checks if provided fault name is registered as valid in this server.
     *
     * @param string $fault Name of a fault class
     * @return bool
     */
    public function is_registered_as_fault_exception($fault)
    {
        if ($this->debug) {
            return true;
        }
        $ref = new ReflectionClass($fault);
        $class_names = $ref->get_name();
        return in_array($class_names, $this->fault_exceptions);
    }
    /**
     * Deregister a fault exception from the fault exception stack
     *
     * @param  string $class
     */
    public function deregister_fault_exception($class): bool
    {
        if (in_array($class, $this->fault_exceptions, true)) {
            $index = array_search($class, $this->fault_exceptions);
            unset($this->fault_exceptions[$index]);
            return true;
        }
        return false;
    }
    /**
     * Return fault exceptions list
     *
     * @return array
     */
    public function get_fault_exceptions()
    {
        return $this->fault_exceptions;
    }
    /**
     * Return caught exception during business code execution
     *
     * @return null|Exception caught exception
     */
    public function get_exception()
    {
        return $this->caught_exception;
    }
    /**
     * Generate a server fault
     *
     * Note that the arguments are reverse to those of SoapFault.
     *
     * If an exception is passed as the first argument, its message and code
     * will be used to create the fault object if it has been registered via
     * {@Link registerFaultException()}.
     *
     * @link   http://www.w3.org/TR/soap12-part1/#faultcodes
     *
     * @param string|Exception $fault
     * @param  string $code SOAP Fault Codes
     */
    public function fault($fault = null, $code = 'Receiver'): \Soap_Fault
    {
        $this->caught_exception = is_string($fault) ? new Exception($fault) : $fault;
        if ($fault instanceof Exception) {
            if ($this->is_registered_as_fault_exception($fault)) {
                $message = $fault->get_message();
                $e_code = $fault->get_code();
                $code = empty($e_code) ? $code : $e_code;
            } else {
                $message = 'Unknown error';
            }
        } elseif (is_string($fault)) {
            $message = $fault;
        } else {
            $message = 'Unknown error';
        }
        $allowed_fault_modes = ['VersionMismatch', 'MustUnderstand', 'DataEncodingUnknown', 'Sender', 'Receiver', 'Server'];
        if (!in_array($code, $allowed_fault_modes)) {
            $code = 'Receiver';
        }
        return new Soap_Fault($code, $message);
    }
    /**
     * Throw PHP errors as SoapFaults
     *
     * @param  int $errno
     * @param  string $errstr
     * @throws SoapFault
     */
    public function handle_php_errors($errno, $errstr): never
    {
        throw $this->fault($errstr, 'Receiver');
    }
    /**
     * Disable the ability to load external XML entities based on libxml version
     *
     * If we are using libxml < 2.9, unsafe XML entity loading must be
     * disabled with a flag.
     *
     * If we are using libxml >= 2.9, XML entity loading is disabled by default.
     *
     * @param bool $flag
     * @return bool
     */
    private function disable_entity_loader($flag = true)
    {
        if (LIBXML_VERSION < 20900) {
            return libxml_disable_entity_loader($flag);
        }
        return $flag;
    }
}