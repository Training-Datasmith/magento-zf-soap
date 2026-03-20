<?php

declare (strict_types=1);
namespace Laminas\Soap\Exception;

use RuntimeException as SPLRuntimeException;
/**
 * Exception thrown when there is an error during program execution
 */
class RuntimeException extends Spl_Runtime_Exception implements Exception_Interface
{
}