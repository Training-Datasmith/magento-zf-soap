# Architecture: magento-zf-soap

## Purpose

Magento's fork of Zend Framework's SOAP component. Provides a SOAP server, SOAP client, WSDL generation, and auto-discovery of SOAP services from PHP class reflection.

## Directory Structure

```
src/
  Server.php              — SOAP server: wraps PHP's SoapServer, handles dispatch and fault generation
  Client.php              — SOAP client: wraps PHP's SoapClient with convenience methods
  Client/
    Common.php            — Common client functionality (cookie handling, HTTP auth)
    Dot_Net.php           — .NET-specific client compatibility fixes
    Local.php             — Local (in-process) SOAP client for testing
  Auto_Discover.php       — Reflects a PHP class and generates a WSDL document
  Wsdl.php                — WSDL DOM builder: creates WSDL XML programmatically
  Wsdl/
    ComplexTypeStrategy/  — Strategies for mapping PHP types to WSDL complex types
      Default_Complex_Type.php      — Uses PHPDoc @param/@return for type detection
      Array_Of_Type_Complex.php     — Maps PHP arrays to xsd:complexType sequences
      Array_Of_Type_Sequence.php    — Alternative array encoding
      Any_Type.php                  — Maps everything to xsd:anyType
      Composite.php                 — Chains multiple strategies (tries each in order)
    DocumentationStrategy/
      Reflection_Documentation.php  — Reads PHPDoc @param descriptions for WSDL docs
  Server/
    Document_Literal_Wrapper.php    — Wraps a class to use document/literal SOAP style
  Exception/                        — Typed exceptions for each error category
```

## Key Design Decisions

- **Strategy pattern for complex types**: `ComplexTypeStrategyInterface` allows plugging in different logic for how PHP class properties become WSDL types (the default reads PHPDoc; alternatives can use reflection or always use `anyType`)
- **Auto-discovery**: `AutoDiscover` reflects the target PHP class's PHPDoc `@param` and `@return` tags to generate a complete WSDL without manual WSDL authoring
- **DOM-based WSDL builder**: `Wsdl` uses `DOMDocument` directly rather than string concatenation, ensuring well-formed XML

## Extension Points

- Implement `Complex_Type_Strategy_Interface` to customize WSDL complex type generation
- Use `Composite` to chain multiple strategies (e.g., try `DefaultComplexType` first, fall back to `AnyType`)

## Dependency Flow

```
Auto_Discover::__toString() → generates WSDL XML
  → Wsdl (DOM builder)
  → ComplexTypeStrategy (PHP class → WSDL types)
  → DocumentationStrategy (PHPDoc → WSDL docs)

Server::handle()
  → PHP SoapServer (dispatches to bound class methods)

Client::__call()
  → PHP SoapClient (sends SOAP request, returns response)
```
