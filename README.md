# uri-template

A small [RFC 6570](https://datatracker.ietf.org/doc/html/rfc6570) URI
Template expander for PHP.

It expands URI templates using a variable map and encodes variable values
according to the template expression type.

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
of variables to use during expansion.

Variable values are encoded during expansion:

```php
UriTemplate::expand('/search{?q}', ['q' => 'Hello World!']);

// /search?q=Hello%20World%21
```

Reserved expansion (`{+var}`) and fragment expansion (`{#var}`) preserve URI
reserved delimiters:

```php
UriTemplate::expand('{+path}', ['path' => '/foo/bar']);

// /foo/bar
```

Dense zero-indexed arrays expand as lists:

```php
UriTemplate::expand('/tags{/tags*}', [
    'tags' => ['red', 'green', 'blue'],
]);

// /tags/red/green/blue
```

Sparse or mixed-key arrays expand as maps. Map order follows PHP array insertion
order:

```php
UriTemplate::expand('/search{?filter*}', [
    'filter' => [
        'status' => 'open',
        'sort' => 'created',
    ],
]);

// /search?status=open&sort=created
```

Nested arrays are supported for exploded query-style expansions, such as
`{?var*}` and `{&var*}`:

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

## Input Contract

`UriTemplate::expand()` expects an RFC 6570 URI template and an array of
variables.

Supported variable values are:

- `null`, which is treated as undefined and omitted
- scalars
- objects with `__toString()`
- lists
- maps

Invalid templates or unsupported variable values throw `InvalidArgumentException`.

Literal text outside expressions must already be valid URI template literal text.
For example, use `/search%20terms/{id}` instead of `/search terms/{id}`.

Templates should generally be application-controlled. If templates come from
users or remote systems, treat them as policy input and review them before
expansion.

## Upgrading

Please see [UPGRADING](UPGRADING.md) for details on upgrading to new major
versions.

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Testing

```bash
make test
```

## Security

If you discover a security vulnerability within this package, please send an email to security@tidelift.com. All security vulnerabilities will be promptly addressed. Please do not disclose security-related issues publicly until a fix has been announced. Please see [Security Policy](https://github.com/guzzle/uri-template/security/policy) for more information.

## License

Guzzle URI Template is made available under the MIT License (MIT). Please see [License File](LICENSE) for more information.

## For Enterprise

Available as part of the Tidelift Subscription

The maintainers of Guzzle and thousands of other packages are working with Tidelift to deliver commercial support and maintenance for the open source dependencies you use to build your applications. Save time, reduce risk, and improve code health, while paying the maintainers of the exact dependencies you use. [Learn more.](https://tidelift.com/subscription/pkg/packagist-guzzlehttp-uri-template?utm_source=packagist-guzzlehttp-uri-template&utm_medium=referral&utm_campaign=enterprise&utm_term=repo)
