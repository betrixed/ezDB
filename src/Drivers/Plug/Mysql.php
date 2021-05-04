<?php

/**
 * @package ActiveRecord
 */

namespace TS\ezDB\Drivers\Plug;

use TS\ezDB\Drivers\PDODriver;

/**
 * Adapter for MySQL.
 *
 * @package ActiveRecord
 */
use PDO;
use TS\ezDB\Fizz;

class Mysql extends PDODriver {

    static $DEFAULT_PORT = 3306;

    public function limit($sql, $offset, $limit) {
        $offset = is_null($offset) ? '' : intval($offset) . ',';
        $limit = intval($limit);
        return "$sql LIMIT {$offset}$limit";
    }

    public function query_column_info($table): array {
        $attr = $this->getCaseAttribute();
        if ($attr !== PDO::CASE_LOWER) {
            $this->setCaseAttribute(PDO::CASE_LOWER);
        }
        $result = $this->fetchAllRows($this->prepareQuery("SHOW COLUMNS FROM $table"));
        if ($attr !== PDO::CASE_LOWER) {
            $this->setCaseAttribute($attr);
        }
        return $result;
    }

    public function getTableNames(): array {
        
        $attr = $this->getCaseAttribute();
        if ($attr !== PDO::CASE_LOWER) {
            $this->setCaseAttribute(PDO::CASE_LOWER);
        }
        $result = $this->fetchAllRows($this->prepareQuery('SHOW TABLES'), PDO::FETCH_COLUMN);
        if ($attr !== PDO::CASE_LOWER) {
            $this->setCaseAttribute($attr);
        }
        return $result;
    }

    public function create_column(&$column) {
        $c = new Column();
        $c->inflected_name = Fizz::variablize($column['field']);
        $c->name = $column['field'];
        $c->nullable = ($column['null'] === 'YES' ? true : false);
        $c->pk = ($column['key'] === 'PRI' ? true : false);
        $c->auto_increment = ($column['extra'] === 'auto_increment' ? true : false);

        $coltype = $column['type'];
        switch ($coltype) {
            case 'timestamp':
            case 'datetime':
                $c->raw_type = 'datetime';
                $c->length = 19;
                break;
            case 'date':
                $c->raw_type = 'date';
                $c->length = 10;
                break;
            case 'time':
                $c->raw_type = 'time';
                $c->length = 8;
                break;
            default: {
                    preg_match('/^([A-Za-z0-9_]+)(\(([0-9]+(,[0-9]+)?)\))?/', $coltype, $matches);

                    $c->raw_type = (count($matches) > 0 ? $matches[1] : $column['type']);

                    if (count($matches) >= 4) {
                        $c->length = intval($matches[3]);
                    }
                    break;
                }
        }

        $c->map_raw_type();
        $c->default = $c->cast($column['default'], $this);

        return $c;
    }

    public function set_encoding($charset) {
        $params = array($charset);
        $this->prepareQuery('SET NAMES ?', $params);
    }

    public function accepts_limit_and_order_for_update_and_delete() {
        return true;
    }

    public function native_database_types() {
        return array(
            'primary_key' => 'int(11) UNSIGNED DEFAULT NULL auto_increment PRIMARY KEY',
            'string' => array('name' => 'varchar', 'length' => 255),
            'text' => array('name' => 'text'),
            'integer' => array('name' => 'int', 'length' => 11),
            'float' => array('name' => 'float'),
            'datetime' => array('name' => 'datetime'),
            'timestamp' => array('name' => 'datetime'),
            'time' => array('name' => 'time'),
            'date' => array('name' => 'date'),
            'binary' => array('name' => 'blob'),
            'boolean' => array('name' => 'tinyint', 'length' => 1)
        );
    }

    public function after_connect() {
        $handle = $this->handle();
        $this->setCaseAttribute(PDO::CASE_NATURAL);
        $handle->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $handle->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        $handle->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
    }

}
