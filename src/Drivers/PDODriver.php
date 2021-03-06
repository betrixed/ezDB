<?php

/*
 * Copyright (c) 2021 - Terminus Studio (https://Terminus.Studio)
 *
 * ezDB - https://github.com/TerminusStudio/ezDB
 *
 * @license https://github.com/TerminusStudio/ezDB/blob/dev/LICENSE.md (MIT License)
 */

namespace TS\ezDB\Drivers;

use PDO;
use PDOException;
use PDOStatement;
use TS\ezDB\DatabaseConfig;
use TS\ezDB\Exceptions\DriverException;
use TS\ezDB\Interfaces\DriverInterface;
use TS\ezDB\Query\Processor\Processor;

class PDODriver implements DriverInterface {

    protected $logging = false;
    
    
    protected $lastsql;
    /**
     * @var PDO
     */
    protected $handle;

    /**
     * @var DatabaseConfig
     */
    protected $databaseConfig;

    /**
     * @var Processor
     */
    protected $processor;

    /**
     * @inheritDoc
     */
    public function __construct(DatabaseConfig $databaseConfig, Processor $processor) {
        $this->databaseConfig = $databaseConfig;
        $this->processor = $processor;
    }

    /*     * *
     *  variant pdo database types can implement this
     */

    protected function after_connect() {
        
    }

    /**
     * @inheritDoc
     */
    public function connect() {
        try {
            $serverName = sprintf(
                    '%s:host=%s;dbname=%s',
                    $this->databaseConfig->getDriver(),
                    $this->databaseConfig->getHost(),
                    $this->databaseConfig->getDatabase()
            );

            $options = array(
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            );

            if ($this->databaseConfig->getDriver() == 'mysql') {
                $options[PDO::MYSQL_ATTR_INIT_COMMAND] = sprintf(
                        'SET NAMES %s COLLATE %s',
                        $this->databaseConfig->getCharset(),
                        $this->databaseConfig->getCollation()
                );
            } elseif ($this->databaseConfig->getDriver() == 'pgsql') {
                $serverName .= sprintf(";options='--client_encoding=%s'", $this->databaseConfig->getCharset());
            }

            $this->handle = new PDO(
                    $serverName,
                    $this->databaseConfig->getUsername(),
                    $this->databaseConfig->getPassword(),
                    $options
            );
            $this->after_connect();

            return true;
        } catch (PDOException $e) {
            throw new DriverException($e->getMessage(), $e->getCode(), $e->getPrevious());
        }
        return false;
    }

    /**
     * @inheritDoc
     * @return PDO
     */
    public function handle() {
        if ($this->handle == null) {
            $this->connect();
            //throw new DriverException('Driver Handle not found. Make sure you call connect() first.');
        }
        return $this->handle;
    }

    /**
     * @inheritDoc
     */
    public function close() {
        $this->handle = null;
        return true;
    }

    /**
     * @inheritDoc
     */
    public function reset() {
        $this->handle = null;
        return $this->connect();
    }

    /**
     * @inheritDoc
     * @param string $query
     * @return false|PDOStatement
     * @throws DriverException
     */
    public function prepare(string $query) {
        $stmt = $this->handle->prepare($query);
        if ($stmt === false) {
            throw new DriverException('Error trying to prepare statement - ' . $this->handle->error);
        }
        return $stmt;
    }

    /**
     * @inheritDoc
     * @param PDOStatement $stmt
     */
    public function bind($stmt, &...$params) {
        foreach ($params as $ix => $val) {
            switch (gettype($val)) {
                case 'string' :
                    $type = PDO::PARAM_STR;
                    break;
                case 'integer' :
                    $type = PDO::PARAM_INT;
                    break;
                case 'boolean':
                    $type = PDO::PARAM_BOOL;
                    break;
                default:
                    $type = PDO::PARAM_STR;
            }
            $stmt->bindValue($ix + 1, $val, $type);
        }
        return $stmt;
    }

    /**
     * @inheritDoc
     * @param PDOStatement $stmt
     * @param bool $close Close Connection
     * @param bool $fetch Fetch Results
     * @throws DriverException
     */
    public function execute($stmt, $close = true, $fetch = false) {
        try {
            $stmt->execute();
            if ($fetch) {
                $result = $stmt->fetchAll(PDO::FETCH_CLASS);
            } else {
                $result = $stmt->rowCount();
            }

            if ($close) {
                $stmt->closeCursor();
            }
        } catch (\Exception $e) {
            throw new DriverException($e->getMessage(), $e->getCode(), $e->getPrevious());
        }

        return $this->getResults($result);
    }

