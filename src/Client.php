<?php

declare (strict_types=1);
namespace Laminas\Soap;

use function array_merge;
use function call_user_func_array;
use function class_exists;
use function count;
use function extension_loaded;
use function get_resource_type;
use function in_array;
use function is_array;
use function is_callable;
use function is_readable;
use function is_resource;
use function is_string;
use Laminas\Server\Client as ServerClient;
use Laminas\Stdlib\Array_Utils;
use function parse_url;
use const PHP_URL_SCHEME;
use const SOAP_1_1;
use const SOAP_1_2;
use const SOAP_DOCUMENT;
use const SOAP_ENCODED;
use const SOAP_LITERAL;
use const SOAP_RPC;
use Soap_Client;
use Soap_Header;
use function sprintf;
use function strtolower;
use Traversable;
class Client implements Server_Client
{
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
    protected $encoding = 'UTF-8';
    /**
     * Registered fault exceptions
     *
     * @var array
     */
    protected $fault_exceptions = [];
    /**
     * Last invoked method
     *
     * @var string
     */
    protected $last_method = '';
    /**
     * Permanent SOAP request headers (shared between requests).
     *
     * @var array
     */
    protected $permanent_soap_input_headers = [];
    /**
     * SoapClient object
     *
     * @var SoapClient
     */
    protected $soap_client;
    /**
     * Array of SoapHeader objects
     *
     * @var SoapHeader[]
     */
    protected $soap_input_headers = [];
    /**
     * Array of SoapHeader objects
     *
     * @var array
     */
    protected $soap_output_headers = [];
    /**
     * SOAP version to use; SOAP_1_2 by default, to allow processing of headers
     *
     * @var int
     */
    protected $soap_version = SOAP_1_2;
    /** @var array */
    protected $typemap;
    /**
     * WSDL used to access server
     * It also defines Client working mode (WSDL vs non-WSDL)
     *
     * @var string
     */
    protected $wsdl;
    /**
     * Whether to send the "Connection: Keep-Alive" header (true) or "Connection: close" header (false)
     * Available since PHP 5.4.0
     *
     * @var bool
     */
    protected $keep_alive;
    /**
     * One of SOAP_SSL_METHOD_TLS, SOAP_SSL_METHOD_SSLv2, SOAP_SSL_METHOD_SSLv3 or SOAP_SSL_METHOD_SSLv23
     * Available since PHP 5.5.0
     *
     * @var int
     */
    protected $ssl_method;
    /** @var string */
    protected $connection_timeout;
    /** @var string */
    protected $local_cert;
    /** @var string */
    protected $location;
    /** @var string */
    protected $login;
    /** @var string */
    protected $passphrase;
    /** @var string */
    protected $password;
    /** @var string */
    protected $proxy_host;
    /** @var string */
    protected $proxy_login;
    /** @var string */
    protected $proxy_password;
    /** @var string */
    protected $proxy_port;
    /** @var string */
    protected $stream_context;
    /** @var string */
    protected $style;
    /** @var string */
    protected $uri;
    /** @var string */
    protected $use;
    /** @var string */
    protected $user_agent;
    /** @var int */
    protected $cache_wsdl;
    /** @var int */
    protected $compression;
    /** @var int */
    protected $features;
    /**
     * @param  string $wsdl
     * @param  array|Traversable $options
     * @throws Exception\ExtensionNotLoadedException
     */
    public function __construct($wsdl = null, $options = null)
    {
        if (!extension_loaded('soap')) {
            throw new Exception\Extension_Not_Loaded_Exception('SOAP extension is not loaded.');
        }
        if ($wsdl !== null) {
            $this->set_wsdl($wsdl);
        }
        if ($options !== null) {
            $this->set_options($options);
        }
    }
    /**
     * Set wsdl
     *
     * @param  string $wsdl
     */
    public function set_wsdl($wsdl): static
    {
        $this->wsdl = $wsdl;
        $this->soap_client = null;
        return $this;
    }
    /**
     * Get wsdl
     *
     * @return string
     */
    public function get_wsdl()
    {
        return $this->wsdl;
    }
    /**
     * Set Options
     *
     * Allows setting options as an associative array of option => value pairs.
     *
     * @param  array|Traversable $options
     * @throws Exception\InvalidArgumentException
     */
    public function set_options($options): static
    {
        if ($options instanceof Traversable) {
            $options = Array_Utils::iterator_to_array($options);
        }
        foreach ($options as $key => $value) {
            switch (strtolower($key)) {
                case 'classmap':
                case 'class_map':
                    $this->set_classmap($value);
                    break;
                case 'encoding':
                    $this->set_encoding($value);
                    break;
                case 'soapversion':
                case 'soap_version':
                    $this->set_soap_version($value);
                    break;
                case 'wsdl':
                    $this->set_wsdl($value);
                    break;
                case 'uri':
                    $this->set_uri($value);
                    break;
                case 'location':
                    $this->set_location($value);
                    break;
                case 'style':
                    $this->set_style($value);
                    break;
                case 'use':
                    $this->set_encoding_method($value);
                    break;
                case 'login':
                    $this->set_http_login($value);
                    break;
                case 'password':
                    $this->set_http_password($value);
                    break;
                case 'proxyhost':
                case 'proxy_host':
                    $this->set_proxy_host($value);
                    break;
                case 'proxyport':
                case 'proxy_port':
                    $this->set_proxy_port($value);
                    break;
                case 'proxylogin':
                case 'proxy_login':
                    $this->set_proxy_login($value);
                    break;
                case 'proxypassword':
                case 'proxy_password':
                    $this->set_proxy_password($value);
                    break;
                case 'localcert':
                case 'local_cert':
                    $this->set_https_certificate($value);
                    break;
                case 'passphrase':
                    $this->set_https_cert_passphrase($value);
                    break;
                case 'compression':
                    $this->set_compression_options($value);
                    break;
                case 'streamcontext':
                case 'stream_context':
                    $this->set_stream_context($value);
                    break;
                case 'features':
                    $this->set_soap_features($value);
                    break;
                case 'cachewsdl':
                case 'cache_wsdl':
                    $this->set_wsdl_cache($value);
                    break;
                case 'useragent':
                case 'user_agent':
                    $this->set_user_agent($value);
                    break;
                case 'typemap':
                case 'type_map':
                    $this->set_typemap($value);
                    break;
                case 'connectiontimeout':
                case 'connection_timeout':
                    $this->connection_timeout = $value;
                    break;
                case 'keepalive':
                case 'keep_alive':
                    $this->set_keep_alive($value);
                    break;
                case 'sslmethod':
                case 'ssl_method':
                    $this->set_ssl_method($value);
                    break;
                default:
                    throw new Exception\InvalidArgumentException('Unknown SOAP client option');
            }
        }
        return $this;
    }
    /**
     * Return array of options suitable for using with SoapClient constructor
     */
    public function get_options(): array
    {
        $options = [];
        $options['classmap'] = $this->get_classmap();
        $options['typemap'] = $this->get_typemap();
        $options['encoding'] = $this->get_encoding();
        $options['soap_version'] = $this->get_soap_version();
        $options['wsdl'] = $this->get_wsdl();
        $options['uri'] = $this->get_uri();
        $options['location'] = $this->get_location();
        $options['style'] = $this->get_style();
        $options['use'] = $this->get_encoding_method();
        $options['login'] = $this->get_http_login();
        $options['password'] = $this->get_http_password();
        $options['proxy_host'] = $this->get_proxy_host();
        $options['proxy_port'] = $this->get_proxy_port();
        $options['proxy_login'] = $this->get_proxy_login();
        $options['proxy_password'] = $this->get_proxy_password();
        $options['local_cert'] = $this->get_https_certificate();
        $options['passphrase'] = $this->get_https_cert_passphrase();
        $options['compression'] = $this->get_compression_options();
        $options['connection_timeout'] = $this->connection_timeout;
        $options['stream_context'] = $this->get_stream_context();
        $options['cache_wsdl'] = $this->get_wsdl_cache();
        $options['features'] = $this->get_soap_features();
        $options['user_agent'] = $this->get_user_agent();
        $options['keep_alive'] = $this->get_keep_alive();
        $options['ssl_method'] = $this->get_ssl_method();
        foreach ($options as $key => $value) {
            if ($value === null) {
                unset($options[$key]);
            }
        }
        return $options;
    }
    /**
     * Set SOAP version
     *
     * @param  int $version One of the SOAP_1_1 or SOAP_1_2 constants
     * @throws Exception\InvalidArgumentException With invalid soap version argument.
     */
    public function set_soap_version($version): static
    {
        if (!in_array($version, [SOAP_1_1, SOAP_1_2])) {
            throw new Exception\InvalidArgumentException('Invalid soap version specified. Use SOAP_1_1 or SOAP_1_2 constants.');
        }
        $this->soap_version = $version;
        $this->soap_client = null;
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
     * Set classmap
     *
     * @throws Exception\InvalidArgumentException For any invalid class in the class map.
     */
    public function set_classmap(array $classmap): static
    {
        foreach ($classmap as $class) {
            if (!class_exists($class)) {
                throw new Exception\InvalidArgumentException('Invalid class in class map: ' . $class);
            }
        }
        $this->classmap = $classmap;
        $this->soap_client = null;
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
     * @throws Exception\InvalidArgumentException
     */
    public function set_typemap(array $type_map): static
    {
        foreach ($type_map as $type) {
            if (!is_callable($type['from_xml'])) {
                throw new Exception\InvalidArgumentException(sprintf('Invalid from_xml callback for type: %s', $type['type_name']));
            }
            if (!is_callable($type['to_xml'])) {
                throw new Exception\InvalidArgumentException(sprintf('Invalid to_xml callback for type: %s', $type['type_name']));
            }
        }
        $this->typemap = $type_map;
        $this->soap_client = null;
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
     * Set encoding
     *
     * @param  string $encoding
     * @throws Exception\InvalidArgumentException With invalid encoding argument.
     */
    public function set_encoding($encoding): static
    {
        if (!is_string($encoding)) {
            throw new Exception\InvalidArgumentException('Invalid encoding specified');
        }
        $this->encoding = $encoding;
        $this->soap_client = null;
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
     * Check for valid URN
     *
     * @param  string $urn
     * @throws Exception\InvalidArgumentException On invalid URN.
     */
    public function validate_urn($urn): bool
    {
        $scheme = parse_url($urn, PHP_URL_SCHEME);
        if ($scheme === false || $scheme === null) {
            throw new Exception\InvalidArgumentException('Invalid URN');
        }
        return true;
    }
    /**
     * Set URI
     *
     * URI in Web Service the target namespace
     *
     * @param  string $uri
     * @throws Exception\InvalidArgumentException With invalid uri argument.
     */
    public function set_uri($uri): static
    {
        $this->validate_urn($uri);
        $this->uri = $uri;
        $this->soap_client = null;
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
     * Set Location
     *
     * URI in Web Service the target namespace
     *
     * @param  string $location
     * @throws Exception\InvalidArgumentException With invalid uri argument.
     */
    public function set_location($location): static
    {
        $this->validate_urn($location);
        $this->location = $location;
        $this->soap_client = null;
        return $this;
    }
    /**
     * Retrieve URI
     *
     * @return string
     */
    public function get_location()
    {
        return $this->location;
    }
    /**
     * Set request style
     *
     * @param  int $style One of the SOAP_RPC or SOAP_DOCUMENT constants
     * @throws Exception\InvalidArgumentException With invalid style argument.
     */
    public function set_style($style): static
    {
        if (!in_array($style, [SOAP_RPC, SOAP_DOCUMENT])) {
            throw new Exception\InvalidArgumentException('Invalid request style specified. Use SOAP_RPC or SOAP_DOCUMENT constants.');
        }
        $this->style = $style;
        $this->soap_client = null;
        return $this;
    }
    /**
     * Get request style
     *
     * @return int
     */
    public function get_style()
    {
        return $this->style;
    }
    /**
     * Set message encoding method
     *
     * @param  int $use One of the SOAP_ENCODED or SOAP_LITERAL constants
     * @throws Exception\InvalidArgumentException With invalid message encoding method argument.
     */
    public function set_encoding_method($use): static
    {
        if (!in_array($use, [SOAP_ENCODED, SOAP_LITERAL])) {
            throw new Exception\InvalidArgumentException('Invalid message encoding method. Use SOAP_ENCODED or SOAP_LITERAL constants.');
        }
        $this->use = $use;
        $this->soap_client = null;
        return $this;
    }
    /**
     * Get message encoding method
     *
     * @return int
     */
    public function get_encoding_method()
    {
        return $this->use;
    }
    /**
     * Set HTTP login
     *
     * @param  string $login
     */
    public function set_http_login($login): static
    {
        $this->login = $login;
        $this->soap_client = null;
        return $this;
    }
    /**
     * Retrieve HTTP Login
     *
     * @return string
     */
    public function get_http_login()
    {
        return $this->login;
    }
    /**
     * Set HTTP password
     *
     * @param  string $password
     */
    public function set_http_password($password): static
    {
        $this->password = $password;
        $this->soap_client = null;
        return $this;
    }
    /**
     * Retrieve HTTP Password
     *
     * @return string
     */
    public function get_http_password()
    {
        return $this->password;
    }
    /**
     * Set proxy host
     *
     * @param  string $proxyHost
     */
    public function set_proxy_host($proxy_host): static
    {
        $this->proxy_host = $proxy_host;
        $this->soap_client = null;
        return $this;
    }
    /**
     * Retrieve proxy host
     *
     * @return string
     */
    public function get_proxy_host()
    {
        return $this->proxy_host;
    }
    /**
     * Set proxy port
     *
     * @param  int $proxyPort
     */
    public function set_proxy_port($proxy_port): static
    {
        $this->proxy_port = (int) $proxy_port;
        $this->soap_client = null;
        return $this;
    }
    /**
     * Retrieve proxy port
     *
     * @return int
     */
    public function get_proxy_port()
    {
        return $this->proxy_port;
    }
    /**
     * Set proxy login
     *
     * @param  string $proxyLogin
     */
    public function set_proxy_login($proxy_login): static
    {
        $this->proxy_login = $proxy_login;
        $this->soap_client = null;
        return $this;
    }
    /**
     * Retrieve proxy login
     *
     * @return string
     */
    public function get_proxy_login()
    {
        return $this->proxy_login;
    }
    /**
     * Set proxy password
     *
     * @param  string $proxyPassword
     */
    public function set_proxy_password($proxy_password): static
    {
        $this->proxy_password = $proxy_password;
        $this->soap_client = null;
        return $this;
    }
    /**
     * Set HTTPS client certificate path
     *
     * @param  string $localCert local certificate path
     * @throws Exception\InvalidArgumentException With invalid local certificate path argument.
     */
    public function set_https_certificate($local_cert): static
    {
        if (!is_readable($local_cert)) {
            throw new Exception\InvalidArgumentException('Invalid HTTPS client certificate path.');
        }
        $this->local_cert = $local_cert;
        $this->soap_client = null;
        return $this;
    }
    /**
     * Get HTTPS client certificate path
     *
     * @return string
     */
    public function get_https_certificate()
    {
        return $this->local_cert;
    }
    /**
     * Set HTTPS client certificate passphrase
     *
     * @param  string $passphrase
     */
    public function set_https_cert_passphrase($passphrase): static
    {
        $this->passphrase = $passphrase;
        $this->soap_client = null;
        return $this;
    }
    /**
     * Get HTTPS client certificate passphrase
     *
     * @return string
     */
    public function get_https_cert_passphrase()
    {
        return $this->passphrase;
    }
    /**
     * Set compression options
     *
     * @param  int|null $compressionOptions
     */
    public function set_compression_options($compression_options): static
    {
        if ($compression_options === null) {
            $this->compression = null;
        } else {
            $this->compression = (int) $compression_options;
        }
        $this->soap_client = null;
        return $this;
    }
    /**
     * Get Compression options
     *
     * @return int
     */
    public function get_compression_options()
    {
        return $this->compression;
    }
    /**
     * Retrieve proxy password
     *
     * @return string
     */
    public function get_proxy_password()
    {
        return $this->proxy_password;
    }
    /**
     * Set Stream Context
     *
     * @param  resource $context
     * @throws Exception\InvalidArgumentException
     */
    public function set_stream_context($context): static
    {
        if (!is_resource($context) || get_resource_type($context) !== 'stream-context') {
            throw new Exception\InvalidArgumentException('Invalid stream context resource given.');
        }
        $this->stream_context = $context;
        return $this;
    }
    /**
     * Get Stream Context
     *
     * @return resource
     */
    public function get_stream_context()
    {
        return $this->stream_context;
    }
    /**
     * Set the SOAP Feature options.
     *
     * @param  string|int $feature
     */
    public function set_soap_features($feature): static
    {
        $this->features = $feature;
        $this->soap_client = null;
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
     * @param  string|int|bool|null $caching
     */
    public function set_wsdl_cache($caching): static
    {
        //@todo check WSDL_CACHE_* constants?
        if ($caching === null) {
            $this->cache_wsdl = null;
        } else {
            $this->cache_wsdl = (int) $caching;
        }
        return $this;
    }
    /**
     * Get current SOAP WSDL Caching option
     *
     * @return int
     */
    public function get_wsdl_cache()
    {
        return $this->cache_wsdl;
    }
    /**
     * Set the string to use in User-Agent header
     *
     * @param  string|null $userAgent
     */
    public function set_user_agent($user_agent): static
    {
        if ($user_agent === null) {
            $this->user_agent = null;
        } else {
            $this->user_agent = (string) $user_agent;
        }
        return $this;
    }
    /**
     * Get current string to use in User-Agent header
     *
     * @return string|null
     */
    public function get_user_agent()
    {
        return $this->user_agent;
    }
    /**
     * Retrieve request XML
     *
     * @return string
     */
    public function get_last_request(): ?string
    {
        if ($this->soap_client !== null) {
            return $this->soap_client->__get_last_request();
        }
        return '';
    }
    /**
     * Get response XML
     *
     * @return string
     */
    public function get_last_response(): ?string
    {
        if ($this->soap_client !== null) {
            return $this->soap_client->__get_last_response();
        }
        return '';
    }
    /**
     * Retrieve request headers
     *
     * @return string
     */
    public function get_last_request_headers(): ?string
    {
        if ($this->soap_client !== null) {
            return $this->soap_client->__get_last_request_headers();
        }
        return '';
    }
    /**
     * Retrieve response headers (as string)
     *
     * @return string
     */
    public function get_last_response_headers(): ?string
    {
        if ($this->soap_client !== null) {
            return $this->soap_client->__get_last_response_headers();
        }
        return '';
    }
    /**
     * Retrieve last invoked method
     *
     * @return string
     */
    public function get_last_method()
    {
        return $this->last_method;
    }
    // @codingStandardsIgnoreStart
    /**
     * Do request proxy method.
     *
     * May be overridden in subclasses
     *
     * @param  string $request
     * @param  string $location
     * @param  string $action
     * @param  int    $version
     * @param  int    $oneWay
     * @return mixed
     */
    public function _do_request(Client\Common $client, $request, $location, $action, $version, $one_way = null)
    {
        // Perform request as is
        if ($one_way === null) {
            return $client->parent_do_request($request, $location, $action, $version);
        }
        return $client->parent_do_request($request, $location, $action, $version, $one_way);
    }
    // @codingStandardsIgnoreEnd
    /**
     * Initialize SOAP Client object
     *
     * @throws Exception\ExceptionInterface
     */
    protected function init_soap_client_object()
    {
        $wsdl = $this->get_wsdl();
        $options = array_merge($this->get_options(), ['trace' => true]);
        if ($wsdl === null) {
            if (!isset($options['location'])) {
                throw new Exception\UnexpectedValueException('"location" parameter is required in non-WSDL mode.');
            }
            if (!isset($options['uri'])) {
                throw new Exception\UnexpectedValueException('"uri" parameter is required in non-WSDL mode.');
            }
        } else {
            if (isset($options['use'])) {
                throw new Exception\UnexpectedValueException('"use" parameter only works in non-WSDL mode.');
            }
            if (isset($options['style'])) {
                throw new Exception\UnexpectedValueException('"style" parameter only works in non-WSDL mode.');
            }
        }
        unset($options['wsdl']);
        $this->soap_client = new Client\Common($this->_do_request(...), $wsdl, $options);
    }
    // @codingStandardsIgnoreStart
    /**
     * Perform arguments pre-processing
     *
     * My be overridden in descendant classes
     *
     * @param  array $arguments
     * @return array
     */
    protected function _pre_process_arguments($arguments)
    {
        // Do nothing
        return $arguments;
    }
    // @codingStandardsIgnoreEnd
    // @codingStandardsIgnoreStart
    /**
     * Perform result pre-processing
     *
     * My be overridden in descendant classes
     *
     * @param  array $result
     * @return array
     */
    protected function _pre_process_result($result)
    {
        // Do nothing
        return $result;
    }
    // @codingStandardsIgnoreEnd
    /**
     * Add SOAP input header
     *
     * @param  bool $permanent
     */
    public function add_soap_input_header(Soap_Header $header, $permanent = false): static
    {
        if ($permanent) {
            $this->permanent_soap_input_headers[] = $header;
        } else {
            $this->soap_input_headers[] = $header;
        }
        return $this;
    }
    /**
     * Reset SOAP input headers
     */
    public function reset_soap_input_headers(): static
    {
        $this->permanent_soap_input_headers = [];
        $this->soap_input_headers = [];
        return $this;
    }
    /**
     * Get last SOAP output headers
     *
     * @return array
     */
    public function get_last_soap_output_header_objects()
    {
        return $this->soap_output_headers;
    }
    /**
     * Perform a SOAP call
     *
     * @param  array  $arguments
     * @return mixed
     */
    public function __call(string $name, array $arguments)
    {
        if (!is_array($arguments)) {
            $arguments = [$arguments];
        }
        $soap_client = $this->get_soap_client();
        $this->last_method = $name;
        $soap_headers = array_merge($this->permanent_soap_input_headers, $this->soap_input_headers);
        $result = $soap_client->__soap_call(
            $name,
            $this->_pre_process_arguments($arguments),
            [],
            /* Options are already set to the SOAP client object */
            count($soap_headers) > 0 ? $soap_headers : [],
            $this->soap_output_headers
        );
        // Reset non-permanent input headers
        $this->soap_input_headers = [];
        return $this->_pre_process_result($result);
    }
    /**
     * Send an RPC request to the service for a specific method.
     *
     * @param  string $method Name of the method we want to call.
     * @param  array $params List of parameters for the method.
     * @return mixed Returned results.
     */
    public function call($method, $params = []): mixed
    {
        return call_user_func_array($this->__call(...), [$method, $params]);
    }
    /**
     * Return a list of available functions
     *
     * @return array
     * @throws Exception\UnexpectedValueException
     */
    public function get_functions(): ?array
    {
        if ($this->get_wsdl() === null) {
            throw new Exception\UnexpectedValueException(sprintf('%s method is available only in WSDL mode.', __METHOD__));
        }
        $soap_client = $this->get_soap_client();
        return $soap_client->__get_functions();
    }
    /**
     * Return a list of SOAP types
     *
     * @return array
     * @throws Exception\UnexpectedValueException
     */
    public function get_types(): ?array
    {
        if ($this->get_wsdl() === null) {
            throw new Exception\UnexpectedValueException(sprintf('%s method is available only in WSDL mode.', __METHOD__));
        }
        $soap_client = $this->get_soap_client();
        return $soap_client->__get_types();
    }
    /**
     * Set SoapClient object
     */
    public function set_soap_client(Soap_Client $soap_client): static
    {
        $this->soap_client = $soap_client;
        return $this;
    }
    /**
     * Get SoapClient object
     *
     * @return SoapClient
     */
    public function get_soap_client()
    {
        if ($this->soap_client === null) {
            $this->init_soap_client_object();
        }
        return $this->soap_client;
    }
    /**
     * Set cookie
     *
     * @param  string $cookieName
     * @param  string $cookieValue
     */
    public function set_cookie($cookie_name, $cookie_value = null): static
    {
        $soap_client = $this->get_soap_client();
        $soap_client->__set_cookie($cookie_name, $cookie_value);
        return $this;
    }
    /**
     * @return boolean
     */
    public function get_keep_alive()
    {
        return $this->keep_alive;
    }
    /**
     * @param boolean $keepAlive
     */
    public function set_keep_alive($keep_alive): static
    {
        $this->keep_alive = (bool) $keep_alive;
        return $this;
    }
    /**
     * @return int
     */
    public function get_ssl_method()
    {
        return $this->ssl_method;
    }
    /**
     * @param int $sslMethod
     */
    public function set_ssl_method($ssl_method): static
    {
        $this->ssl_method = $ssl_method;
        return $this;
    }
}