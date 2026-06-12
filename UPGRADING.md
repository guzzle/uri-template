Guzzle URI Template Upgrade Guide
=================================

1.x to 2.0
----------

Guzzle URI Template 2.0 is a major release that raises the minimum PHP version
and validates RFC 6570 template syntax, literal text, variable names, value
modifiers, and referenced variable values more strictly before expansion.
Invalid input now fails with `InvalidArgumentException` instead of being
partially expanded, returned unchanged, or producing PHP warnings.

Applications that expand application-controlled templates with scalar values
should usually need small changes. Applications that load templates from
configuration, service descriptions, or remote systems, or that pass mixed PHP
value shapes into templates, should review the stricter validation rules below.

#### PHP Version and Dependencies

Guzzle URI Template 2.0 requires PHP `^7.4 || ^8.0`. Guzzle URI Template 1.x
supported PHP `^7.2.5 || ^8.0`.

If your application still supports PHP 7.2 or 7.3, continue using Guzzle URI
Template 1.x until your minimum PHP version is raised.

Guzzle URI Template 2.0 continues to require `symfony/polyfill-php80:^1.24`, so
there are no runtime package dependency changes beyond PHP.

#### Input Contract

`UriTemplate::expand()` expects an RFC 6570 URI template and a variable map.
Guzzle URI Template 2.0 validates template syntax, literal text, variable
specifiers, value modifiers, and referenced variable values before expansion.
Invalid input throws `InvalidArgumentException` instead of being partially
expanded, returned unchanged, or producing PHP warnings.

Only variables referenced by the template are validated. Extra values in the
variable map are ignored, even if those extra values have unsupported PHP types.
Missing variables and variables set to `null` are treated as undefined and are
omitted from the expansion. Empty strings are defined values and are expanded.

Invalid template syntax includes malformed braces, unsupported operators,
invalid variable names, invalid modifiers, repeated operator-like variable
specifiers, and prefix modifiers applied to list or map values.

#### Common Migration Fixes

Use pct-encoded literal text in templates. Pass variable values in their normal
application form and let the expander encode them according to the expression
type:

```php
UriTemplate::expand('/search%20terms/{id}', ['id' => 1]);

UriTemplate::expand('/search{?q}', ['q' => 'search terms']);
```

Use valid RFC 6570 variable names. If the expanded URI must contain a query
parameter name that is not a valid template variable name, place that name in an
exploded map:

```php
UriTemplate::expand('/search{?query*}', [
    'query' => ['default-graph-uri' => 'https://example.com/'],
]);
```

Apply prefix modifiers only to scalar or stringable values:

```php
UriTemplate::expand('{name:3}', ['name' => 'value']);
```

Validate variable maps before expansion if they contain user-provided values.
Unsupported resources, closures, non-stringable objects, non-finite floats, and
unsupported nested arrays now throw `InvalidArgumentException`.

Apply defaults before expansion instead of using non-RFC template extension
syntax:

```php
$q = $q ?? 'default';

UriTemplate::expand('/search{?q}', ['q' => $q]);
```

#### Literal Text

Literal text outside expressions is now validated and encoded using RFC 6570
literal rules; apostrophes are valid literal characters per verified RFC 6570
erratum 6937. Invalid literal characters, including spaces, raw or malformed `%`
sequences, double quotes, controls, `<`, `>`, backslash, caret, backtick, and
pipe, now throw `InvalidArgumentException`. Valid non-ASCII literal text must be
valid UTF-8 and is pct-encoded during expansion. Templates without expressions
are also validated and encoded.

Existing valid pct-encoded triplets in literal text are preserved. Raw `%`
characters and malformed pct-encoded triplets are invalid literal text.

Before:

```php
UriTemplate::expand('/search terms/{id}', ['id' => 1]);
```

After:

```php
UriTemplate::expand('/search%20terms/{id}', ['id' => 1]);
```

Before:

```php
UriTemplate::expand('/files/%/{id}', ['id' => 1]);
```

After:

```php
UriTemplate::expand('/files/%25/{id}', ['id' => 1]);
```

#### Variable Names

Variable names in templates must use RFC 6570 syntax: ASCII letters, ASCII
digits, `_`, pct-encoded triplets, and dot separators. Pct-encoded triplets are
part of the variable name and are not decoded for variable lookup.

Raw Unicode characters are not valid variable names. Use the pct-encoded form in
both the template and the variable map key:

```php
UriTemplate::expand('/lookup{?Stra%C3%9Fe}', [
    'Stra%C3%9Fe' => 'Gruner Weg',
]);
```

Dot separators are part of the variable name. They do not traverse nested PHP
arrays:

```php
UriTemplate::expand('/people/{last.name}', [
    'last.name' => 'Doe',
]);
```

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

If the resulting query parameter name must contain a hyphen, use an exploded map
so the hyphenated name is data rather than template syntax:

```php
UriTemplate::expand('/search{?query*}', [
    'query' => ['default-graph-uri' => 'https://example.com/'],
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

URI Template extension syntaxes that are not part of RFC 6570, such as default
values, join expressions, and prefix extension expressions, are rejected. Apply
those policies in application code before expansion.

Before:

```php
UriTemplate::expand('/search{?q=default}', []);
```

After:

```php
UriTemplate::expand('/search{?q}', ['q' => 'default']);
```

#### Value Modifiers

Prefix modifiers, such as `{var:3}`, are valid only for scalar or stringable
values. Prefix lengths must be positive integers from `1` through `9999`, with
no leading zeroes. Prefix lengths are counted as Unicode code points and
existing pct-encoded characters, not bytes or visual grapheme clusters.
Consecutive pct-encoded triplets that encode one Unicode code point in UTF-8,
such as `%C3%A9`, count as one character. A prefixed string value must be valid
UTF-8, otherwise expansion throws `InvalidArgumentException`.

Before:

```php
UriTemplate::expand('{list:1}', [
    'list' => ['red', 'green'],
]);
```

After:

```php
UriTemplate::expand('{name:1}', ['name' => 'red']);
```

Use the explode modifier, `*`, to expand lists and maps item by item:

```php
UriTemplate::expand('{/list*}', [
    'list' => ['red', 'green'],
]);

