<?php

declare(strict_types=1);

namespace GuzzleHttp\UriTemplate;

/**
 * Expands URI templates. Userland implementation of PECL uri_template.
 *
 * @see https://datatracker.ietf.org/doc/html/rfc6570
 */
final class UriTemplate
{
    private const RESERVED_OPERATORS = '=,!@|';
    private const SUPPORTED_OPERATORS = '+#./;?&';
    private const VARNAME_PATTERN = '(?:[A-Za-z0-9_]|%[0-9A-Fa-f]{2})(?:\.?(?:[A-Za-z0-9_]|%[0-9A-Fa-f]{2}))*';

    /**
     * @var array<string, array{prefix:string, joiner:string, query:bool}> Hash for quick operator lookups
     */
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

    /**
     * @param array<string,mixed> $variables Variables to use in the template expansion
     *
     * @throws \InvalidArgumentException When the template syntax is invalid
     * @throws \RuntimeException
     */
    public static function expand(string $template, array $variables): string
    {
        self::validateTemplateStructure($template);

        if (false === \strpos($template, '{')) {
            return $template;
        }

        /** @var string|null */
        $result = \preg_replace_callback(
            '/\{([^\}]+)\}/',
            self::expandMatchCallback($variables),
            $template
        );

        if (null === $result) {
            throw new \RuntimeException(\sprintf('Unable to process template: %s', \preg_last_error_msg()));
        }

        return $result;
    }

    /**
     * @param array<string,mixed> $variables Variables to use in the template expansion
     *
     * @return callable(string[]): string
     */
    private static function expandMatchCallback(array $variables): callable
    {
        return static function (array $matches) use ($variables): string {
            return self::expandMatch($matches, $variables);
        };
    }

    private static function validateTemplateStructure(string $template): void
    {
        $length = \strlen($template);

        for ($offset = 0; $offset < $length; ++$offset) {
            $char = $template[$offset];

            if ($char === '{') {
                $end = \strpos($template, '}', $offset + 1);

                if ($end === false) {
                    throw self::invalidTemplate($offset, 'unmatched "{"');
                }

                if ($end === $offset + 1) {
                    throw self::invalidTemplate($offset, 'empty expression');
                }

                $expression = \substr($template, $offset + 1, $end - $offset - 1);

                if (\strpos($expression, '{') !== false) {
                    throw self::invalidTemplate($offset, 'nested expressions are not allowed');
                }

                $offset = $end;
                continue;
            }

            if ($char === '}') {
                throw self::invalidTemplate($offset, 'unmatched "}"');
            }
        }
    }

    private static function invalidTemplate(int $offset, string $message): \InvalidArgumentException
    {
        return new \InvalidArgumentException(\sprintf(
            'Invalid URI template at offset %d: %s.',
            $offset,
            $message
        ));
    }

    /**
     * Process an expansion
     *
     * @param array<string,mixed> $variables Variables to use in the template expansion
     * @param string[]            $matches   Matches met in the preg_replace_callback
     *
     * @return string Returns the replacement string
     */
    private static function expandMatch(array $matches, array $variables): string
    {
        $replacements = [];
        $parsed = self::parseExpression($matches[1]);
        $prefix = self::$operatorHash[$parsed['operator']]['prefix'];
        $joiner = self::$operatorHash[$parsed['operator']]['joiner'];
        $useQuery = self::$operatorHash[$parsed['operator']]['query'];
        $allowReserved = $parsed['operator'] === '+' || $parsed['operator'] === '#';
        $allUndefined = true;

        foreach ($parsed['values'] as $value) {
            if (!isset($variables[$value['value']])) {
                continue;
            }

            $variable = $variables[$value['value']];
            $actuallyUseQuery = $useQuery;
            $expanded = '';

            if (\is_array($variable)) {
                $isAssoc = self::isAssoc($variable);
                $kvp = [];
                /** @var mixed $var */
                foreach ($variable as $key => $var) {
                    if ($isAssoc) {
                        $key = \rawurlencode((string) $key);
                        $isNestedArray = \is_array($var);
                    } else {
                        $isNestedArray = false;
                    }

                    if (!$isNestedArray) {
                        $var = self::encodeValue((string) $var, $allowReserved);
                    }

                    if ($value['modifier'] === '*') {
                        if ($isAssoc) {
                            if ($isNestedArray) {
                                // Nested arrays must allow for deeply nested structures.
                                $var = \http_build_query([$key => $var], '', '&', \PHP_QUERY_RFC3986);
                            } else {
                                $var = \sprintf('%s=%s', (string) $key, (string) $var);
                            }
                        } elseif ($key > 0 && $actuallyUseQuery) {
                            $var = \sprintf('%s=%s', $value['value'], (string) $var);
                        }
                    }

                    /** @var string $var */
                    $kvp[$key] = $var;
                }

                if (0 === \count($variable)) {
                    $actuallyUseQuery = false;
                } elseif ($value['modifier'] === '*') {
                    $expanded = \implode($joiner, $kvp);
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
                            $v = \sprintf('%s,%s', $k, $v);
                        }
                    }
                    $expanded = \implode(',', $kvp);
                }
            } else {
                $allUndefined = false;
                if ($value['modifier'] === ':' && isset($value['position'])) {
                    $variable = \substr((string) $variable, 0, $value['position']);
                }
                $expanded = self::encodeValue((string) $variable, $allowReserved);
            }

