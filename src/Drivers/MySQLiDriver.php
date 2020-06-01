<?php

/** @noinspection PhpComposerExtensionStubsInspection */

namespace TS\ezDB\Drivers;

use mysqli;
use PHP_CodeSniffer\Sniffs\AbstractArraySniff;
use TS\ezDB\DatabaseConfig;
use TS\ezDB\Exceptions\QueryException;
use TS\ezDB\Interfaces\DriverInterface;

class MySQLiDriver implements DriverInterface
{

    /**
     * @var mysqli
     */
    protected $handle;

    /**
     * @var DatabaseConfig
     */
    protected $databaseConfig;

    /**
     * @inheritDoc
     */
    public function __construct(DatabaseConfig $databaseConfig)
    {
        $this->databaseConfig = $databaseConfig;
    }

    /**
     * @inheritDoc
     */
    public function connect()
    {
        $this->handle = new mysqli(
            $this->databaseConfig->getHost(),
            $this->databaseConfig->getUsername(),
            $this->databaseConfig->getPassword(),
            $this->databaseConfig->getDatabase()
        );

        if ($this->handle->connect_errno) {
            return false;
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function handle()
    {
        return $this->handle();
    }

    /**
     * @inheritDoc
     */
    public function close()
    {
        return $this->handle->close();
    }

    /**
     * @inheritDoc
     */
    public function reset()
    {
        $this->handle = null;
        return $this->connect();
    }

    /**
     * @inheritDoc
     * @param string $query
     * @return false|\mysqli_stmt
     * @throws QueryException
     */
    public function prepare(string $query)
    {
        $stmt = $this->handle->prepare($query);
        if ($stmt === false) {
            throw new QueryException("Error trying to prepare statement");
        }
        return $stmt;
    }

    /**
     * @inheritDoc
     * @param \mysqli_stmt $stmt
     */
    public function bind($stmt, &...$params)
    {
        $type = '';

        foreach ($params as $param) {
            if (is_string($param)) {
                $type .= 's';
            } elseif (is_int($param)) {
                $type .= 'i';
            } elseif (is_double($param)) {
                $type .= 'd';
            } else {
                $type .= 's';
            }
        }

        $stmt->bind_param($type, ...$params);
        return $stmt;
    }

    /**
     * @inheritDoc
     * @param \mysqli_stmt $stmt
     * @throws QueryException
     */
    public function execute($stmt, $close = true, $fetch = false)
    {
        try {
            $result = $stmt->execute();
            if ($fetch) {
                $result = $stmt->get_result();
            }

            if ($close) {
                $stmt->close();
            }
        } catch (\Exception $e) {
            throw new QueryException($e->getMessage(), $e->getCode(), $e->getPrevious());
        }

        return $this->getResults($result);
    }

    /**
     * @inheritDoc
     * @throws QueryException
     */
    public function query(string $query)
    {
        try {
            $result = $this->handle->query($query);
        } catch (\Exception $e) {
            throw new QueryException($e->getMessage(), $e->getCode(), $e->getPrevious());
        }

        return $this->getResults($result);
    }

    /**
     * @param bool|\mysqli_result $result
     * @return array|bool
     * @throws QueryException
     */
    protected function getResults($result)
    {
        if (is_bool($result)) {
            return $result;
        } elseif ($result instanceof \mysqli_result) {
            $fetchedResult = [];
            while ($obj = $result->fetch_object()) {
                $fetchedResult[] = $obj;
            }
            $result->free();
            return $fetchedResult;
        }
        throw new QueryException("Error executing query.");
    }

    /**
     * @inheritDoc
     */
    public function escape(string $value)
    {
        return $this->handle->real_escape_string($value);
    }


}