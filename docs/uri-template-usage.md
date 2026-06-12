# URI Template Usage

This guide shows the supported URI template expansion forms and common value
shapes for `UriTemplate::expand()`. For the complete rules for variable names,
accepted PHP values, empty values, and validation errors, see the
[input contract](input-contract.md).

```php
use GuzzleHttp\UriTemplate\UriTemplate;

$uri = UriTemplate::expand('/users/{id}{?tab}', [
    'id' => 123,
    'tab' => 'settings',
]);

// /users/123?tab=settings
```

The first argument is an RFC 6570 URI template. The second argument is an array
of [variables](input-contract.md#variables) to use during expansion. Variable
map keys must match template variable names exactly.

This package supports RFC 6570 levels 1 through 4 for the standard operators
listed below, including prefix and explode modifiers. RFC 6570 reserved
extension operators are not supported and are rejected.

| Operator | Expansion | Status |
|----------|-----------|--------|
| none | Simple string expansion | Supported |
| `+` | Reserved string expansion | Supported |
| `#` | Fragment expansion | Supported |
| `.` | Label expansion | Supported |
| `/` | Path segment expansion | Supported |
| `;` | Path-style parameter expansion | Supported |
| `?` | Form-style query expansion | Supported |
| `&` | Form-style query continuation | Supported |
| `=`, `,`, `!`, `@`, `\|` | Reserved extension operators | Unsupported, rejected |

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

[Dense zero-indexed arrays](input-contract.md#values) expand as lists. Sparse
numeric arrays and mixed-key arrays expand as maps. Map order follows PHP array
insertion order:

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

Nested arrays are supported only for exploded query-style map expansions, such
as `{?var*}` and `{&var*}`. They use RFC 3986 query encoding with PHP bracket
syntax. See [nested query arrays](input-contract.md#nested-query-arrays) for the
full contract:

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

Empty nested arrays are omitted from exploded query expansions. `null` members
inside lists and maps are treated as undefined members and omitted, like
top-level `null`.

Variable values are encoded during expansion according to the expression type.
Existing percent-encoded triplets in reserved and fragment expansions are
preserved, while simple expansion encodes `%` as `%25`:

```php
UriTemplate::expand('{id}', ['id' => 'admin%2F']);

// admin%252F

UriTemplate::expand('{+id}', ['id' => 'admin%2F']);

// admin%2F
```

## Related

- [Input Contract](input-contract.md)
- [Upgrade Guide](../UPGRADING.md)
- [Changelog](../CHANGELOG.md)
