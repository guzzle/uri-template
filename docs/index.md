# Guzzle URI Template Documentation

An [RFC 6570](https://datatracker.ietf.org/doc/html/rfc6570) URI Template
expander for PHP. It expands URI templates using a variable map and encodes
variable values according to the template expression type.

## Install

Via Composer

```bash
composer require guzzlehttp/uri-template
```

## Usage

```php
use GuzzleHttp\UriTemplate\UriTemplate;

$uri = UriTemplate::expand('/users/{id}{?tab}', [
    'id' => 123,
    'tab' => 'settings',
]);

// /users/123?tab=settings
```

The first argument is an RFC 6570 URI template. The second argument is an array
of variables to use during expansion. Variable map keys must match template
variable names exactly.

This package supports RFC 6570 simple, reserved, fragment, label, path,
path-style parameter, query, and query-continuation expansions, including prefix
and explode modifiers.

Simple expansion encodes reserved URI delimiters in variable values:

```php
UriTemplate::expand('/files/{path}', ['path' => 'a/b']);

// /files/a%2Fb
```

Use reserved expansion (`{+var}`) when the variable value intentionally contains
URI delimiters that should remain delimiters in the expanded URI:

```php
UriTemplate::expand('/files/{+path}', ['path' => 'a/b']);

// /files/a/b
```

Fragment expansion (`{#var}`) prefixes the expanded value with `#` when the
variable is defined:

```php
UriTemplate::expand('/docs{#section}', ['section' => 'part 1']);

// /docs#part%201
```

Other operators help build common URI components:

```php
UriTemplate::expand('www{.domain*}', [
    'domain' => ['example', 'com'],
]);

// www.example.com

UriTemplate::expand('/users{/id}', ['id' => 123]);

// /users/123

UriTemplate::expand('/users{;role}', ['role' => 'admin']);

// /users;role=admin

UriTemplate::expand('/search{?q,page}', [
    'q' => 'uri templates',
    'page' => 2,
]);

// /search?q=uri%20templates&page=2

UriTemplate::expand('/search?fixed=yes{&page}', ['page' => 2]);

// /search?fixed=yes&page=2
```

Prefix modifiers select a prefix of a scalar value:

```php
UriTemplate::expand('/dictionary/{term:1}/{term}', ['term' => 'cat']);

// /dictionary/c/cat
```

Explode modifiers expand lists and maps item by item:

```php
UriTemplate::expand('/tags{/tag*}', [
    'tag' => ['red', 'green', 'blue'],
]);

// /tags/red/green/blue

UriTemplate::expand('/tags{?tag*}', [
    'tag' => ['red', 'green'],
]);

// /tags?tag=red&tag=green
```

Dense zero-indexed arrays expand as lists. Sparse numeric arrays and mixed-key
arrays expand as maps. Map order follows PHP array insertion order:

```php
UriTemplate::expand('/search{?filter*}', [
    'filter' => [
        'status' => 'open',
        'sort' => 'created',
    ],
]);

// /search?status=open&sort=created
```

If a sparse array is intended to expand as a list, reindex it before expansion:

```php
$tag = [1 => 'red', 2 => 'green'];

UriTemplate::expand('/tags{/tag*}', ['tag' => array_values($tag)]);

// /tags/red/green
```

Nested arrays are supported for exploded query-style expansions, such as
`{?var*}` and `{&var*}`. They use RFC 3986 query encoding with PHP bracket
syntax:

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

Empty nested arrays are omitted from exploded query expansions.

Variable values are encoded during expansion according to the expression type.
Existing percent-encoded triplets in reserved and fragment expansions are
preserved, while simple expansion encodes `%` as `%25`:

```php
UriTemplate::expand('{id}', ['id' => 'admin%2F']);

// admin%252F

UriTemplate::expand('{+id}', ['id' => 'admin%2F']);

// admin%2F
```

## Input Contract

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

## Upgrading

Please see [UPGRADING](../UPGRADING.md) for details on upgrading to new major
versions.

Please see [CHANGELOG](../CHANGELOG.md) for more information on what has changed recently.

## Testing

```bash
make test
```

## Security

If you discover a security vulnerability within this package, please send an email to security@tidelift.com. All security vulnerabilities will be promptly addressed. Please do not disclose security-related issues publicly until a fix has been announced. Please see [Security Policy](https://github.com/guzzle/uri-template/security/policy) for more information.

## License

Guzzle URI Template is made available under the MIT License (MIT). Please see [License File](../LICENSE) for more information.

## For Enterprise

Available as part of the Tidelift Subscription

The maintainers of Guzzle and thousands of other packages are working with Tidelift to deliver commercial support and maintenance for the open source dependencies you use to build your applications. Save time, reduce risk, and improve code health, while paying the maintainers of the exact dependencies you use. [Learn more.](https://tidelift.com/subscription/pkg/packagist-guzzlehttp-uri-template?utm_source=packagist-guzzlehttp-uri-template&utm_medium=referral&utm_campaign=enterprise&utm_term=repo)
