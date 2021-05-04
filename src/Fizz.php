<?php

/**
 * @package ActiveRecord
 */

namespace TS\ezDB;

/**
 * Static functions for string processing,
 * From ActiveRecord Utils and Inflector.
 * Some array processing.
 * Needs a spring clean.
 */
class Fizz {

    /**
     * Turn a string into its camelized version.
     *
     * @param string $s string to convert
     * @return string
     */
    public static function str_camel(string $s): string {
        $s = preg_replace('/[_-]+/', '_', trim($s));
        $s = str_replace(' ', '_', $s);

        $camelized = '';

        for ($i = 0, $n = strlen($s); $i < $n; ++$i) {
            if ($s[$i] == '_' && $i + 1 < $n)
                $camelized .= strtoupper($s[++$i]);
            else
                $camelized .= $s[$i];
        }

        $camelized = trim($camelized, ' _');

        if (strlen($camelized) > 0)
            $camelized[0] = strtolower($camelized[0]);

        return $camelized;
    }

    /**
     * Determines if a string contains all uppercase characters.
     *
     * @param string $s string to check
     * @return bool
     */
    public static function is_upper(string $s): bool {
        return (strtoupper($s) === $s);
    }

    /**
     * Determines if a string contains all lowercase characters.
     *
     * @param string $s string to check
     * @return bool
     */
    public static function is_lower(string $s): bool {
        return (strtolower($s) === $s);
    }

    /**
     * Convert a camelized string to a lowercase, underscored string.
     *
     * @param string $s string to convert
     * @return string
     */
    public function str_uncamel(string $s): string {
        $normalized = '';

        for ($i = 0, $n = strlen($s); $i < $n; ++$i) {
            if (ctype_alpha($s[$i]) && self::is_upper($s[$i]))
                $normalized .= '_' . strtolower($s[$i]);
            else
                $normalized .= $s[$i];
        }
        return trim($normalized, ' _');
    }

    /**
     * Convert a string with space into a underscored equivalent.
     *
     * @param string $s string to convert
     * @return string
     */
    public static function underscorify(string $s): string {
        return preg_replace(array('/[_\- ]+/', '/([a-z])([A-Z])/'), array('_', '\\1_\\2'), trim($s));
    }

    public static function keyify(string $class_name): string {
        return strtolower(self::underscorify(denamespace($class_name))) . '_id';
    }

    static function denamespace($class_name) {
        if (is_object($class_name))
            $class_name = get_class($class_name);

        if (has_namespace($class_name)) {
            $parts = explode('\\', $class_name);
            return end($parts);
        }
        return $class_name;
    }

    public static function tableize($s) {
        return Utils::pluralize(strtolower(self::underscorify($s)));
    }

    public static function squeeze($char, $string) {
        return preg_replace("/$char+/", $char, $string);
    }

    public static function human_attribute($attr) {
        $inflected = self::variablize($attr);
        $normal = self::str_uncamel($inflected);

        return ucfirst(str_replace('_', ' ', $normal));
    }

    public static function variablize($s) {
        return str_replace(
                array('-', ' '),
                array('_', '_'),
                strtolower(trim($s))
        );
    }

    public static function is_odd(int $number): int {
        return $number & 1;
    }

    public static function is_a($type, $var) {
        switch ($type) {
            case 'range':
                if (is_array($var) && (int) $var[0] < (int) $var[1])
                    return true;
        }

        return false;
    }

    public static function is_blank($var) {
        return 0 === strlen($var);
    }

    static function get_namespaces($class_name) {
        if (self::has_namespace($class_name))
            return explode('\\', $class_name);
        return null;
    }

    static function has_namespace($class_name) {
        if (strpos($class_name, '\\') !== false)
            return true;
        return false;
    }

    public static function extract_options($options) {
        return is_array(end($options)) ? end($options) : array();
    }

    /**
     * TODO: array $conditions is both a reference and a return value
     * @param array $conditions
     * @param type $condition
     * @param type $conjuction
     * @return array
     */
    public static function add_condition(array &$conditions, $condition, $conjuction = 'AND'): array {
        if (is_array($condition)) {
            if (empty($conditions))
                $conditions = array_flatten($condition);
            else {
                $conditions[0] .= " $conjuction " . array_shift($condition);
                $conditions[] = array_flatten($condition);
            }
        } elseif (is_string($condition))
            $conditions[0] .= " $conjuction $condition";

        return $conditions;
    }

    /**
     * Returns true if all values in $haystack === $needle
     * @param $needle
     * @param $haystack
     * @return unknown_type
     */
    static function all($needle, array $haystack) {
        foreach ($haystack as $value) {
            if ($value !== $needle)
                return false;
        }
        return true;
    }

    /**
     * Wrap string definitions (if any) into arrays.
     */
    function wrap_strings_in_arrays(&$strings) {
        if (!is_array($strings))
            $strings = array(array($strings));
        else {
            foreach ($strings as &$str) {
                if (!is_array($str))
                    $str = array($str);
            }
        }
        return $strings;
    }

    function has_absolute_namespace($class_name) {
        if (strpos($class_name, '\\') === 0)
            return true;
        return false;
    }

// http://snippets.dzone.com/posts/show/4660

    /**
     * 
     * @param array $array
     * @return array
     */
    static function array_flatten(array $array) {
        $i = 0;

        while ($i < count($array)) {
            if (is_array($array[$i]))
                array_splice($array, $i, 1, $array[$i]);
            else
                ++$i;
        }
        return $array;
    }

    /**
     * Somewhat naive way to determine if an array is a hash map.
     * Test if first key is a string.
     */
    function is_hash(&$array) {
        if (!is_array($array))
            return false;

        $keys = array_keys($array);
        return @is_string($keys[0]) ? true : false;
    }

}