    /**
     * @inheritDoc
     * @throws DriverException
     */
    public function query(string $query) {
        try {
            $stmt = $this->handle->query($query);
            try {
                //Try to fetch results
                $result = $stmt->fetchAll(PDO::FETCH_CLASS);
                $stmt->closeCursor();
            } catch (PDOException $PDOException) {
                //if there is error then check if the statement is instance of PDO statement.
                // Queries that don't return anything (like INSERT, DELETE, TRUNCATE) will throw error when we try to
                // fetchAll() for PHP 7. This issue was fixed for PHP 8. (Look at ezDB issue #4).
                if ($stmt instanceof PDOStatement) {
                    $result = true;
                } else {
                    throw new DriverException(
                                    $PDOException->getMessage(),
                                    $PDOException->getCode(),
                                    $PDOException->getPrevious()
                    );
                }
            }
        } catch (\Exception $e) {
            throw new DriverException($e->getMessage(), $e->getCode(), $e->getPrevious());
        }

        return $this->getResults($result);
    }

    /**
     * PDO uses exec to execute this.
     * Avoid using this.
     *
     * This returns an bool which indicates the status of the first query. Subsequent queries could have failed.
     *
     * @inheritDoc
     */
    public function exec(string $sql) {
        try {
            $stmt = $this->handle->prepare($sql);
            $result = $stmt->execute();
            $stmt->closeCursor();
            return $result;
        } catch (\Exception $e) {
            //  $this->handle->setAttribute(PDO::ATTR_EMULATE_PREPARES, 0);
            throw new DriverException($e->getMessage(), $e->getCode(), $e->getPrevious());
        }
    }

    /**
     * @param bool|int|array $result
     * @return array|bool
     * @throws DriverException
     */
    protected function getResults($result) {
        if (is_bool($result) || is_int($result) || is_array($result)) {
            return $result;
        }
        throw new DriverException('Error getting results.');
    }

    /**
     * @inheritDoc
     */
    public function escape(string $value) {
        $escaped = $this->handle->quote($value);
        return preg_replace('/^\'(.*)\'$/', '$1', $escaped); //remove surrounding quote
    }

    /**
     * @inheritDoc
     */
    public function getLastInsertId() {
        return $this->handle->lastInsertId();
    }

    /**
     * @inheritDoc
     */
    public function getProcessor() {
        return $this->processor;
    }

    /**
     * For changing on the fly 
     * - Mysql returns metadata field names in mixed case.
     * PDO::CASE_LOWER or PDO:CASE_NATURAL
     * @param int $value
     */
    public function setCaseAttribute(int $value) {
        $this->handle()->setAttribute(PDO::ATTR_CASE, $value);
    }

    /**
     * PDO::CASE_LOWER,  PDO::CASE_UPPER, PDO:CASE_NATURAL;
     * return current setting
     * 
     */
    public function getCaseAttribute(): int {
        return $this->handle()->getAttribute(PDO::ATTR_CASE);
    }

    /**
     * 
     * @param PDOStatement $sth
     * @param int $mode
     * @return array
     */
    public function fetchAllRows($sth, int $mode = \PDO::FETCH_ASSOC): array {
        if (!empty($sth)) {
            return $sth->fetchAll($mode);
        } else {
            return [];
        }
    }

    public function getTableNames(): array {  
    }

    /**
     * Execute a raw SQL query on the database.
     *
     * @param string $sql Raw SQL string to execute.
     * @param array &$values Optional array of bind values
     * @return mixed A result set object
     */
    public function prepareQuery($sql, array $values = null, array $bindTypes = null) : object {
        $handle = $this->handle;
        if ($this->logging) {
            $this->logger->log($sql);
            if ($values)
                $this->logger->log($values);
        }

        $this->lastsql = $sql;

        try {
            if (!($sth = $handle->prepare($sql)))
                throw new DriverException($this);
        } catch (PDOException $e) {
            throw new DriverException($this);
        }

        if (is_array($values) && !array_key_exists(0, $values)) {
            // assume named parameters
            foreach ($values as $key => $val) {
                $btype = !empty($bindTypes) ? $bindTypes[$key] : (is_int($val) ? \PDO::BIND_INT : \PDO::BIND_STR);
                $sth->bindValue($key, $val, $btype);
            }
            $evalues = null;
        } else {
            // assume maybe positional parameters
            $evalues = $values;
        }

        try {
            if (!$sth->execute($evalues))
                throw new DriverException($this);
        } catch (PDOException $e) {
            throw new DriverException($e);
        }
        return $sth;
    }
}
