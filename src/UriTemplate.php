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
    private const MAX_VARIABLE_DEPTH = 64;

    /**
     * Hash for quick operator lookups.
     *
     * @var array<string, array{prefix:string, joiner:string, query:bool}>
     */
    private const OPERATOR_HASH = [
        '' => ['prefix' => '', 'joiner' => ',', 'query' => false],
        '+' => ['prefix' => '', 'joiner' => ',', 'query' => false],
        '#' => ['prefix' => '#', 'joiner' => ',', 'query' => false],
        '.' => ['prefix' => '.', 'joiner' => '.', 'query' => false],
        '/' => ['prefix' => '/', 'joiner' => '/', 'query' => false],
        ';' => ['prefix' => ';', 'joiner' => ';', 'query' => true],
        '?' => ['prefix' => '?', 'joiner' => '&', 'query' => true],
        '&' => ['prefix' => '&', 'joiner' => '&', 'query' => true],
    ];

    private function __construct()
    {
    }

    /**
     * @param array<string,mixed> $variables Variables to use in the template expansion
     *
     * @throws \InvalidArgumentException When the template syntax or referenced variable shape is invalid
     * @throws \RuntimeException
     */
    public static function expand(string $template, array $variables): string
    {
        $template = self::prepareTemplate($template);

        if (false === \strpos($template, '{')) {
            return $template;
        }

        $callback = self::expandMatchCallback($variables);

        /** @var string|null */
        $result = \preg_replace_callback(
            '/\{([^\}]+)\}/',
            static function (array $matches) use ($callback): string {
                return $callback($matches);
            },
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
     * @return \Closure(array{0: string, 1: string}): string
     */
    private static function expandMatchCallback(array $variables): \Closure
    {
        return static function (array $matches) use ($variables): string {
            /** @var array{0: string, 1: string} $matches */
            return self::expandMatch($matches, $variables);
        };
    }

    private static function prepareTemplate(string $template): string
    {
        $length = \strlen($template);
        $prepared = '';
        $literalStart = 0;

        for ($offset = 0; $offset < $length; ++$offset) {
            $char = $template[$offset];

            if ($char === '{') {
                $prepared .= self::encodeLiteralSegment(
                    \substr($template, $literalStart, $offset - $literalStart),
                    $literalStart
                );

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

                $prepared .= \substr($template, $offset, $end - $offset + 1);
                $offset = $end;
                $literalStart = $end + 1;

                continue;
            }

            if ($char === '}') {
                throw self::invalidTemplate($offset, 'unmatched "}"');
            }
        }

        return $prepared.self::encodeLiteralSegment(\substr($template, $literalStart), $literalStart);
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
     * @param array<string,mixed>         $variables Variables to use in the template expansion
     * @param array{0: string, 1: string} $matches   Matches met in the preg_replace_callback
     *
     * @return string Returns the replacement string
     */
    private static function expandMatch(array $matches, array $variables): string
    {
        $replacements = [];
        $parsed = self::parseExpression($matches[1]);
        $prefix = self::OPERATOR_HASH[$parsed['operator']]['prefix'];
        $joiner = self::OPERATOR_HASH[$parsed['operator']]['joiner'];
        $useQuery = self::OPERATOR_HASH[$parsed['operator']]['query'];
        $allowReserved = $parsed['operator'] === '+' || $parsed['operator'] === '#';
        $hasDefinedVariable = false;

        foreach ($parsed['values'] as $value) {
            if (self::isUndefinedVariable($variables, $value['value'])) {
                continue;
            }

            $variable = $variables[$value['value']];

            if (\is_array($variable) && $variable === []) {
                continue;
            }

            self::assertVariableShape($value, $variable, $matches[1], $parsed['operator']);

            $actuallyUseQuery = $useQuery;
            $expanded = '';

            if (\is_array($variable)) {
                $isAssoc = self::isAssoc($variable);
                $kvp = [];
                /** @var mixed $var */
                foreach ($variable as $key => $var) {
                    if ($isAssoc) {
                        $rawKey = (string) $key;
                        $key = \rawurlencode($rawKey);
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
                                $var = \http_build_query([$rawKey => $var], '', '&', \PHP_QUERY_RFC3986);
                                if ($var === '') {
                                    continue;
                                }
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

                if ($kvp === []) {
                    continue;
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
                if ($value['modifier'] === ':' && isset($value['position'])) {
                    $variable = self::prefixValue((string) $variable, $value['position'], $matches[1], $value['value']);
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

            $hasDefinedVariable = true;

            $replacements[] = $expanded;
        }

        $ret = \implode($joiner, $replacements);

        // Spec section 3.2.1 and appendix A: the operator's first string is
        // appended once any variable in the expression is defined, even when
        // every defined value expands to an empty string.
        if ('' !== $prefix && $hasDefinedVariable) {
            return \sprintf('%s%s', $prefix, $ret);
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

        if (isset(self::OPERATOR_HASH[$first])) {
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

            $values[] = self::parseVarSpec($original, $varspec);
        }

        return ['operator' => $operator, 'values' => $values];
    }

    /**
     * @return array{value:string, modifier:(''|'*'|':'), position?:int}
     */
    private static function parseVarSpec(string $expression, string $varspec): array
    {
        if ($varspec !== \trim($varspec)) {
            throw self::invalidExpression($expression, \sprintf('invalid whitespace in variable specifier "%s"', $varspec));
        }

        if (\strpos(self::SUPPORTED_OPERATORS.self::RESERVED_OPERATORS, $varspec[0]) !== false) {
            throw self::invalidExpression($expression, \sprintf('invalid variable specifier "%s"', $varspec));
        }

        $matches = [];
        $pattern = '/\A('.self::VARNAME_PATTERN.')(?::([1-9][0-9]{0,3})|(\*))?\z/';

        if (\preg_match($pattern, $varspec, $matches) !== 1) {
            throw self::invalidExpression($expression, \sprintf('invalid variable specifier "%s"', $varspec));
        }

        if (isset($matches[2]) && $matches[2] !== '') {
            return [
                'value' => $matches[1],
                'modifier' => ':',
                'position' => (int) $matches[2],
            ];
        }

        if (isset($matches[3])) {
            return ['modifier' => '*', 'value' => $matches[1]];
        }

        return ['value' => $matches[1], 'modifier' => ''];
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
     * @param array<string,mixed> $variables
     */
    private static function isUndefinedVariable(array $variables, string $name): bool
    {
        return !\array_key_exists($name, $variables) || $variables[$name] === null;
    }

    private static function invalidVariable(string $expression, string $path, string $message): \InvalidArgumentException
    {
        return new \InvalidArgumentException(\sprintf(
            'Invalid URI template variable "%s" in "{%s}": %s.',
            $path,
            $expression,
            $message
        ));
    }

    /**
     * @param array{value:string, modifier:(''|'*'|':'), position?:int} $varspec
     * @param mixed                                                     $variable
     */
    private static function assertVariableShape(array $varspec, $variable, string $expression, string $operator): void
    {
        if (self::isScalarLike($variable)) {
            return;
        }

        if (!\is_array($variable)) {
            throw self::invalidVariable(
                $expression,
                $varspec['value'],
                \sprintf(
                    'expected scalar, stringable object, list, or associative array; got %s',
                    \get_debug_type($variable)
                )
            );
        }

        if ($varspec['modifier'] === ':') {
            throw self::invalidVariable(
                $expression,
                $varspec['value'],
                'prefix modifier is not applicable to composite values'
            );
        }

        $isAssoc = self::isAssoc($variable);

        if (!$isAssoc) {
            self::assertListShape($varspec['value'], $variable, $expression);

            return;
        }

        $allowNestedArrays = $varspec['modifier'] === '*' && ($operator === '?' || $operator === '&');

        self::assertMapShape($varspec['value'], $variable, $expression, $allowNestedArrays, 0);
    }

    /**
     * @param mixed $value
     */
    private static function isScalarLike($value): bool
    {
        return \is_scalar($value) || (\is_object($value) && \method_exists($value, '__toString'));
    }

    /**
     * @param array<array-key,mixed> $value
     */
    private static function assertListShape(string $path, array $value, string $expression): void
    {
        foreach ($value as $index => $member) {
            $memberPath = \sprintf('%s[%d]', $path, $index);

            if ($member === null) {
                throw self::invalidVariable($expression, $memberPath, 'nested null values are not supported');
            }

            if (self::isScalarLike($member)) {
                continue;
            }

            throw self::invalidVariable(
                $expression,
                $memberPath,
                \sprintf('expected scalar or stringable object; got %s', \get_debug_type($member))
            );
        }
    }

    /**
     * @param array<array-key,mixed> $value
     */
    private static function assertMapShape(
        string $path,
        array $value,
        string $expression,
        bool $allowNestedArrays,
        int $depth
    ): void {
        if ($depth > self::MAX_VARIABLE_DEPTH) {
            throw self::invalidVariable($expression, $path, 'maximum variable nesting depth exceeded');
        }

        foreach ($value as $key => $member) {
            $memberPath = \sprintf('%s[%s]', $path, (string) $key);

            if ($member === null) {
                throw self::invalidVariable($expression, $memberPath, 'nested null values are not supported');
            }

            if (self::isScalarLike($member)) {
                continue;
            }

            if (\is_array($member) && $allowNestedArrays) {
                self::assertNestedQueryShape($memberPath, $member, $expression, $depth + 1);
                continue;
            }

            throw self::invalidVariable(
                $expression,
                $memberPath,
                \sprintf('expected scalar%s; got %s', $allowNestedArrays ? ', stringable object, or nested array' : ' or stringable object', \get_debug_type($member))
            );
        }
    }

    /**
     * @param array<array-key,mixed> $value
     */
    private static function assertNestedQueryShape(string $path, array $value, string $expression, int $depth): void
    {
        if ($depth > self::MAX_VARIABLE_DEPTH) {
            throw self::invalidVariable($expression, $path, 'maximum variable nesting depth exceeded');
        }

        foreach ($value as $key => $member) {
            $memberPath = \sprintf('%s[%s]', $path, (string) $key);

            if ($member === null) {
                throw self::invalidVariable($expression, $memberPath, 'nested null values are not supported');
            }

            if (\is_scalar($member)) {
                continue;
            }

            if (\is_array($member)) {
                self::assertNestedQueryShape($memberPath, $member, $expression, $depth + 1);
                continue;
            }

            throw self::invalidVariable(
                $expression,
                $memberPath,
                \sprintf('expected scalar or nested array; got %s', \get_debug_type($member))
            );
        }
    }

    /**
     * Determines if an array should be expanded as a map.
     *
     * @param array<array-key,mixed> $array
     */
    private static function isAssoc(array $array): bool
    {
        if ($array === []) {
            return false;
        }

        return \array_keys($array) !== \range(0, \count($array) - 1);
    }

    private static function prefixValue(string $value, int $length, string $expression, string $name): string
    {
        if ($value === '') {
            return '';
        }

        $matches = [];
        $result = \preg_match_all('/%[0-9A-Fa-f]{2}|./us', $value, $matches);

        if ($result === false || \preg_last_error() !== \PREG_NO_ERROR) {
            throw self::invalidVariable($expression, $name, 'prefix modifier requires valid UTF-8');
        }

        return \implode('', \array_slice($matches[0], 0, $length));
    }

    private static function encodeLiteralSegment(string $literal, int $baseOffset): string
    {
        if ($literal === '') {
            return '';
        }

        $matches = [];
        $result = \preg_match_all('/%[0-9A-Fa-f]{2}|./us', $literal, $matches, \PREG_OFFSET_CAPTURE);

        if ($result === false || \preg_last_error() !== \PREG_NO_ERROR) {
            throw self::invalidTemplate($baseOffset, 'literal text must be valid UTF-8');
        }

        $encoded = '';
        $position = 0;

        foreach ($matches[0] as $match) {
            $token = $match[0];
            $offset = $match[1];

            if ($offset < 0) {
                throw self::invalidTemplate($baseOffset + $position, 'invalid literal character');
            }

            if ($offset !== $position) {
                throw self::invalidTemplate($baseOffset + $position, 'invalid literal character');
            }

            $position = $offset + \strlen($token);

            if (\preg_match('/\A%[0-9A-Fa-f]{2}\z/', $token) === 1) {
                $encoded .= $token;
                continue;
            }

            if (\strlen($token) === 1) {
                if (self::isAllowedAsciiLiteral($token)) {
                    $encoded .= $token;
                    continue;
                }

                throw self::invalidTemplate($baseOffset + $offset, $token === '%' ? 'invalid percent-encoded triplet' : 'invalid literal character');
            }

            if (!self::isAllowedUnicodeLiteral($token)) {
                throw self::invalidTemplate($baseOffset + $offset, 'invalid literal character');
            }

            $encoded .= \rawurlencode($token);
        }

        if ($position !== \strlen($literal)) {
            throw self::invalidTemplate($baseOffset + $position, 'invalid literal character');
        }

        return $encoded;
    }

    private static function isAllowedAsciiLiteral(string $char): bool
    {
        $ord = \ord($char);

        return $ord === 0x21
            || ($ord >= 0x23 && $ord <= 0x24)
            || $ord === 0x26
            // Upstream RFC example fixtures include apostrophes as literal text.
            || $ord === 0x27
            || ($ord >= 0x28 && $ord <= 0x3B)
            || $ord === 0x3D
            || ($ord >= 0x3F && $ord <= 0x5B)
            || $ord === 0x5D
            || $ord === 0x5F
            || ($ord >= 0x61 && $ord <= 0x7A)
            || $ord === 0x7E;
    }

    private static function isAllowedUnicodeLiteral(string $char): bool
    {
        return \preg_match('/\A(?:[\x{A0}-\x{D7FF}\x{E000}-\x{FDCF}\x{FDF0}-\x{FFEF}]|[\x{10000}-\x{1FFFD}\x{20000}-\x{2FFFD}\x{30000}-\x{3FFFD}\x{40000}-\x{4FFFD}\x{50000}-\x{5FFFD}\x{60000}-\x{6FFFD}\x{70000}-\x{7FFFD}\x{80000}-\x{8FFFD}\x{90000}-\x{9FFFD}\x{A0000}-\x{AFFFD}\x{B0000}-\x{BFFFD}\x{C0000}-\x{CFFFD}\x{D0000}-\x{DFFFD}\x{E1000}-\x{EFFFD}\x{F0000}-\x{FFFFD}\x{100000}-\x{10FFFD}])\z/u', $char) === 1;
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
