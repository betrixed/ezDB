<?php

namespace TS\ezDB\Exceptions;

use Throwable;

class Exception extends \Exception
{
    protected $exceptionType = "";
    /**
     * Exception constructor.
     * @param string $message
     * @param int $code
     * @param Throwable|null $previous
     */
    public function __construct($message = "", $code = 0, Throwable $previous = null)
    {
        parent::__construct($this->exceptionType . " ezDB Exception: " . $message, $code, $previous);
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return __CLASS__ . ": [{$this->code}]: {$this->message}\n";
    }
}