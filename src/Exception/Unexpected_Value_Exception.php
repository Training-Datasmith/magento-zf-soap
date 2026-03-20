<?php

declare (strict_types=1);
namespace Laminas\Soap\Exception;

use UnexpectedValueException as SPLUnexpectedValueException;
/**
 * Exception thrown when provided arguments are invalid
 */
class UnexpectedValueException extends Spl_Unexpected_Value_Exception implements Exception_Interface
{
}