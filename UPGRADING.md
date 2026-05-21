Guzzle URI Template Upgrade Guide
=================================

1.x to 2.0
----------

#### PHP Version and Dependencies

Guzzle URI Template 2.0 requires PHP `^7.4 || ^8.0`. Guzzle URI Template 1.x
supported PHP `^7.2.5 || ^8.0`.

If your application still supports PHP 7.2 or 7.3, continue using Guzzle URI
Template 1.x until your minimum PHP version is raised.

#### Input Contract

`UriTemplate::expand()` expects an RFC 6570 URI template and a variable map.
Guzzle URI Template 2.0 validates template syntax and referenced variable values
before expansion. Invalid input throws `InvalidArgumentException` instead of
being partially expanded, returned unchanged, or producing PHP warnings.

Invalid template syntax includes malformed braces, unsupported operators,
invalid variable names, invalid modifiers, repeated operator-like variable
specifiers, and prefix modifiers applied to list or map values.

#### Literal Text

Literal text outside expressions is now validated for unsafe characters. Spaces,
raw or malformed `%` sequences, double quotes, controls, `<`, `>`, backslash,
caret, backtick, and pipe now throw `InvalidArgumentException`. Valid non-ASCII
literal text is preserved, but must be valid UTF-8. Templates without expressions
are also validated.

Before:

```php
UriTemplate::expand('/search terms/{id}', ['id' => 1]);
```

After:

```php
UriTemplate::expand('/search%20terms/{id}', ['id' => 1]);
```

#### Variable Names

Variable names in templates must use RFC 6570 syntax: ASCII letters, ASCII
digits, `_`, pct-encoded triplets, and dot separators. Pct-encoded triplets are
part of the variable name and are not decoded for variable lookup.

For example, hyphenated variable names are invalid in templates. Rename them to
use a valid character such as `_`, or use valid dot-separated names where that
matches your variable map.

Before:

```php
UriTemplate::expand('/search{?default-graph-uri}', [
    'default-graph-uri' => 'https://example.com/',
]);
```

After:

```php
UriTemplate::expand('/search{?default_graph_uri}', [
    'default_graph_uri' => 'https://example.com/',
]);
```

Whitespace is also invalid inside variable specifiers.

Before:

```php
UriTemplate::expand('{?x, y}', ['x' => 1, 'y' => 2]);
```

After:

```php
UriTemplate::expand('{?x,y}', ['x' => 1, 'y' => 2]);
```

#### Variable Values

Supported variable values are `null`, scalars, stringable objects, lists, and
maps. `null` means undefined and is omitted from expansion. Lists and maps may
contain scalar or stringable values. Dense zero-indexed arrays are expanded as
lists. Sparse numeric arrays and mixed-key arrays are expanded as maps.

Nested maps are supported for exploded query-style expansions, such as `{?var*}`
and `{&var*}`, to preserve existing Guzzle URI Template behavior.

Unsupported values throw `InvalidArgumentException` before expansion. This
includes resources, closures, non-stringable objects, unsupported nested arrays,
nested null values, recursive arrays, and arrays nested too deeply.

Prefix modifiers, such as `{var:3}`, are only valid for scalar or stringable
values. Applying a prefix modifier to a list or map now throws
`InvalidArgumentException`. Prefix lengths are counted as Unicode code points
and existing pct-encoded triplets, not bytes or visual grapheme clusters. A
prefixed string value must be valid UTF-8, otherwise expansion throws
`InvalidArgumentException`.

#### Reserved Expansion

Reserved expansion (`{+var}`) and fragment expansion (`{#var}`) intentionally
preserve URI reserved delimiters from variable values according to RFC 6570.
They also preserve valid pct-encoded triplets already present in variable
values.

Templates should generally be application-controlled. If templates come from
users or remote systems, treat them as policy input and review them before
expansion.
