<?php

declare (strict_types=1);
namespace Laminas\Soap\Exception;

use BadMethodCallException as SPLBadMethodCallException;
/**
 * Exception thrown when unrecognized method is called via overloading
 */
class BadMethodCallException extends Spl_Bad_Method_Call_Exception implements Exception_Interface
{
}