            if ($actuallyUseQuery) {
                if ($expanded === '' && $joiner !== '&') {
                    $expanded = $value['value'];
                } else {
                    $expanded = \sprintf('%s=%s', $value['value'], $expanded);
                }
            }

            $replacements[] = $expanded;
        }

        $ret = \implode($joiner, $replacements);

        if ('' === $ret) {
            // Spec section 3.2.4 and 3.2.5
            if (false === $allUndefined && ('#' === $prefix || '.' === $prefix)) {
                return $prefix;
            }
        } else {
            if ('' !== $prefix) {
                return \sprintf('%s%s', $prefix, $ret);
            }
        }

        return $ret;
    }

    /**
     * Parse an expression into parts
     *
     * @param string $expression Expression to parse
     *
     * @return array{operator:string, values:array<array{value:string, modifier:(''|'*'|':'), position?:int}>}
     */
    private static function parseExpression(string $expression): array
    {
        if ($expression === '') {
            throw self::invalidExpression($expression, 'empty expression');
        }

        $original = $expression;
        $operator = '';
        $first = $expression[0];

        if (isset(self::$operatorHash[$first])) {
            $operator = $first;
            /** @var string */
            $expression = \substr($expression, 1);
        } elseif (\strpos(self::RESERVED_OPERATORS, $first) !== false) {
            throw self::invalidExpression($original, \sprintf('unsupported operator "%s"', $first));
        }

        if ($expression === '') {
            throw self::invalidExpression($original, 'missing variable list');
        }

        $values = [];
        foreach (\explode(',', $expression) as $varspec) {
            if ($varspec === '') {
                throw self::invalidExpression($original, 'empty variable specifier');
            }

            $values[] = self::parseVarSpecLenientForNow($original, $varspec);
        }

        return ['operator' => $operator, 'values' => $values];
    }

    /**
     * @return array{value:string, modifier:(''|'*'|':'), position?:int}
     */
    private static function parseVarSpecLenientForNow(string $expression, string $varspec): array
    {
        if ($varspec !== \trim($varspec)) {
            throw self::invalidExpression($expression, \sprintf('invalid whitespace in variable specifier "%s"', $varspec));
        }

        if (\strpos(self::SUPPORTED_OPERATORS.self::RESERVED_OPERATORS, $varspec[0]) !== false) {
            throw self::invalidExpression($expression, \sprintf('invalid variable specifier "%s"', $varspec));
        }

        $colonPos = \strpos($varspec, ':');
        if ($colonPos !== false) {
            $name = (string) \substr($varspec, 0, $colonPos);
            self::assertValidVariableName($expression, $name, $varspec);

            return [
                'value' => $name,
                'modifier' => ':',
                'position' => (int) \substr($varspec, $colonPos + 1),
            ];
        }

        if (\substr($varspec, -1) === '*') {
            $name = (string) \substr($varspec, 0, -1);
            self::assertValidVariableName($expression, $name, $varspec);

            return ['modifier' => '*', 'value' => $name];
        }

        self::assertValidVariableName($expression, $varspec, $varspec);

        return ['value' => $varspec, 'modifier' => ''];
    }

    private static function assertValidVariableName(string $expression, string $name, string $varspec): void
    {
        if (\preg_match('/\A'.self::VARNAME_PATTERN.'\z/', $name) !== 1) {
            throw self::invalidExpression($expression, \sprintf('invalid variable specifier "%s"', $varspec));
        }
    }

    private static function invalidExpression(string $expression, string $message): \InvalidArgumentException
    {
        return new \InvalidArgumentException(\sprintf(
            'Invalid URI template expression "{%s}": %s.',
            $expression,
            $message
        ));
    }

    /**
     * Determines if an array is associative.
     *
     * This makes the assumption that input arrays are sequences or hashes.
     * This assumption is a tradeoff for accuracy in favor of speed, but it
     * should work in almost every case where input is supplied for a URI
     * template.
     */
    private static function isAssoc(array $array): bool
    {
        return $array && \array_keys($array)[0] !== 0;
    }

    private static function encodeValue(string $value, bool $allowReserved): string
    {
        if ($value === '') {
            return '';
        }

        $matches = [];
        if (\preg_match_all('/%[0-9A-Fa-f]{2}|./s', $value, $matches) === false) {
            throw new \RuntimeException('Unable to encode URI template value.');
        }

        $encoded = '';

        foreach ($matches[0] as $token) {
            if ($allowReserved && \preg_match('/\A%[0-9A-Fa-f]{2}\z/', $token) === 1) {
                $encoded .= $token;
                continue;
            }

            if (\preg_match('/\A[A-Za-z0-9._~-]\z/', $token) === 1) {
                $encoded .= $token;
                continue;
            }

            if ($allowReserved && \strlen($token) === 1 && \strpos(":/?#[]@!$&'()*+,;=", $token) !== false) {
                $encoded .= $token;
                continue;
            }

            $encoded .= \rawurlencode($token);
        }

        return $encoded;
    }
}
