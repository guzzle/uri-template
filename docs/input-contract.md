# Input Contract

This document defines the template syntax and PHP value shapes accepted by
`UriTemplate::expand()`. Use it when variables come from application data,
request data, or another system and you need predictable validation behavior.

`UriTemplate::expand()` expects an RFC 6570 URI template and an array of
variables. Invalid templates or unsupported referenced variable values throw
`InvalidArgumentException`.

## Variables

Template variable names may contain ASCII letters, ASCII digits, `_`, valid
percent-encoded triplets, and dot separators. Percent-encoded triplets are part
of the variable name and are not decoded for variable lookup. Dot separators may
separate name parts, but names cannot start with a dot, end with a dot, or
contain empty dot-separated parts.

Array keys in the variables argument must match template variable names exactly:

```php
UriTemplate::expand('/users/{user.id}', [
    'user.id' => 123,
]);
```

## Values

Supported variable values are:

- `null`, which is treated as undefined and omitted
- scalars, which are cast to strings before expansion
- objects with `__toString()`
- dense zero-indexed lists containing scalar or stringable values
- maps containing scalar or stringable values
- nested arrays in maps for exploded query-style expansions, with scalar leaves

Booleans expand as `1` and `0` at every nesting level.

An empty string is a defined value and is expanded. An empty array is treated as
undefined and omitted. Missing variables and variables set to `null` are treated
as undefined and omitted.

`null` members inside lists and maps are treated as undefined members and
omitted, like top-level `null`. A list or map whose members are all `null` is
treated as undefined and omitted, like an empty array.

Arrays whose keys are exactly `0` through `n-1` in ascending insertion order
expand as lists. All other arrays, including reordered, sparse, and mixed-key
arrays, expand as maps. Map and list order follows PHP array insertion order.

Unsupported values include resources, closures, non-stringable objects,
recursive arrays, arrays nested too deeply, and nested arrays outside exploded
query-style expansions. Unsupported values are validated only when the template
references that variable.

Variable values must be valid UTF-8. Invalid byte sequences throw
`InvalidArgumentException`. Encode binary data, for example with base64, before
expansion.

## Prefix Modifiers

Prefix modifiers select a character prefix from scalar and stringable values:

```php
UriTemplate::expand('/dictionary/{term:1}/{term}', ['term' => 'cat']);

// /dictionary/c/cat
```

Prefix lengths must be decimal integers from `1` through `9999`. Leading zeroes,
zero, negative numbers, non-numeric values, and values greater than `9999` are
invalid.

Prefix modifiers are not valid on composite values such as lists or maps, and a
varspec cannot combine prefix and explode modifiers.

Prefix length counts Unicode characters and existing percent-encoded
characters, not bytes. For example, `%2F` counts as one character before the
selected prefix is encoded for the expression type, and consecutive
percent-encoded triplets that encode one Unicode code point in UTF-8, such as
`%C3%A9`, also count as one character. Values must be valid UTF-8, as described
in [values](#values).

## Nested Query Arrays

Nested arrays are accepted only as values inside map variables expanded with an
exploded query or query-continuation expression, such as `{?filter*}` or
`{&filter*}`.

Nested query arrays must have scalar leaves. Recursive arrays, arrays nested too
deeply, and nested objects are rejected. Nested `null` values are treated as
undefined members and omitted. Stringable objects are accepted as direct map
values, but not as leaves inside nested query arrays.

Nested query arrays use RFC 3986 query encoding with PHP bracket syntax:

```php
UriTemplate::expand('/search{?filter*}', [
    'filter' => [
        'author' => [
            'name' => 'Ada Lovelace',
        ],
    ],
]);

// /search?author%5Bname%5D=Ada%20Lovelace
```

Empty nested arrays are omitted from exploded query expansions. Empty scalar
values are preserved.

Empty-string keys in nested query arrays produce PHP append syntax
(`a%5B%5D=v`), which does not round-trip the key.

## Validation Errors

Literal text outside expressions must already be valid URI template literal text.
For example, use `/search%20terms/{id}` instead of `/search terms/{id}`.

`InvalidArgumentException` is thrown for invalid template syntax, unsupported
operators, invalid variable names, invalid modifiers, invalid literal text, and
unsupported shapes for variables referenced by the template.

`RuntimeException` is thrown when the PCRE engine fails while processing a
template, for example when an extremely long variable name exhausts a PCRE
resource limit. This indicates an environment limit, not invalid input; the
threshold depends on the PCRE build and `pcre.jit` configuration.

Exceptions thrown by a value object's `__toString()` method propagate unchanged;
they are not converted to `InvalidArgumentException`.

Catch `InvalidArgumentException` if templates or values come from outside your
application:

```php
try {
    $uri = UriTemplate::expand($template, $variables);
} catch (\InvalidArgumentException $e) {
    // Reject or log the invalid template input.
}
```

Templates should generally be application-controlled. If templates come from
users or remote systems, treat them as policy input and review them before
expansion.

## Related

- [URI Template Usage](uri-template-usage.md)
- [Upgrade Guide](../UPGRADING.md)
- [Changelog](../CHANGELOG.md)
