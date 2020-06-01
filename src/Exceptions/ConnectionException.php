<?php

namespace TS\ezDB\Exceptions;

class ConnectionException extends Exception
{
    protected $exceptionType = "Connection";

    /**
     * @return string
     */
    public function __toString()
    {
        return __CLASS__ . ": [{$this->code}]: {$this->message}\n";
    }
}