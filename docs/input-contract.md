# Input Contract

`UriTemplate::expand()` expects an RFC 6570 URI template and an array of
variables. Invalid templates or unsupported referenced variable values throw
`InvalidArgumentException`.

Template variable names may contain ASCII letters, ASCII digits, `_`, valid
percent-encoded triplets, and dot separators. Percent-encoded triplets are part
of the variable name and are not decoded for variable lookup.

Supported variable values are:

- `null`, which is treated as undefined and omitted
- scalars, which are cast to strings
- objects with `__toString()`
- dense zero-indexed lists containing scalar or stringable values
- maps containing scalar or stringable values
- nested arrays in maps for exploded query-style expansions

An empty string is a defined value and is expanded. An empty array is treated as
undefined and omitted. Unsupported values include resources, closures,
non-stringable objects, recursive arrays, nested `null` values, arrays nested too
deeply, and nested arrays outside exploded query-style expansions.

Literal text outside expressions must already be valid URI template literal text.
For example, use `/search%20terms/{id}` instead of `/search terms/{id}`.

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
