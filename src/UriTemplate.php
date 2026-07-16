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
     * @return array<array-key, mixed>
     */
    public function __serialize(): array
    {
        throw new \LogicException(static::class.' should never be serialized');
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public function __unserialize(array $data): void
    {
        throw new \LogicException(static::class.' should never be unserialized');
    }

    /**
     * @param array<string, mixed> $variables Variables to use in the template expansion
     *
     * @throws \InvalidArgumentException When the template syntax or referenced variable shape is invalid
     * @throws \RuntimeException
     */
    public static function expand(string $template, array $variables): string
    {
        [$template, $references] = self::prepareTemplate($template);

        if ($references === []) {
            return $template;
        }

        $expressions = self::parseExpressions($references);
        $values = self::formValues($references, $expressions, $variables);

        /** @var string|null */
        $result = \preg_replace_callback(
            '/\{([^\}]+)\}/',
            static function (array $matches) use ($expressions, $values): string {
                return self::expandMatch($matches, $expressions, $values);
            },
            $template
        );

        if (null === $result) {
            throw new \RuntimeException(\sprintf('Unable to process template: %s', \preg_last_error_msg()));
        }

        return $result;
    }

    /**
     * Parse and validate every expression of the template, in template order.
     *
     * Expression text such as "0" is a canonical decimal integer string,
     * which PHP stores under an integer array key.
     *
     * @param list<string> $references The expression text of each expression, in template order
     *
     * @return array<array-key, array{operator:string, values:array<array{value:string, modifier:(''|'*'|':'), position?:int}>}>
     */
    private static function parseExpressions(array $references): array
    {
        $expressions = [];

        foreach ($references as $expression) {
            if (!isset($expressions[$expression])) {
                $expressions[$expression] = self::parseExpression($expression);
            }
        }

        return $expressions;
    }

    /**
     * Detach and form every referenced variable value before expansion.
     *
     * Spec section 3: each variable's value is formed prior to template
     * expansion. Definedness is bound and raw values are detached, in
     * template order, before any __toString() method runs, so an object
     * converted while values are formed cannot change another referenced
     * variable through a PHP reference. Stringable objects are then
     * converted to strings and scalars to their expansion strings, once
     * per value position, in template order, and string values and map
     * keys are validated as UTF-8 in the same pass, so a repeated variable
     * keeps a static value throughout the expansion and value errors
     * surface in member order. Undefined variables are stored as null.
     *
     * @param list<string>                                                                                                      $references  The expression text of each expression, in template order
     * @param array<array-key, array{operator:string, values:array<array{value:string, modifier:(''|'*'|':'), position?:int}>}> $expressions
     * @param array<array-key, mixed>                                                                                           $variables
     *
     * @return array<array-key, mixed>
     */
    private static function formValues(array $references, array $expressions, array $variables): array
    {
        /** @var array<array-key, mixed> $detached */
        $detached = [];
        /** @var list<array{string, array{value:string, modifier:(''|'*'|':'), position?:int}, string, string}> $order */
        $order = [];

        foreach ($references as $expression) {
            foreach ($expressions[$expression]['values'] as $varspec) {
                $name = $varspec['value'];

                if (\array_key_exists($name, $detached)) {
                    continue;
                }

                if (self::isUndefinedVariable($variables, $name)) {
                    $detached[$name] = null;
                    continue;
                }

                $operator = $expressions[$expression]['operator'];
                /** @var mixed $raw */
                $raw = $variables[$name];
                $membersMayNest = \is_array($raw)
                    && $varspec['modifier'] === '*'
                    && ($operator === '?' || $operator === '&')
                    && self::isAssoc($raw);

                $detached[$name] = self::detachValue($raw, $membersMayNest, 0);
                $order[] = [$name, $varspec, $expression, $operator];
            }
        }

        $values = $detached;

        foreach ($order as [$name, $varspec, $expression, $operator]) {
            $values[$name] = self::normalizeVariableShape($varspec, $detached[$name], $expression, $operator);
        }

        return $values;
    }

    /**
     * Detach a raw variable value from the caller's variables array.
     *
     * Admissible arrays are rebuilt so that PHP references held by the
     * caller cannot change the value after it has been read, and scalars
     * are copied. Object handles are kept as they are; stringable objects
     * are resolved later, while values are formed. Array members outside a
     * nested query array, and levels deeper than the nesting limit, are
     * kept as they are: shape validation rejects them by type or depth
     * without reading their contents, and rebuilding them could
     * materialize a copy-on-write shared array graph of unbounded logical
     * size.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    private static function detachValue($value, bool $membersMayNest, int $depth)
    {
        if (!\is_array($value) || $depth > self::MAX_VARIABLE_DEPTH) {
            return $value;
        }

        /** @var array<array-key, mixed> $detached */
        $detached = [];

        /** @var mixed $member */
        foreach ($value as $key => $member) {
            $detached[$key] = \is_array($member) && !$membersMayNest
                ? $member
                : self::detachValue($member, true, $depth + 1);
        }

        return $detached;
    }

    /**
     * Validate and encode the template's literal text and collect its
     * distinct expressions in first-occurrence order.
     *
     * Only distinct expression text is collected, so a template that
     * repeats an expression many times does not allocate storage
     * proportional to the number of occurrences.
     *
     * @return array{string, list<string>}
     */
    private static function prepareTemplate(string $template): array
    {
        $length = \strlen($template);
        $prepared = '';
        $literalStart = 0;
        $references = [];
        $seen = [];

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

                if (\str_contains($expression, '{')) {
                    throw self::invalidTemplate($offset, 'nested expressions are not allowed');
                }

                if (!isset($seen[$expression])) {
                    $seen[$expression] = true;
                    $references[] = $expression;
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

        return [$prepared.self::encodeLiteralSegment(\substr($template, $literalStart), $literalStart), $references];
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
     * @param array{0: string, 1: string}                                                                                       $matches     Matches met in the preg_replace_callback
     * @param array<array-key, array{operator:string, values:array<array{value:string, modifier:(''|'*'|':'), position?:int}>}> $expressions Parsed expressions of the template
     * @param array<array-key, mixed>                                                                                           $values      Variable values formed before the expansion began
     *
     * @return string Returns the replacement string
     */
    private static function expandMatch(array $matches, array $expressions, array $values): string
    {
        $replacements = [];
        $parsed = $expressions[$matches[1]];
        $prefix = self::OPERATOR_HASH[$parsed['operator']]['prefix'];
        $joiner = self::OPERATOR_HASH[$parsed['operator']]['joiner'];
        $useQuery = self::OPERATOR_HASH[$parsed['operator']]['query'];
        $ifemp = self::OPERATOR_HASH[$parsed['operator']]['ifemp'];
        $allowReserved = $parsed['operator'] === '+' || $parsed['operator'] === '#';
        $hasDefinedVariable = false;

        foreach ($parsed['values'] as $value) {
            if ($values[$value['value']] === null) {
                continue;
            }

            /** @var mixed $variable */
            $variable = $values[$value['value']];

            // Varspec-specific checks, such as the prefix-on-composite
            // rule, run on every occurrence against the formed value, so an
            // occurrence with an inapplicable modifier throws no matter
            // where it appears in the template.
            self::normalizeVariableShape($value, $variable, $matches[1], $parsed['operator']);

            $actuallyUseQuery = $useQuery;
            $expanded = '';
            $kvp = [];

            if (\is_array($variable)) {
                $isAssoc = self::isAssoc($variable);
                /** @var mixed $var */
                foreach ($variable as $key => $var) {
                    if ($var === null) {
                        // Spec section 3.2.1: a list expands "the defined
                        // member string values", and spec section 2.4.2:
                        // "only the defined pairs are present in the
                        // expansion", so undefined members are skipped. A
                        // list whose members are all skipped stays defined
                        // and expands to an empty member list.
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
                                // Members were converted to their expansion
                                // strings while values were formed, so
                                // http_build_query's own locale-sensitive
                                // float conversion is never used.
                                $var = \http_build_query([$rawKey => $var], '', '&', \PHP_QUERY_RFC3986);
                                if ($var === '') {
                                    continue;
                                }
                            } else {
                                // Spec section 3.2.1: every exploded pair
                                // follows the operator's ifemp rule; the
                                // non-normative appendix A algorithm, which
                                // always renders name=value, conflicts and
                                // is not followed.
                                $var = self::formatPair((string) $key, (string) $var, $ifemp);
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

                if ($kvp === [] && $isAssoc) {
                    // Spec section 2.3: a map whose member names are all
                    // associated with undefined values is undefined. Nested
                    // query maps whose members are all empty or all-null
                    // nested arrays reach this point because those members
                    // are arrays rather than null, so they are skipped here
                    // instead of in isUndefinedVariable().
                    continue;
                } elseif ($value['modifier'] === '*') {
                    $expanded = \implode($joiner, $kvp);
                    // Spec section 3.2.1: exploded members carry their own
                    // name (and ifemp handling) above, so the
                    // expression-level name must not be prepended again.
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
                    if ($kvp === []) {
                        // Spec section 3.2.7: "=" is appended only for a
                        // non-empty value, and a composite value is empty
                        // only when it contains no defined members, so the
                        // operator's ifemp string is used instead.
                        $expanded = $value['value'].$ifemp;
                    } else {
                        // Spec sections 2.3 and 3.2.7 and appendix A:
                        // emptiness is tested on the variable's value before
                        // expansion, and a composite with a defined member
                        // is never an empty value, so "=" is appended even
                        // when every member expands to the empty string.
                        $expanded = \sprintf('%s=%s', $value['value'], $expanded);
                    }
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
     * Floats are formatted with "." as the decimal separator regardless of
     * the process locale, because the plain float-to-string cast honors
     * LC_NUMERIC before PHP 8.0.
     *
     * @param mixed $value
     */
    private static function stringifyValue($value): string
    {
        if (\is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (\is_float($value)) {
            return self::stringifyFloat($value);
        }

        return (string) $value;
    }

    /**
     * Convert a float to its expansion string independently of the locale.
     *
     * Before PHP 8.0 the float-to-string cast honors LC_NUMERIC, so a
     * comma-decimal locale such as de_DE renders 3.5 as "3,5". Normalizing
     * the locale decimal separator back to "." keeps expansion output
     * deterministic across runtimes while preserving the precision-dependent
     * formatting of the cast.
     */
    private static function stringifyFloat(float $value): string
    {
        $string = (string) $value;
        $decimalPoint = \localeconv()['decimal_point'] ?? '.';

        if ('.' !== $decimalPoint && '' !== $decimalPoint) {
            $string = \str_replace($decimalPoint, '.', $string);
        }

        return $string;
    }

    /**
     * Format a named (name, value) pair.
     *
     * Spec section 3.2.1: a pair whose value is the empty string is rendered
     * as the name followed by the operator's ifemp string ("=" for the
     * form-style "?" and "&" operators, nothing for all other operators).
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
        } elseif (\str_contains(self::RESERVED_OPERATORS, $first)) {
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
        if ($varspec !== \trim($varspec, " \t\n\r\v\f")) {
            throw self::invalidExpression($expression, \sprintf('invalid whitespace in variable specifier "%s"', $varspec));
        }

        if (\str_contains(self::SUPPORTED_OPERATORS.self::RESERVED_OPERATORS, $varspec[0])) {
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
            self::escapeInvalidUtf8ForMessage($expression),
            self::escapeInvalidUtf8ForMessage($message)
        ));
    }

    /**
     * Determines if a referenced variable is undefined.
     *
     * Spec section 2.3: a list is undefined only when it contains zero
     * members, while a map is undefined when it contains zero members or
     * when all member names are associated with undefined values. A
     * non-empty list whose members are all null is therefore a defined
     * variable with no defined members. Spec section 3.2.1: undefined
     * variables are ignored by the expansion process, so they are skipped
     * before varspec shape validation.
     *
     * @param array<string, mixed> $variables
     */
    private static function isUndefinedVariable(array $variables, string $name): bool
    {
        if (!\array_key_exists($name, $variables) || $variables[$name] === null) {
            return true;
        }

        if (!\is_array($variables[$name])) {
            return false;
        }

        if ($variables[$name] === []) {
            return true;
        }

        if (!self::isAssoc($variables[$name])) {
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
            self::escapeInvalidUtf8ForMessage($path),
            self::escapeInvalidUtf8ForMessage($expression),
            $message
        ));
    }

    /**
     * Escape unsafe diagnostic text before embedding it in an exception message.
     *
     * ASCII control bytes are always escaped as \xHH. Text that is not valid
     * UTF-8 additionally has all bytes outside printable ASCII escaped.
     */
    private static function escapeInvalidUtf8ForMessage(string $value): string
    {
        $isValidUtf8 = \preg_match('//u', $value) === 1;
        $sanitized = '';

        for ($offset = 0, $length = \strlen($value); $offset < $length; ++$offset) {
            $ord = \ord($value[$offset]);
            $isSafeByte = $ord >= 0x20 && $ord !== 0x7F && ($isValidUtf8 || $ord <= 0x7E);

            $sanitized .= $isSafeByte ? $value[$offset] : \sprintf('\x%02X', $ord);
        }

        return $sanitized;
    }

    /**
     * Validate a referenced variable and return its snapshot.
     *
     * Stringable objects, including stringable members of lists and maps,
     * are converted to strings exactly once, at validation time, so a
     * repeated variable keeps a static value throughout the expansion and a
     * mutating __toString() cannot bypass validation. Later occurrences of
     * the same variable revalidate the returned snapshot against their own
     * varspec and discard the result, so varspec-specific checks such as
     * the prefix-on-composite rule run on every occurrence.
     *
     * @param array{value:string, modifier:(''|'*'|':'), position?:int} $varspec
     * @param mixed                                                     $variable
     *
     * @return mixed
     */
    private static function normalizeVariableShape(array $varspec, $variable, string $expression, string $operator)
    {
        if (self::isScalarLike($variable)) {
            if (\is_float($variable) && !\is_finite($variable)) {
                throw self::invalidVariable($expression, $varspec['value'], 'non-finite floats are not supported');
            }

            return self::formScalarLike($variable, $expression, $varspec['value']);
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

        if (!self::isAssoc($variable)) {
            return self::normalizeListShape($varspec['value'], $variable, $expression);
        }

        $allowNestedArrays = $varspec['modifier'] === '*' && ($operator === '?' || $operator === '&');

        return self::normalizeMapShape($varspec['value'], $variable, $expression, $allowNestedArrays, 0);
    }

    /**
     * @param mixed $value
     */
    private static function isScalarLike($value): bool
    {
        return \is_scalar($value) || (\is_object($value) && \method_exists($value, '__toString'));
    }

    /**
     * Form a null, scalar, or stringable value while values are formed.
     *
     * Stringable objects are resolved to strings once per value position
     * here, so their __toString() methods are never called again during
     * expansion, and scalars are converted to their expansion strings, so
     * repeated occurrences render identically even when a __toString()
     * method changes the float precision or the locale mid-expansion.
     * String values are validated as UTF-8 in the same pass, per spec
     * section 1.6, so all value errors surface in member order while
     * values are formed. Null is returned unchanged because it marks an
     * undefined member.
     *
     * @param mixed $value
     */
    private static function formScalarLike($value, string $expression, string $path): ?string
    {
        if ($value === null) {
            return null;
        }

        if (\is_object($value) && \method_exists($value, '__toString')) {
            $value = (string) $value;
        }

        if (\is_string($value)) {
            self::assertValidVariableUtf8($value, $expression, $path);

            return $value;
        }

        return self::stringifyValue($value);
    }

    /**
     * Validate a list variable and snapshot its members.
     *
     * Rebuilding the list resolves stringable members once per value
     * position and breaks PHP references held by the caller's array, so
     * members mutated after the snapshot is taken cannot change the
     * expansion.
     *
     * @param array<array-key, mixed> $value
     *
     * @return array<array-key, mixed>
     */
    private static function normalizeListShape(string $path, array $value, string $expression): array
    {
        $normalized = [];

        foreach ($value as $index => $member) {
            $memberPath = \sprintf('%s[%d]', $path, $index);

            if ($member === null || self::isScalarLike($member)) {
                if (\is_float($member) && !\is_finite($member)) {
                    throw self::invalidVariable($expression, $memberPath, 'non-finite floats are not supported');
                }

                $normalized[] = self::formScalarLike($member, $expression, $memberPath);
                continue;
            }

            throw self::invalidVariable(
                $expression,
                $memberPath,
                \sprintf('expected scalar or stringable object; got %s', \get_debug_type($member))
            );
        }

        return $normalized;
    }

    /**
     * Validate a map variable and snapshot its members.
     *
     * Rebuilding the map resolves stringable members once per value
     * position and breaks PHP references held by the caller's array, so
     * members mutated after the snapshot is taken cannot change the
     * expansion.
     *
     * @param array<array-key, mixed> $value
     *
     * @return array<array-key, mixed>
     */
    private static function normalizeMapShape(
        string $path,
        array $value,
        string $expression,
        bool $allowNestedArrays,
        int $depth
    ): array {
        if ($depth > self::MAX_VARIABLE_DEPTH) {
            throw self::invalidVariable($expression, $path, 'maximum variable nesting depth exceeded');
        }

        $normalized = [];

        foreach ($value as $key => $member) {
            if ($member === null) {
                // Spec section 2.4.2: only pairs with defined values are
                // present in the expansion, so null members are omitted
                // before their keys are validated, matching the handling
                // of null members in nested query arrays.
                $normalized[$key] = null;
                continue;
            }

            $memberPath = \sprintf('%s[%s]', $path, (string) $key);

            if (\is_string($key)) {
                // Spec section 3.2.1: pair names are encoded like simple
                // string values, so keys are validated as UTF-8 while
                // values are formed, before their members.
                self::assertValidVariableUtf8($key, $expression, $memberPath);
            }

            if (self::isScalarLike($member)) {
                if (\is_float($member) && !\is_finite($member)) {
                    throw self::invalidVariable($expression, $memberPath, 'non-finite floats are not supported');
                }

                $normalized[$key] = self::formScalarLike($member, $expression, $memberPath);
                continue;
            }

            if (\is_array($member) && $allowNestedArrays) {
                $normalized[$key] = self::normalizeNestedQueryShape($memberPath, $member, $expression, $depth + 1);
                continue;
            }

            throw self::invalidVariable(
                $expression,
                $memberPath,
                \sprintf('expected scalar%s; got %s', $allowNestedArrays ? ', stringable object, or nested array' : ' or stringable object', \get_debug_type($member))
            );
        }

        return $normalized;
    }

    /**
     * Validate a nested query array and snapshot its members.
     *
     * Nested query arrays accept only scalar leaves, so there are no
     * stringable objects to resolve, but rebuilding the array breaks PHP
     * references held by the caller's array, so members mutated after the
     * snapshot is taken cannot change the expansion.
     *
     * @param array<array-key, mixed> $value
     *
     * @return array<array-key, mixed>
     */
    private static function normalizeNestedQueryShape(string $path, array $value, string $expression, int $depth): array
    {
        if ($depth > self::MAX_VARIABLE_DEPTH) {
            throw self::invalidVariable($expression, $path, 'maximum variable nesting depth exceeded');
        }

        $normalized = [];

        foreach ($value as $key => $member) {
            if ($member === null) {
                // Spec sections 2.3 and 2.4.2: only members with defined
                // values are present in the expansion, so null members are
                // omitted before their keys are validated, matching the
                // handling of null members in top-level maps.
                $normalized[$key] = null;
                continue;
            }

            $memberPath = \sprintf('%s[%s]', $path, (string) $key);

            if (\is_string($key)) {
                self::assertValidVariableUtf8($key, $expression, $memberPath);
            }

            if (\is_scalar($member)) {
                if (\is_string($member)) {
                    self::assertValidVariableUtf8($member, $expression, $memberPath);
                }

                if (\is_float($member) && !\is_finite($member)) {
                    throw self::invalidVariable($expression, $memberPath, 'non-finite floats are not supported');
                }

                // Scalars are converted to their expansion strings while
                // values are formed, so http_build_query never applies its
                // own locale-sensitive float conversion and repeated
                // occurrences render identically even when the float
                // precision changes mid-expansion.
                $normalized[$key] = self::stringifyValue($member);
                continue;
            }

            if (\is_array($member)) {
                $normalized[$key] = self::normalizeNestedQueryShape($memberPath, $member, $expression, $depth + 1);
                continue;
            }

            throw self::invalidVariable(
                $expression,
                $memberPath,
                \sprintf('expected scalar or nested array; got %s', \get_debug_type($member))
            );
        }

        return $normalized;
    }

    /**
     * Determines if an array should be expanded as a map.
     *
     * @param array<array-key, mixed> $array
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
            if (\preg_last_error() !== \PREG_BAD_UTF8_ERROR) {
                throw new \RuntimeException(\sprintf('Unable to process template: %s', \preg_last_error_msg()));
            }

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

            if (\strlen($tokens[$index]) === 3 && \str_starts_with($tokens[$index], '%')) {
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

            if (\strlen($token) !== 3 || !\str_starts_with($token, '%')) {
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
            if (\preg_last_error() === \PREG_BAD_UTF8_ERROR) {
                throw self::invalidTemplate(
                    $baseOffset + self::validUtf8PrefixLength($literal),
                    'literal text must be valid UTF-8'
                );
            }

            // A PCRE engine failure, such as an exhausted resource limit, is
            // not a template syntax error.
            throw new \RuntimeException(\sprintf('Unable to process template: %s', \preg_last_error_msg()));
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

            if (\strlen($token) === 3 && \str_starts_with($token, '%')) {
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

    /**
     * Count the bytes of the longest well-formed UTF-8 prefix.
     *
     * Mirrors the UTF8-char grammar of RFC 3629 section 4 byte by byte so
     * the reported template offset identifies the first invalid byte rather
     * than the start of the enclosing literal segment.
     */
    private static function validUtf8PrefixLength(string $value): int
    {
        $matches = [];
        $result = \preg_match(
            '/\A(?:[\x00-\x7F]|[\xC2-\xDF][\x80-\xBF]|\xE0[\xA0-\xBF][\x80-\xBF]|[\xE1-\xEC][\x80-\xBF]{2}|\xED[\x80-\x9F][\x80-\xBF]|[\xEE-\xEF][\x80-\xBF]{2}|\xF0[\x90-\xBF][\x80-\xBF]{2}|[\xF1-\xF3][\x80-\xBF]{3}|\xF4[\x80-\x8F][\x80-\xBF]{2})*+/',
            $value,
            $matches
        );

        if ($result !== 1) {
            // The pattern matches the empty prefix of every subject, so
            // anything other than a match is a PCRE engine failure.
            throw new \RuntimeException(\sprintf('Unable to process template: %s', \preg_last_error_msg()));
        }

        return \strlen($matches[0]);
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
        self::assertValidVariableUtf8($value, $expression, $name);

        $matches = [];
        if (\preg_match_all('/%[0-9A-Fa-f]{2}|./s', $value, $matches) === false) {
            throw new \RuntimeException(\sprintf('Unable to encode URI template value: %s', \preg_last_error_msg()));
        }

        $encoded = '';

        foreach ($matches[0] as $token) {
            if ($allowReserved && \strlen($token) === 3 && \str_starts_with($token, '%')) {
                $encoded .= $token;
                continue;
            }

            if (\strlen($token) === 1 && \str_contains('ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789._~-', $token)) {
                $encoded .= $token;
                continue;
            }

            if ($allowReserved && \strlen($token) === 1 && \str_contains(":/?#[]@!$&'()*+,;=", $token)) {
                $encoded .= $token;
                continue;
            }

            $encoded .= \rawurlencode($token);
        }

        return $encoded;
    }

    private static function assertValidVariableUtf8(string $value, string $expression, string $name): void
    {
        if (\preg_match('//u', $value) === 1) {
            return;
        }

        if (\preg_last_error() !== \PREG_BAD_UTF8_ERROR) {
            throw new \RuntimeException(\sprintf('Unable to process template: %s', \preg_last_error_msg()));
        }

        throw self::invalidVariable($expression, $name, 'variable values must be valid UTF-8');
    }
}
