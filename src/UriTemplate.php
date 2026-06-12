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
     * @var array<string, array{prefix:string, joiner:string, query:bool, ifemp:string}>
     */
    private const OPERATOR_HASH = [
        '' => ['prefix' => '', 'joiner' => ',', 'query' => false, 'ifemp' => ''],
        '+' => ['prefix' => '', 'joiner' => ',', 'query' => false, 'ifemp' => ''],
        '#' => ['prefix' => '#', 'joiner' => ',', 'query' => false, 'ifemp' => ''],
        '.' => ['prefix' => '.', 'joiner' => '.', 'query' => false, 'ifemp' => ''],
        '/' => ['prefix' => '/', 'joiner' => '/', 'query' => false, 'ifemp' => ''],
        ';' => ['prefix' => ';', 'joiner' => ';', 'query' => true, 'ifemp' => ''],
        '?' => ['prefix' => '?', 'joiner' => '&', 'query' => true, 'ifemp' => '='],
        '&' => ['prefix' => '&', 'joiner' => '&', 'query' => true, 'ifemp' => '='],
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
        $ifemp = self::OPERATOR_HASH[$parsed['operator']]['ifemp'];
        $allowReserved = $parsed['operator'] === '+' || $parsed['operator'] === '#';
        $hasDefinedVariable = false;

        foreach ($parsed['values'] as $value) {
            if (self::isUndefinedVariable($variables, $value['value'])) {
                continue;
            }

            $variable = $variables[$value['value']];

            self::assertVariableShape($value, $variable, $matches[1], $parsed['operator']);

            $actuallyUseQuery = $useQuery;
            $expanded = '';

            if (\is_array($variable)) {
                $isAssoc = self::isAssoc($variable);
                $kvp = [];
                /** @var mixed $var */
                foreach ($variable as $key => $var) {
                    if ($var === null) {
                        // Spec sections 2.3 and 2.4.2: only members with
                        // defined values are present in the expansion.
                        continue;
                    }

                    $memberPath = \sprintf('%s[%s]', $value['value'], (string) $key);

                    if ($isAssoc) {
                        $rawKey = (string) $key;
                        // Spec section 3.2.1: pair names are encoded in the
                        // same way as simple string values, so reserved
                        // expansion and fragment expansion keep reserved
                        // characters and pct-encoded triplets in names.
                        $key = self::encodeValue($rawKey, $allowReserved, $matches[1], $memberPath);
                        $isNestedArray = \is_array($var);
                    } else {
                        $isNestedArray = false;
                    }

                    if (!$isNestedArray) {
                        $var = self::encodeValue(self::stringifyValue($var), $allowReserved, $matches[1], $memberPath);
                    }

                    if ($value['modifier'] === '*') {
                        if ($isAssoc) {
                            if ($isNestedArray) {
                                // Nested arrays must allow for deeply nested structures.
                                $var = \http_build_query([$rawKey => $var], '', '&', \PHP_QUERY_RFC3986);
                                if ($var === '') {
                                    continue;
                                }
                            } elseif ($useQuery) {
                                $var = self::formatPair((string) $key, (string) $var, $ifemp);
                            } else {
                                $var = \sprintf('%s=%s', (string) $key, (string) $var);
                            }
                        } elseif ($useQuery) {
                            $var = self::formatPair($value['value'], (string) $var, $ifemp);
                        }
                    } elseif ($isAssoc) {
                        // When an associative array is encountered and the
                        // explode modifier is not set, then the result must be
                        // a comma separated list of keys followed by their
                        // respective values.
                        $var = \sprintf('%s,%s', (string) $key, (string) $var);
                    }

                    $kvp[] = (string) $var;
                }

                if ($kvp === []) {
                    continue;
                } elseif ($value['modifier'] === '*') {
                    $expanded = \implode($joiner, $kvp);
                    // Spec appendix A: exploded members carry their own name
                    // (and ifemp handling) above, so the expression-level
                    // name must not be prepended again.
                    $actuallyUseQuery = false;
                } else {
                    $expanded = \implode(',', $kvp);
                }
            } else {
                $expanded = self::stringifyValue($variable);

                if ($value['modifier'] === ':' && isset($value['position'])) {
                    $expanded = self::prefixValue($expanded, $value['position'], $matches[1], $value['value']);
                }

                $expanded = self::encodeValue($expanded, $allowReserved, $matches[1], $value['value']);
            }

            if ($actuallyUseQuery) {
                if (\is_array($variable)) {
                    // Spec sections 2.3 and 3.2.7 and appendix A: emptiness
                    // is tested on the variable's value before expansion, and
                    // a defined list or map is never an empty value, so "="
                    // is appended even when every member expands to the empty
                    // string.
                    $expanded = \sprintf('%s=%s', $value['value'], $expanded);
                } else {
                    $expanded = self::formatPair($value['value'], $expanded, $ifemp);
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
     * Cast a scalar or stringable variable value to its expansion string.
     *
     * Booleans expand as "1" and "0" so that false remains distinguishable
     * from the empty string and matches the http_build_query semantics used
     * by the nested query-array extension.
     *
     * @param mixed $value
     */
    private static function stringifyValue($value): string
    {
        if (\is_bool($value)) {
            return $value ? '1' : '0';
        }

        return (string) $value;
    }

    /**
     * Format a named (name, value) pair.
     *
     * Spec section 3.2.1: a pair whose value is the empty string is rendered
     * as the name followed by the operator's ifemp string ("=" for the
     * form-style "?" and "&" operators, nothing for ";").
     */
    private static function formatPair(string $name, string $value, string $ifemp): string
    {
        if ($value === '') {
            return $name.$ifemp;
        }

        return \sprintf('%s=%s', $name, $value);
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
        $result = \preg_match($pattern, $varspec, $matches);

        if ($result === false) {
            // A PCRE engine failure, such as an exhausted JIT stack or
            // backtrack limit on a very long variable name, is not a template
            // syntax error; spec section 2.3 places no length limit on
            // variable names.
            throw new \RuntimeException(\sprintf('Unable to parse variable specifier: %s', \preg_last_error_msg()));
        }

        if ($result !== 1) {
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
     * Determines if a referenced variable is undefined.
     *
     * Spec section 2.3: a list is undefined when it contains zero members,
     * and a map is undefined when it contains zero members or when all
     * member names are associated with undefined values. Lists whose
     * members are all null are treated the same way, like an empty list.
     * Spec section 3.2.1: undefined variables are ignored by the expansion
     * process, so they are skipped before varspec shape validation.
     *
     * @param array<string,mixed> $variables
     */
    private static function isUndefinedVariable(array $variables, string $name): bool
    {
        if (!\array_key_exists($name, $variables) || $variables[$name] === null) {
            return true;
        }

        if (!\is_array($variables[$name])) {
            return false;
        }

        /** @var mixed $member */
        foreach ($variables[$name] as $member) {
            if ($member !== null) {
                return false;
            }
        }

        return true;
    }

    private static function invalidVariable(string $expression, string $path, string $message): \InvalidArgumentException
    {
        return new \InvalidArgumentException(\sprintf(
            'Invalid URI template variable "%s" in "{%s}": %s.',
            self::sanitizeVariablePath($path),
            $expression,
            $message
        ));
    }

    /**
     * Make a variable member path safe to embed in an exception message.
     *
     * Member paths are built from raw array keys, so a path can contain
     * byte sequences that are not valid UTF-8. Escaping such bytes keeps
     * the exception message itself valid UTF-8 for consumers that
     * serialize messages, such as json_encode-based loggers.
     */
    private static function sanitizeVariablePath(string $path): string
    {
        if (\preg_match('//u', $path) === 1) {
            return $path;
        }

        $sanitized = '';

        for ($offset = 0, $length = \strlen($path); $offset < $length; ++$offset) {
            $ord = \ord($path[$offset]);

            $sanitized .= $ord >= 0x20 && $ord <= 0x7E ? $path[$offset] : \sprintf('\x%02X', $ord);
        }

        return $sanitized;
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

            if ($member === null || self::isScalarLike($member)) {
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

            if ($member === null || self::isScalarLike($member)) {
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
            if ($member === null) {
                // Spec sections 2.3 and 2.4.2: only members with defined
                // values are present in the expansion, so null members are
                // omitted before their keys are validated, matching the
                // handling of null members in top-level maps.
                continue;
            }

            $memberPath = \sprintf('%s[%s]', $path, (string) $key);

            if (\is_string($key) && \preg_match('//u', $key) !== 1) {
                throw self::invalidVariable($expression, $memberPath, 'variable values must be valid UTF-8');
            }

            if (\is_scalar($member)) {
                if (\is_string($member) && \preg_match('//u', $member) !== 1) {
                    throw self::invalidVariable($expression, $memberPath, 'variable values must be valid UTF-8');
                }

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

        $tokens = $matches[0];
        $count = \count($tokens);
        $prefix = '';
        $index = 0;

        // Spec sections 2.4.1 and 3.2.1: prefix lengths count each Unicode
        // code point as one character so that the value is never split in
        // mid-character, so consecutive pct-encoded triplets that encode a
        // single UTF-8 code point are kept together as one character.
        for ($taken = 0; $taken < $length && $index < $count; ++$taken) {
            $width = 1;

            if (\strlen($tokens[$index]) === 3 && $tokens[$index][0] === '%') {
                $width = self::pctEncodedCodePointTripletCount($tokens, $index);
            }

            while ($width-- > 0) {
                $prefix .= $tokens[$index];
                ++$index;
            }
        }

        return $prefix;
    }

    /**
     * Count the consecutive pct-encoded triplets starting at the index that
     * together encode a single Unicode code point as UTF-8.
     *
     * Returns 1 when the triplet does not begin such a sequence, so triplets
     * that do not participate in a multi-octet-encoded character keep
     * counting as one character each.
     *
     * @param list<string> $tokens
     */
    private static function pctEncodedCodePointTripletCount(array $tokens, int $index): int
    {
        $lead = (int) \hexdec(\substr($tokens[$index], 1));

        if ($lead >= 0xC2 && $lead <= 0xDF) {
            $octets = 2;
        } elseif ($lead >= 0xE0 && $lead <= 0xEF) {
            $octets = 3;
        } elseif ($lead >= 0xF0 && $lead <= 0xF4) {
            $octets = 4;
        } else {
            return 1;
        }

        $decoded = \chr($lead);

        for ($offset = 1; $offset < $octets; ++$offset) {
            $token = $tokens[$index + $offset] ?? '';

            if (\strlen($token) !== 3 || $token[0] !== '%') {
                return 1;
            }

            $decoded .= \chr((int) \hexdec(\substr($token, 1)));
        }

        return \preg_match('/\A.\z/us', $decoded) === 1 ? $octets : 1;
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
            // Apostrophe is a valid literal per RFC 6570 erratum 6937 (verified).
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

    private static function encodeValue(string $value, bool $allowReserved, string $expression, string $name): string
    {
        if ($value === '') {
            return '';
        }

        // Spec section 1.6: values are encoded as UTF-8 before pct-encoding,
        // so byte sequences that are not valid UTF-8 cannot be expanded.
        if (\preg_match('//u', $value) !== 1) {
            throw self::invalidVariable($expression, $name, 'variable values must be valid UTF-8');
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
