<?php

declare(strict_types=1);

namespace GuzzleHttp\Utility;

/**
 * Expands URI templates. Userland implementation of PECL uri_template.
 *
 * @link http://tools.ietf.org/html/rfc6570
 */
final class UriTemplate
{
    /** @var array Hash for quick operator lookups */
    private static $operatorHash = [
        '' => ['prefix' => '', 'joiner' => ',', 'query' => false],
        '+' => ['prefix' => '', 'joiner' => ',', 'query' => false],
        '#' => ['prefix' => '#', 'joiner' => ',', 'query' => false],
        '.' => ['prefix' => '.', 'joiner' => '.', 'query' => false],
        '/' => ['prefix' => '/', 'joiner' => '/', 'query' => false],
        ';' => ['prefix' => ';', 'joiner' => ';', 'query' => true],
        '?' => ['prefix' => '?', 'joiner' => '&', 'query' => true],
        '&' => ['prefix' => '&', 'joiner' => '&', 'query' => true],
    ];

    /** @var array Delimiters */
    private static $delims = [
        ':',
        '/',
        '?',
        '#',
        '[',
        ']',
        '@',
        '!',
        '$',
        '&',
        '\'',
        '(',
        ')',
        '*',
        '+',
        ',',
        ';',
        '=',
    ];

    /** @var array<int, string> Percent encoded delimiters */
    private static $delimsPct = [
        '%3A',
        '%2F',
        '%3F',
        '%23',
        '%5B',
        '%5D',
        '%40',
        '%21',
        '%24',
        '%26',
        '%27',
        '%28',
        '%29',
        '%2A',
        '%2B',
        '%2C',
        '%3B',
        '%3D',
    ];

    /**
     * @param mixed[] $variables Variables to use in the template expansion
     *
     * @return string|string[]|null
     */
    public static function expand(string $template, array $variables)
    {
        if (false === strpos($template, '{')) {
            return $template;
        }

        return preg_replace_callback(
            '/\{([^\}]+)\}/',
            self::expandMatchCallback($variables),
            $template
        );
    }

    /**
     * @param mixed[] $variables Variables to use in the template expansion
     */
    private static function expandMatchCallback(array $variables): callable
    {
        return function (array $matches) use ($variables): string {
            return self::expandMatch($matches, $variables);
        };
    }

    /**
     * Process an expansion
     *
     * @param mixed[]  $variables Variables to use in the template expansion
     * @param string[] $matches   Matches met in the preg_replace_callback
     *
     * @return string Returns the replacement string
     */
    private static function expandMatch(array $matches, array $variables): string
    {
        static $rfc1738to3986 = ['+' => '%20', '%7e' => '~'];

        $replacements = [];
        $parsed = self::parseExpression($matches[1]);
        $prefix = self::$operatorHash[$parsed['operator']]['prefix'];
        $joiner = self::$operatorHash[$parsed['operator']]['joiner'];
        $useQuery = self::$operatorHash[$parsed['operator']]['query'];

        foreach ($parsed['values'] as $value) {
            if (!isset($variables[$value['value']])) {
                continue;
            }

            $variable = $variables[$value['value']];
            $actuallyUseQuery = $useQuery;
            $expanded = '';

            if (is_array($variable)) {
                $isAssoc = self::isAssoc($variable);
                $kvp = [];
                foreach ($variable as $key => $var) {
                    if ($isAssoc) {
                        $key = rawurlencode((string)$key);
                        $isNestedArray = is_array($var);
                    } else {
                        $isNestedArray = false;
                    }

                    if (!$isNestedArray) {
                        $var = rawurlencode($var);
                        if ($parsed['operator'] === '+' ||
                            $parsed['operator'] === '#'
                        ) {
                            $var = self::decodeReserved($var);
                        }
                    }

                    if ($value['modifier'] === '*') {
                        if ($isAssoc) {
                            if ($isNestedArray) {
                                // Nested arrays must allow for deeply nested
                                // structures.
                                $var = strtr(
                                    http_build_query([$key => $var]),
                                    $rfc1738to3986
                                );
                            } else {
                                $var = $key . '=' . $var;
                            }
                        } elseif ($key > 0 && $actuallyUseQuery) {
                            $var = $value['value'] . '=' . $var;
                        }
                    }

                    $kvp[$key] = $var;
                }

                if (empty($variable)) {
                    $actuallyUseQuery = false;
                } elseif ($value['modifier'] === '*') {
                    $expanded = implode($joiner, $kvp);
                    if ($isAssoc) {
                        // Don't prepend the value name when using the explode
                        // modifier with an associative array.
                        $actuallyUseQuery = false;
                    }
                } else {
                    if ($isAssoc) {
                        // When an associative array is encountered and the
                        // explode modifier is not set, then the result must be
                        // a comma separated list of keys followed by their
                        // respective values.
                        foreach ($kvp as $k => &$v) {
                            $v = $k . ',' . $v;
                        }
                    }
                    $expanded = implode(',', $kvp);
                }
            } else {
                if ($value['modifier'] === ':') {
                    $variable = substr($variable, 0, $value['position']);
                }
                $expanded = rawurlencode((string)$variable);
                if ($parsed['operator'] === '+' || $parsed['operator'] === '#') {
                    $expanded = self::decodeReserved($expanded);
                }
            }

            if ($actuallyUseQuery) {
                if (!$expanded && $joiner !== '&') {
                    $expanded = $value['value'];
                } else {
                    $expanded = $value['value'] . '=' . $expanded;
                }
            }

            $replacements[] = $expanded;
        }

        $ret = implode($joiner, $replacements);
        if ($ret && $prefix) {
            return $prefix . $ret;
        }

        return $ret;
    }

    /**
     * Parse an expression into parts
     *
     * @param string $expression Expression to parse
     *
     * @return array<string, mixed> Returns an associative array of parts
     */
    private static function parseExpression(string $expression): array
    {
        $result = [];

        if (isset(self::$operatorHash[$expression[0]])) {
            $result['operator'] = $expression[0];
            $expression = substr($expression, 1);
        } else {
            $result['operator'] = '';
        }

        foreach (explode(',', $expression) as $value) {
            $value = trim($value);
            $varspec = [];
            if ($colonPos = strpos($value, ':')) {
                $varspec['value'] = substr($value, 0, $colonPos);
                $varspec['modifier'] = ':';
                $varspec['position'] = (int)substr($value, $colonPos + 1);
            } elseif (substr($value, -1) === '*') {
                $varspec['modifier'] = '*';
                $varspec['value'] = substr($value, 0, -1);
            } else {
                $varspec['value'] = (string)$value;
                $varspec['modifier'] = '';
            }
            $result['values'][] = $varspec;
        }

        return $result;
    }

    /**
     * Determines if an array is associative.
     *
     * This makes the assumption that input arrays are sequences or hashes.
     * This assumption is a tradeoff for accuracy in favor of speed, but it
     * should work in almost every case where input is supplied for a URI
     * template.
     *
     * @param array $array Array to check
     */
    private static function isAssoc(array $array): bool
    {
        return $array && array_keys($array)[0] !== 0;
    }

    /**
     * Removes percent encoding on reserved characters (used with + and #
     * modifiers).
     */
    private static function decodeReserved(string $string): string
    {
        return str_replace(self::$delimsPct, self::$delims, $string);
    }
}