<?php

declare (strict_types=1);
namespace Laminas\Soap\Exception;

use InvalidArgumentException as SPLInvalidArgumentException;
/**
 * Exception thrown when one or more method arguments are invalid
 */
class InvalidArgumentException extends Spl_Invalid_Argument_Exception implements Exception_Interface
{
}