// /red/green
```

#### Variable Values

Supported variable values are `null`, scalars, stringable objects, lists, and
maps. `null` means undefined and is omitted from expansion. Lists and maps may
contain scalar or stringable values. Arrays whose keys are exactly `0` through
`n-1` in ascending insertion order are expanded as lists. All other arrays,
including reordered, sparse, and mixed-key arrays, are expanded as maps.

Scalars are cast to strings before expansion. `true` expands as `1` and `false`
expands as `0`, at every nesting level. Guzzle URI Template 1.x expanded
top-level `false` as an empty string; pass `''` explicitly if that output is
required, or `null` to omit the variable.

Floats always expand with `.` as the decimal separator. Guzzle URI Template 1.x
followed the `LC_NUMERIC` locale on PHP versions before 8.0, so a process that
had called `setlocale()` with a comma-decimal locale such as `de_DE` could
expand `3.5` as `3%2C5`. Non-finite floats are not supported: `INF`, `-INF`,
and `NAN` now throw `InvalidArgumentException` instead of expanding as literal
text.

```php
UriTemplate::expand('/search{?q,page}', [
    'q' => null,
    'page' => 1,
]);

// /search?page=1

UriTemplate::expand('/search{?q,page}', [
    'q' => '',
    'page' => 1,
]);

// /search?q=&page=1
```

Nested arrays in maps are supported for exploded query-style expansions, such as
`{?var*}` and `{&var*}`, to preserve existing Guzzle URI Template behavior.

`null` members inside lists and maps are treated as undefined and omitted,
consistent with top-level `null`. RFC 6570 section 3.2.1 expands a list as a
concatenation of "the defined member string values", and section 2.4.2 states
that "only the defined pairs are present in the expansion". A list or map whose
members are all `null` is treated as a wholly undefined variable.

Unsupported values throw `InvalidArgumentException` before expansion. This
includes resources, closures, non-stringable objects, unsupported nested arrays,
recursive arrays, and arrays nested too deeply.

Variable values must now be valid UTF-8. Invalid byte sequences throw
`InvalidArgumentException` instead of being percent-encoded byte by byte. Encode
binary data, for example with base64, before expansion.

Convert non-stringable objects before expansion:

Before:

```php
$date = new \DateTimeImmutable('2026-01-01');

UriTemplate::expand('/events{?date}', ['date' => $date]);
```

After:

```php
$date = new \DateTimeImmutable('2026-01-01');

UriTemplate::expand('/events{?date}', [
    'date' => $date->format(DATE_ATOM),
]);
```

#### Array Values

PHP arrays are classified by shape. Arrays whose keys are exactly `0` through
`n-1` in ascending insertion order are lists. All other arrays, including
reordered, sparse, and mixed-key arrays, are maps. This makes expansion
deterministic and preserves PHP insertion order for maps.

If a sparse array is intended to be a list, reindex it before expansion:

Before:

```php
$tag = [1 => 'red', 2 => 'green'];

UriTemplate::expand('/tags{/tag*}', ['tag' => $tag]);
```

After:

```php
$tag = [1 => 'red', 2 => 'green'];

UriTemplate::expand('/tags{/tag*}', ['tag' => array_values($tag)]);

// /tags/red/green
```

Use maps when the member names are part of the URI:

```php
UriTemplate::expand('/search{?filter*}', [
    'filter' => [
        'status' => 'open',
        'sort' => 'created',
    ],
]);
```

#### Nested Arrays

Nested arrays are supported only for exploded query-style expansions, such as
`{?var*}` and `{&var*}`. They are encoded using RFC 3986 query encoding with PHP
bracket syntax. Empty nested arrays are omitted from the expansion.

Before:

```php
UriTemplate::expand('/search{?filter}', [
    'filter' => [
        'author' => [
            'name' => 'Ada Lovelace',
        ],
    ],
]);
```

After:

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

Nested arrays are not supported in simple, reserved, fragment, label, path, or
path-style parameter expansions. Lists containing arrays are also invalid. If a
nested query array contains objects, convert them to scalar values before
expansion.

#### Reserved Expansion

Reserved expansion (`{+var}`) and fragment expansion (`{#var}`) intentionally
preserve URI reserved delimiters from variable values according to RFC 6570.
Simple expansion encodes reserved delimiters.

```php
UriTemplate::expand('/files/{path}', ['path' => 'a/b']);

// /files/a%2Fb

UriTemplate::expand('/files/{+path}', ['path' => 'a/b']);

// /files/a/b
```

Reserved and fragment expansion also preserve existing valid pct-encoded
triplets in variable values. Simple expansion encodes `%` as `%25`.

```php
UriTemplate::expand('{id}', ['id' => 'admin%2F']);

// admin%252F

UriTemplate::expand('{+id}', ['id' => 'admin%2F']);

// admin%2F
```

Templates should generally be application-controlled. If templates come from
users or remote systems, treat them as policy input because template syntax
controls the structure of the expanded URI.

#### UriTemplate Instantiation

`UriTemplate` now has a private constructor. Use `UriTemplate::expand()`
statically instead of instantiating the class.
