<?php

declare(strict_types=1);

namespace GuzzleHttp\UriTemplate\Tests;

use GuzzleHttp\UriTemplate\UriTemplate;
use PHPUnit\Framework\TestCase;

/**
 * @covers \GuzzleHttp\UriTemplate\UriTemplate
 */
final class UriTemplateTest extends TestCase
{
    /**
     * @return array<int,array{0:string, 1:string, 2:array<string,mixed>}>
     */
    public static function templateProvider(): array
    {
        $variables = [
            'var' => 'value',
            'hello' => 'Hello World!',
            'empty' => '',
            'path' => '/foo/bar',
            'x' => '1024',
            'y' => 768,
            'null' => null,
            'zero' => 0,
            'list' => ['red', 'green', 'blue'],
            'keys' => [
                'semi' => ';',
                'dot' => '.',
                'comma' => ',',
            ],
            'empty_keys' => [],
            'empty_member_list' => [''],
            'null_empty_list' => [null, ''],
            'mixed_list' => ['red', ''],
            'kv_empty' => ['a' => '', 'b' => 'x'],
            'kv_mid_empty' => ['b' => 'x', 'a' => '', 'c' => 'y'],
            'kv_empty_pair' => ['' => ''],
            'kv_empty_key' => ['' => 'x'],
            'reserved_keys' => ['a/b' => 'c/d', 'x%20y' => 'v'],
        ];

        return \array_map(static function (array $t) use ($variables): array {
            $t[] = $variables;

            return $t;
        }, [
            ['foo',                 'foo'],
            ['{var}',               'value'],
            ['{hello}',             'Hello%20World%21'],
            ['{+var}',              'value'],
            ['{+hello}',            'Hello%20World!'],
            ['{+path}/here',        '/foo/bar/here'],
            ['here?ref={+path}',    'here?ref=/foo/bar'],
            ['X{#var}',             'X#value'],
            ['X{#hello}',           'X#Hello%20World!'],
            ['map?{x,y}',           'map?1024,768'],
            ['{x,hello,y}',         '1024,Hello%20World%21,768'],
            ['{+x,hello,y}',        '1024,Hello%20World!,768'],
            ['{+path,x}/here',      '/foo/bar,1024/here'],
            ['{#x,hello,y}',        '#1024,Hello%20World!,768'],
            ['{#path,x}/here',      '#/foo/bar,1024/here'],
            ['X{.var}',             'X.value'],
            ['X{.x,y}',             'X.1024.768'],
            ['{/var}',              '/value'],
            ['{/var,x}/here',       '/value/1024/here'],
            ['{;x,y}',              ';x=1024;y=768'],
            ['{;zero}',             ';zero=0'],
            ['{;x,y,empty}',        ';x=1024;y=768;empty'],
            ['{?x,y}',              '?x=1024&y=768'],
            ['{?x,y,empty}',        '?x=1024&y=768&empty='],
            ['?fixed=yes{&x}',      '?fixed=yes&x=1024'],
            ['{&x,y,empty}',        '&x=1024&y=768&empty='],
            ['{var:3}',             'val'],
            ['{var:30}',            'value'],
            ['{list}',              'red,green,blue'],
            ['{list*}',             'red,green,blue'],
            ['{keys}',              'semi,%3B,dot,.,comma,%2C'],
            ['{keys*}',             'semi=%3B,dot=.,comma=%2C'],
            ['{+path:6}/here',      '/foo/b/here'],
            ['{+list}',             'red,green,blue'],
            ['{+list*}',            'red,green,blue'],
            ['{+keys}',             'semi,;,dot,.,comma,,'],
            ['{+keys*}',            'semi=;,dot=.,comma=,'],
            ['{#path:6}/here',      '#/foo/b/here'],
            ['{#list}',             '#red,green,blue'],
            ['{#list*}',            '#red,green,blue'],
            ['{#keys}',             '#semi,;,dot,.,comma,,'],
            ['{#keys*}',            '#semi=;,dot=.,comma=,'],
            ['X{.var:3}',           'X.val'],
            ['X{.list}',            'X.red,green,blue'],
            ['X{.list*}',           'X.red.green.blue'],
            ['X{.keys}',            'X.semi,%3B,dot,.,comma,%2C'],
            ['X{.keys*}',           'X.semi=%3B.dot=..comma=%2C'],
            ['{/var:1,var}',        '/v/value'],
            ['{/list}',             '/red,green,blue'],
            ['{/list*}',            '/red/green/blue'],
            ['{/list*,path:4}',     '/red/green/blue/%2Ffoo'],
            ['{/keys}',             '/semi,%3B,dot,.,comma,%2C'],
            ['{/keys*}',            '/semi=%3B/dot=./comma=%2C'],
            ['{;hello:5}',          ';hello=Hello'],
            ['{;list}',             ';list=red,green,blue'],
            ['{;list*}',            ';list=red;list=green;list=blue'],
            ['{;keys}',             ';keys=semi,%3B,dot,.,comma,%2C'],
            ['{;keys*}',            ';semi=%3B;dot=.;comma=%2C'],
            ['{?var:3}',            '?var=val'],
            ['{?list}',             '?list=red,green,blue'],
            ['{?list*}',            '?list=red&list=green&list=blue'],
            ['{?keys}',             '?keys=semi,%3B,dot,.,comma,%2C'],
            ['{?keys*}',            '?semi=%3B&dot=.&comma=%2C'],
            ['{&var:3}',            '&var=val'],
            ['{&list}',             '&list=red,green,blue'],
            ['{&list*}',            '&list=red&list=green&list=blue'],
            ['{&keys}',             '&keys=semi,%3B,dot,.,comma,%2C'],
            ['{&keys*}',            '&semi=%3B&dot=.&comma=%2C'],
            ['{.null}',            ''],
            ['{.null,var}',        '.value'],
            ['X{.empty_keys*}',     'X'],
            ['X{.empty_keys}',      'X'],
            ['{/empty}',            '/'],
            ['{empty_member_list}', ''],
            ['{+empty_member_list}', ''],
            ['{#empty_member_list}', '#'],
            ['X{.empty_member_list}', 'X.'],
            ['{/empty_member_list}', '/'],
            ['{/empty_member_list*}', '/'],
            ['X{.empty_member_list*}', 'X.'],
            ['{#null,empty_member_list}', '#'],
            ['{;empty_member_list}', ';empty_member_list='],
            ['{;empty_member_list*}', ';empty_member_list'],
            ['{?empty_member_list}', '?empty_member_list='],
            ['{&empty_member_list}', '&empty_member_list='],
            ['{;null_empty_list}',   ';null_empty_list='],
            ['{;mixed_list}',        ';mixed_list=red,'],
            ['{;mixed_list*}',      ';mixed_list=red;mixed_list'],
            ['{?mixed_list*}',      '?mixed_list=red&mixed_list='],
            ['{&mixed_list*}',      '&mixed_list=red&mixed_list='],
            ['{;kv_empty*}',        ';a;b=x'],
            ['{?kv_empty*}',        '?a=&b=x'],
            ['{&kv_empty*}',        '&a=&b=x'],
            ['{kv_empty*}',         'a,b=x'],
            ['{+kv_empty*}',        'a,b=x'],
            ['{#kv_empty*}',        '#a,b=x'],
            ['X{.kv_empty*}',       'X.a.b=x'],
            ['{/kv_empty*}',        '/a/b=x'],
            ['{;kv_mid_empty*}',    ';b=x;a;c=y'],
            ['X{.kv_mid_empty*}',   'X.b=x.a.c=y'],
            ['{/kv_mid_empty*}',    '/b=x/a/c=y'],
            ['{/kv_empty_pair*}',   '/'],
            ['{?kv_empty_pair*}',   '?='],
            ['{kv_empty_key*}',     '=x'],
            ['{+reserved_keys}',    'a/b,c/d,x%20y,v'],
            ['{+reserved_keys*}',   'a/b=c/d,x%20y=v'],
            ['{#reserved_keys}',    '#a/b,c/d,x%20y,v'],
            ['{#reserved_keys*}',   '#a/b=c/d,x%20y=v'],
            ['{?reserved_keys*}',   '?a%2Fb=c%2Fd&x%2520y=v'],
            // Test that missing expansions are skipped
            ['test{&missing*}',     'test'],
            // Test that multiple expansions can be set
            ['http://{var}/{var:2}{?keys*}', 'http://value/va?semi=%3B&dot=.&comma=%2C'],
            // Test more complex query string stuff
            ['http://www.test.com{+path}{?var,keys*}', 'http://www.test.com/foo/bar?var=value&semi=%3B&dot=.&comma=%2C'],
        ]);
    }

    /**
     * @dataProvider templateProvider
     *
     * @param array<string,mixed> $variables
     */
    public function testExpandsUriTemplates(string $template, string $expansion, array $variables): void
    {
        self::assertSame($expansion, UriTemplate::expand($template, $variables));
    }

    /**
     * @return array<string,array{0:string, 1:array<string,mixed>, 2:string}>
     */
    public static function literalProvider(): array
    {
        return [
            'no expressions' => ['foo', [], 'foo'],
            'expression with literal path' => ['/users/{id}', ['id' => '123'], '/users/123'],
            'pct encoded literal' => ['/files/%2F/{id}', ['id' => 'a'], '/files/%2F/a'],
            'apostrophe literal' => ["/users/o'hara/{id}", ['id' => 'a'], "/users/o'hara/a"],
            'unicode literal' => ["/caf\xC3\xA9/{id}", ['id' => 'a'], '/caf%C3%A9/a'],
            'emoji literal' => ["/\xF0\x9F\x98\x80/{id}", ['id' => 'a'], '/%F0%9F%98%80/a'],
        ];
    }

    /**
     * @dataProvider literalProvider
     *
     * @param array<string,mixed> $variables
     */
    public function testExpandsLiteralText(string $template, array $variables, string $expansion): void
    {
        self::assertSame($expansion, UriTemplate::expand($template, $variables));
    }

    /**
     * @return array<string,array{0:string}>
     */
    public static function invalidLiteralProvider(): array
    {
        return [
            'space' => ['foo bar'],
            'trailing percent' => ['foo%'],
            'short percent triplet' => ['foo%2'],
            'non-hex percent triplet' => ['foo%ZZ'],
            'double quote' => ['foo"bar'],
            'less-than' => ['foo<bar'],
            'greater-than' => ['foo>bar'],
            'backslash' => ['foo\bar'],
            'caret' => ['foo^bar'],
            'backtick' => ['foo`bar'],
            'pipe' => ['foo|bar'],
            'nul' => ["foo\x00bar"],
            'crlf' => ["foo\r\nbar"],
            'del' => ["foo\x7Fbar"],
            'c1 control' => ["foo\xC2\x80bar"],
            'invalid utf-8' => ["foo\xC3".'bar'],
            'invalid after expression' => ['/{id}/bad path'],
        ];
    }

    /**
     * @dataProvider invalidLiteralProvider
     */
    public function testRejectsInvalidLiteralText(string $template): void
    {
        $this->assertInvalidTemplate($template, ['id' => 'a']);
    }

    /**
     * @return array<string,array{0:string, 1:int}>
     */
    public static function invalidUtf8LiteralOffsetProvider(): array
    {
        return [
            'leading invalid byte' => ["\xC3foo", 0],
            'invalid byte after ascii' => ["foo\xC3bar", 3],
            'invalid byte after multibyte literal' => ["caf\xC3\xA9\xC3", 5],
            'overlong encoding' => ["foo\xC0\xAFbar", 3],
            'truncated multibyte at end' => ["abc\xE2\x82", 3],
            'invalid byte after expression' => ["/{id}/bad\xC3", 9],
        ];
    }

    /**
     * @dataProvider invalidUtf8LiteralOffsetProvider
     */
    public function testReportsExactOffsetsForInvalidUtf8Literals(string $template, int $offset): void
    {
        try {
            UriTemplate::expand($template, ['id' => 'a']);
            self::fail('Expected InvalidArgumentException was not thrown.');
        } catch (\InvalidArgumentException $e) {
            self::assertSame(
                \sprintf('Invalid URI template at offset %d: literal text must be valid UTF-8.', $offset),
                $e->getMessage()
            );
        }
    }

    /**
     * @return array<string,array{0:string, 1:array<string,mixed>, 2:string}>
     */
    public static function reservedExpansionPctTripletProvider(): array
    {
        return [
            'scalar reserved' => ['{+id}', ['id' => 'admin%2F'], 'admin%2F'],
            'scalar lowercase pct reserved' => ['{+id}', ['id' => 'admin%2f'], 'admin%2f'],
            'scalar fragment' => ['{#id}', ['id' => 'admin%2F'], '#admin%2F'],
            'scalar simple still encodes pct' => ['{id}', ['id' => 'admin%2F'], 'admin%252F'],
            'invalid pct remains encoded' => ['{+id}', ['id' => '%foo'], '%25foo'],
            'list reserved' => ['{+list}', ['list' => ['red%25', '%2Fgreen', 'blue ']], 'red%25,%2Fgreen,blue%20'],
            'map fragment' => ['{#keys}', ['keys' => ['key1' => 'val1%2F', 'key2' => 'val2%2F']], '#key1,val1%2F,key2,val2%2F'],
        ];
    }

    /**
     * @dataProvider reservedExpansionPctTripletProvider
     *
     * @param array<string,mixed> $variables
     */
    public function testReservedExpansionPreservesPctTriplets(string $template, array $variables, string $expansion): void
    {
        self::assertSame($expansion, UriTemplate::expand($template, $variables));
    }

    /**
     * @return array<string,array{0:string}>
     */
    public static function malformedDelimiterProvider(): array
    {
        return [
            'unmatched open brace' => ['{/id*'],
            'unmatched close brace' => ['/id*}'],
            'empty expression' => ['{}'],
            'nested expression' => ['{{var}}'],
            'nested expression after operator' => ['{?{var}}'],
        ];
    }

    /**
     * @dataProvider malformedDelimiterProvider
     */
    public function testRejectsMalformedTemplateDelimiters(string $template): void
    {
        $this->assertInvalidTemplate($template, ['var' => 'value', 'id' => 'thing']);
    }

    /**
     * @return array<string,array{0:string}>
     */
    public static function invalidOperatorOrVarlistProvider(): array
    {
        return [
            'unsupported equals operator' => ['{=path}'],
            'unsupported bang operator' => ['{!hello}'],
            'unsupported at operator' => ['{@hello}'],
            'unsupported pipe operator' => ['{|var*}'],
            'operator-like path varspec' => ['{/?id}'],
            'double query operator' => ['{??hello}'],
            'operator without varlist' => ['{?}'],
            'empty varspec before comma' => ['{,var}'],
            'empty varspec after comma' => ['{var,}'],
            'empty varspec between commas' => ['{var,,hello}'],
            'whitespace after comma' => ['/resolution{?x, y}'],
            'form feed after comma' => ["/resolution{?x,\fy}"],
        ];
    }

    /**
     * @dataProvider invalidOperatorOrVarlistProvider
     */
    public function testRejectsInvalidOperatorsAndVarlists(string $template): void
    {
        $this->assertInvalidTemplate($template, ['hello' => 'Hello World!', 'path' => '/foo/bar', 'var' => 'value', 'x' => '1024', 'y' => '768']);
    }

    /**
     * @return array<string,array{0:string, 1:array<string,mixed>, 2:string}>
     */
    public static function validVariableNameProvider(): array
    {
        return [
            'letters' => ['{var}', ['var' => 'value'], 'value'],
            'digits' => ['{42}', ['42' => 'answer'], 'answer'],
            'underscore' => ['{first_name}', ['first_name' => 'John'], 'John'],
            'dot separator' => ['{last.name}', ['last.name' => 'Doe'], 'Doe'],
            'pct encoded space in name' => ['{/Some%20Thing}', ['Some%20Thing' => 'foo'], '/foo'],
            'pct encoded unicode in name' => ['{?Stra%C3%9Fe}', ['Stra%C3%9Fe' => 'Gruner Weg'], '?Stra%C3%9Fe=Gruner%20Weg'],
            'long name' => ['{'.\str_repeat('a', 512).'}', [\str_repeat('a', 512) => 'value'], 'value'],
        ];
    }

    /**
     * @dataProvider validVariableNameProvider
     *
     * @param array<string,mixed> $variables
     */
    public function testExpandsValidVariableNames(string $template, array $variables, string $expansion): void
    {
        self::assertSame($expansion, UriTemplate::expand($template, $variables));
    }

    /**
     * @return array<string,array{0:string}>
     */
    public static function invalidVariableNameProvider(): array
    {
        return [
            'space' => ['{with space}'],
            'leading space' => ['{ leading_space}'],
            'trailing space' => ['{trailing_space }'],
            'hyphen' => ['/{default-graph-uri}'],
            'tilde' => ['/people/{~thing}'],
            'dollar' => ['{$var}'],
            'query delimiter in name' => ['/search{?x=1&admin}'],
            'matrix delimiter in name' => ['/users{;role;admin}'],
            'slash in name' => ['{a/b}'],
            'raw percent' => ['{bad%name}'],
            'short percent triplet' => ['{bad%2}'],
            'non-hex percent triplet' => ['{bad%ZZ}'],
            'leading dot' => ['{?.var}'],
            'trailing dot' => ['{var.}'],
            'double dot' => ['{var..name}'],
            'raw unicode' => ["{Stra\xC3\x9Fe}"],
            'default syntax' => ['{?empty=default,var}'],
            'join extension syntax' => ['?{-join|&|var,list}'],
            'pipe extension syntax' => ['x{?empty|foo=none}'],
            'extension after expression' => ['{var}{-prefix|/-/|var}'],
            'operator-like suffix' => ['/h{#hello+}'],
            'operator-like suffix after fragment literal' => ['/h#{hello+}'],
            'star-prefixed name' => ['{*keys?}'],
        ];
    }

    /**
     * @dataProvider invalidVariableNameProvider
     */
    public function testRejectsInvalidVariableNames(string $template): void
    {
        $this->assertInvalidTemplate($template);
    }

    /**
     * @return array<string,array{0:string, 1:array<array-key,mixed>, 2:string}>
     */
    public static function allNumericVariableNameProvider(): array
    {
        return [
            'integer key' => ['{0}', [0 => 'x'], 'x'],
            'non-canonical string key' => ['{01}', ['01' => 'x'], 'x'],
            'largest integer key' => ['{'.\PHP_INT_MAX.'}', [\PHP_INT_MAX => 'x'], 'x'],
            'beyond the integer range' => ['{'.\PHP_INT_MAX.'0}', [\PHP_INT_MAX.'0' => 'x'], 'x'],
            'multiple names' => ['{?0,1}', [0 => 'a', 1 => 'b'], '?0=a&1=b'],
        ];
    }

    /**
     * @dataProvider allNumericVariableNameProvider
     *
     * @param array<array-key,mixed> $variables
     */
    public function testExpandsAllNumericVariableNames(string $template, array $variables, string $expansion): void
    {
        self::assertSame($expansion, UriTemplate::expand($template, $variables));
    }

    public function testReportsEngineFailuresOnLongVariableNamesAsRuntimeException(): void
    {
        // Spec section 2.3 places no length limit on variable names, so a
        // grammar-valid long name must either expand or surface a PCRE
        // engine failure as a RuntimeException, never as invalid syntax.
        $name = \str_repeat('a', 20000);

        try {
            self::assertSame('ok', UriTemplate::expand('{'.$name.'}', [$name => 'ok']));
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('Unable to parse variable specifier', $e->getMessage());
        }
    }

    public function testReportsEngineFailuresDuringLiteralOffsetRecoveryAsRuntimeException(): void
    {
        $previous = \ini_get('pcre.backtrack_limit');
        self::assertNotFalse($previous);

        $caught = null;

        try {
            // Forces the offset-recovery pattern in validUtf8PrefixLength()
            // to fail while the primary tokenizer still reports invalid
            // UTF-8, which PCRE detects before the match limit applies.
            \ini_set('pcre.backtrack_limit', '0');
            UriTemplate::expand("A\xC3", []);
        } catch (\RuntimeException $e) {
            $caught = $e;
        } finally {
            \ini_set('pcre.backtrack_limit', $previous);
        }

        self::assertNotNull($caught);
        self::assertStringContainsString('Unable to process template', $caught->getMessage());
    }

    /**
     * @return array<string,array{0:string, 1:array<string,mixed>, 2:string}>
     */
    public static function validModifierProvider(): array
    {
        return [
            'prefix one' => ['{var:1}', ['var' => 'value'], 'v'],
            'prefix max' => ['{var:9999}', ['var' => 'value'], 'value'],
            'explode scalar' => ['{var*}', ['var' => 'value'], 'value'],
            'explode list' => ['{/list*}', ['list' => ['red', 'green']], '/red/green'],
        ];
    }

    /**
     * @dataProvider validModifierProvider
     *
     * @param array<string,mixed> $variables
     */
    public function testExpandsValidModifiers(string $template, array $variables, string $expansion): void
    {
        self::assertSame($expansion, UriTemplate::expand($template, $variables));
    }

    /**
     * @return array<string,array{0:string, 1:array<string,mixed>, 2:string}>
     */
    public static function unicodePrefixProvider(): array
    {
        return [
            'simple first unicode character' => ['{var:1}', ['var' => "\xC3\xA9clair"], '%C3%A9'],
            'simple unicode and ascii characters' => ['{var:2}', ['var' => "\xC3\xA9clair"], '%C3%A9c'],
            'simple three-byte unicode character' => ['{var:1}', ['var' => "\xE2\x82\xACuro"], '%E2%82%AC'],
            'simple four-byte unicode character' => ['{var:1}', ['var' => "\xF0\x9F\x92\xA9rest"], '%F0%9F%92%A9'],
            'reserved unicode and slash characters' => ['{+var:2}', ['var' => "\xC3\xA9/clair"], '%C3%A9/'],
            'query unicode character' => ['{?var:1}', ['var' => "\xC3\xA9clair"], '?var=%C3%A9'],
            'pct triplet counts as one character' => ['{var:1}', ['var' => '%2Fabc'], '%252F'],
            'reserved pct triplet counts as one character' => ['{+var:1}', ['var' => '%2Fabc'], '%2F'],
            'simple pct code point counts as one character' => ['{var:1}', ['var' => '%C3%A9llo'], '%25C3%25A9'],
            'simple pct code point and ascii character' => ['{var:2}', ['var' => '%C3%A9llo'], '%25C3%25A9l'],
            'reserved pct code point counts as one character' => ['{+var:1}', ['var' => '%C3%A9llo'], '%C3%A9'],
            'reserved lowercase pct code point' => ['{+var:1}', ['var' => '%c3%a9llo'], '%c3%a9'],
            'reserved three triplet pct code point' => ['{+var:1}', ['var' => '%E2%82%ACx'], '%E2%82%AC'],
            'reserved four triplet pct code point' => ['{+var:1}', ['var' => '%F0%9F%92%A9rest'], '%F0%9F%92%A9'],
            'reserved four triplet pct code point and ascii characters' => ['{+var:3}', ['var' => '%F0%9F%92%A9rest'], '%F0%9F%92%A9re'],
            'fragment pct code point counts as one character' => ['{#var:1}', ['var' => '%C3%A9llo'], '#%C3%A9'],
            'query pct code point counts as one character' => ['{?var:1}', ['var' => '%C3%A9llo'], '?var=%25C3%25A9'],
            'lone lead triplet counts as one character' => ['{+var:2}', ['var' => '%C3xyz'], '%C3x'],
            'overlong pct sequence counts per triplet' => ['{+var:1}', ['var' => '%E0%80%80'], '%E0'],
        ];
    }

    /**
     * @dataProvider unicodePrefixProvider
     *
     * @param array<string,mixed> $variables
     */
    public function testExpandsUnicodePrefixes(string $template, array $variables, string $expansion): void
    {
        self::assertSame($expansion, UriTemplate::expand($template, $variables));
    }

    /**
     * @return array<string,array{0:string, 1:array<string,mixed>, 2:string}>
     */
    public static function unicodeNormalizationProvider(): array
    {
        return [
            // RFC 6570 section 1.6 leaves NFC normalization of user-provided
            // values to the caller, so canonically equivalent inputs differ.
            'nfd value passes through' => ['{var}', ['var' => "e\xCC\x81"], 'e%CC%81'],
            'nfc value passes through' => ['{var}', ['var' => "\xC3\xA9"], '%C3%A9'],
            'nfd literal passes through' => ["e\xCC\x81/{var}", ['var' => 'v'], 'e%CC%81/v'],
            'prefix splits decomposed sequence' => ['{var:1}', ['var' => "e\xCC\x81f"], 'e'],
            'nfd map member passes through' => ['{?x*}', ['x' => ['k' => "e\xCC\x81"]], '?k=e%CC%81'],
        ];
    }

    /**
     * @dataProvider unicodeNormalizationProvider
     *
     * @param array<string,mixed> $variables
     */
    public function testDoesNotNormalizeUnicode(string $template, array $variables, string $expansion): void
    {
        self::assertSame($expansion, UriTemplate::expand($template, $variables));
    }

    public function testRejectsInvalidUtf8PrefixValues(): void
    {
        $this->assertInvalidTemplate('{var:1}', ['var' => "\xC3"]);
    }

    public function testRejectsInvalidUtf8AfterSelectedPrefix(): void
    {
        $this->assertInvalidTemplate('{var:1}', ['var' => "a\xFF"]);
    }

    /**
     * @return array<string,array{0:string, 1:array<string,mixed>, 2:string}>
     */
    public static function invalidUtf8MemberPathProvider(): array
    {
        return [
            'list member' => ['{x}', ['x' => ['ok', "\xC3\x28"]], 'x[1]'],
            'map value' => ['{?x*}', ['x' => ['a' => "\xC3\x28"]], 'x[a]'],
            'map key' => ['{?x*}', ['x' => ["\xC3\x28" => 'v']], 'x[\xC3(]'],
            'nested value' => ['{?x*}', ['x' => ['k' => ['n' => "\xC3\x28"]]], 'x[k][n]'],
            'nested key' => ['{?x*}', ['x' => ['k' => ["\xC3\x28" => 'v']]], 'x[k][\xC3(]'],
        ];
    }

    /**
     * @dataProvider invalidUtf8MemberPathProvider
     *
     * @param array<string,mixed> $variables
     */
    public function testReportsMemberPathsForInvalidUtf8(string $template, array $variables, string $path): void
    {
        try {
            UriTemplate::expand($template, $variables);
            self::fail('Expected InvalidArgumentException was not thrown.');
        } catch (\InvalidArgumentException $e) {
            self::assertStringContainsString(\sprintf('variable "%s"', $path), $e->getMessage());
            // Exception messages must stay valid UTF-8 for consumers that
            // serialize them, such as json_encode-based loggers.
            self::assertSame(1, \preg_match('//u', $e->getMessage()));
        }
    }

    public function testEscapesInvalidUtf8KeysInShapeErrorMessages(): void
    {
        try {
            UriTemplate::expand('{?x*}', ['x' => ["\xC3\x28" => new \stdClass()]]);
            self::fail('Expected InvalidArgumentException was not thrown.');
        } catch (\InvalidArgumentException $e) {
            self::assertStringContainsString('variable "x[\xC3(]"', $e->getMessage());
            self::assertSame(1, \preg_match('//u', $e->getMessage()));
        }
    }

    /**
     * @return array<string,array{0:string}>
     */
    public static function invalidUtf8ExpressionProvider(): array
    {
        return [
            'lone invalid byte' => ["{\xC3}"],
            'invalid byte after name' => ["{bad\xC3}"],
            'invalid byte after literal text' => ["/ok/{bad\xC3}"],
            'invalid byte in variable list' => ["{?x,\xC3}"],
            'invalid byte before whitespace' => ["{bad\xC3 }"],
        ];
    }

    /**
     * @dataProvider invalidUtf8ExpressionProvider
     */
    public function testEscapesInvalidUtf8ExpressionsInErrorMessages(string $template): void
    {
        try {
            UriTemplate::expand($template, []);
            self::fail('Expected InvalidArgumentException was not thrown.');
        } catch (\InvalidArgumentException $e) {
            self::assertStringContainsString('\xC3', $e->getMessage());
            self::assertSame(1, \preg_match('//u', $e->getMessage()));
        }
    }

    /**
     * @return array<string,array{0:string, 1:array<string,mixed>, 2:string, 3:string}>
     */
    public static function diagnosticControlByteProvider(): array
    {
        return [
            'nul expression' => ["{bad\x00}", [], '{bad\x00}', "\x00"],
            'esc map key' => ['{?x*}', ['x' => ["red\x1B[31m" => new \stdClass()]], 'variable "x[red\x1B[31m]"', "\x1B"],
            'del map key' => ['{?x*}', ['x' => ["gone\x7F" => new \stdClass()]], 'variable "x[gone\x7F]"', "\x7F"],
            'u+0080 expression' => ["{bad\u{0080}}", [], '{bad\x80}', "\u{0080}"],
            'u+009b map key' => ['{?x*}', ['x' => ["key\u{009B}" => new \stdClass()]], 'variable "x[key\x9B]"', "\u{009B}"],
            'c1 before malformed byte' => ["{bad\u{009B}\xFF}", [], '{bad\xC2\x9B\xFF}', "\u{009B}"],
        ];
    }

    /**
     * @dataProvider diagnosticControlByteProvider
     *
     * @param array<string,mixed> $variables
     */
    public function testEscapesControlBytesInErrorMessages(string $template, array $variables, string $fragment, string $rawByte): void
    {
        try {
            UriTemplate::expand($template, $variables);
            self::fail('Expected InvalidArgumentException was not thrown.');
        } catch (\InvalidArgumentException $e) {
            self::assertStringContainsString($fragment, $e->getMessage());
            self::assertStringNotContainsString($rawByte, $e->getMessage());
            self::assertSame(1, \preg_match('//u', $e->getMessage()));
        }
    }

    /**
     * @return array<string,array{0:string}>
     */
    public static function invalidModifierProvider(): array
    {
        return [
            'zero prefix' => ['{var:0}'],
            'empty prefix' => ['{var:}'],
            'leading zero prefix' => ['{var:01}'],
            'negative prefix' => ['{var:-1}'],
            'non numeric prefix' => ['{var:prefix}'],
            'alphanumeric prefix' => ['{var:1a}'],
            'too large prefix' => ['{var:10000}'],
            'prefix and explode' => ['{hello:2*}'],
            'matrix prefix and explode' => ['{;keys:1*}'],
            'colon star' => ['{var:*}'],
            'double explode' => ['{var**}'],
            'question suffix' => ['{example:color?}'],
        ];
    }

    /**
     * @dataProvider invalidModifierProvider
     */
    public function testRejectsInvalidModifiers(string $template): void
    {
        $this->assertInvalidTemplate($template);
    }

    /**
     * @return array<string,array{0:string, 1:array<string,mixed>}>
     */
    public static function prefixOnCompositeProvider(): array
    {
        return [
            'list simple' => ['{list:1}', ['list' => ['red', 'green']]],
            'map simple' => ['{keys:1}', ['keys' => ['semi' => ';']]],
            'reserved map' => ['{+keys:1}', ['keys' => ['semi' => ';']]],
            'matrix map' => ['{;keys:1}', ['keys' => ['semi' => ';']]],
            'list with null member' => ['{x:1}', ['x' => ['red', null]]],
            'map with null member' => ['{x:1}', ['x' => ['a' => null, 'b' => 'v']]],
            'all null list' => ['{x:1}', ['x' => [null]]],
            'path all null list' => ['{/x:3}', ['x' => [null, null]]],
        ];
    }

    /**
     * @dataProvider prefixOnCompositeProvider
     *
     * @param array<string,mixed> $variables
     */
    public function testRejectsPrefixModifiersOnCompositeValues(string $template, array $variables): void
    {
        $this->assertInvalidTemplate($template, $variables);
    }

    public function testIgnoresPrefixModifiersOnUndefinedVariables(): void
    {
        self::assertSame('', UriTemplate::expand('{missing:1}', []));
        self::assertSame('', UriTemplate::expand('{missing:1}', ['missing' => null]));
        self::assertSame('', UriTemplate::expand('{list:1}', ['list' => []]));
    }

    /**
     * @return array<string,array{0:string, 1:array<string,mixed>, 2:string}>
     */
    public static function allNullMapProvider(): array
    {
        return [
            'prefix on all null map' => ['{x:1}', ['x' => ['a' => null]], ''],
            'query prefix on all null map' => ['{?x:2}', ['x' => ['a' => null]], ''],
            'label prefix on all null map' => ['X{.x:1}', ['x' => ['a' => null]], 'X'],
            'remaining variables still expand' => ['{x:1,y}', ['x' => ['a' => null], 'y' => 'v'], 'v'],
            'all null map query' => ['{?m}', ['m' => ['k' => null]], ''],
        ];
    }

    /**
     * @dataProvider allNullMapProvider
     *
     * @param array<string,mixed> $variables
     */
    public function testTreatsAllNullMapsAsUndefined(string $template, array $variables, string $expansion): void
    {
        self::assertSame($expansion, UriTemplate::expand($template, $variables));
    }

    /**
     * @return array<string,array{0:string, 1:array<string,mixed>, 2:string}>
     */
    public static function allNullListProvider(): array
    {
        return [
            'simple' => ['{l}', ['l' => [null]], ''],
            'simple exploded' => ['{l*}', ['l' => [null]], ''],
            'reserved' => ['{+l}', ['l' => [null]], ''],
            'reserved exploded' => ['{+l*}', ['l' => [null]], ''],
            'fragment' => ['{#l}', ['l' => [null]], '#'],
            'fragment exploded' => ['{#l*}', ['l' => [null]], '#'],
            'label' => ['{.l}', ['l' => [null]], '.'],
            'label exploded' => ['{.l*}', ['l' => [null]], '.'],
            'path' => ['{/l}', ['l' => [null]], '/'],
            'path exploded' => ['{/l*}', ['l' => [null]], '/'],
            'path style' => ['{;l}', ['l' => [null]], ';l'],
            'path style exploded' => ['{;l*}', ['l' => [null]], ';'],
            'query' => ['{?l}', ['l' => [null]], '?l='],
            'query exploded' => ['{?l*}', ['l' => [null]], '?'],
            'query continuation' => ['{&l}', ['l' => [null]], '&l='],
            'query continuation exploded' => ['{&l*}', ['l' => [null]], '&'],
            'multiple null members' => ['{?l}', ['l' => [null, null]], '?l='],
            'mixed null and defined members' => ['{l}', ['l' => [null, 'x']], 'x'],
            'beside defined variable' => ['{x,y}', ['x' => [null], 'y' => 'z'], ',z'],
            'path beside defined variable' => ['{/x,y}', ['x' => [null], 'y' => 'z'], '//z'],
        ];
    }

    /**
     * @dataProvider allNullListProvider
     *
     * @param array<string,mixed> $variables
     */
    public function testTreatsAllNullListsAsDefined(string $template, array $variables, string $expansion): void
    {
        self::assertSame($expansion, UriTemplate::expand($template, $variables));
    }

    /**
     * @return array<string,array{0:string, 1:array<string,mixed>, 2:string}>
     */
    public static function supportedVariableShapeProvider(): array
    {
        return [
            'string' => ['{x}', ['x' => 'value'], 'value'],
            'int zero' => ['{x}', ['x' => 0], '0'],
            'float' => ['{x}', ['x' => 37.76], '37.76'],
            'false' => ['{x}', ['x' => false], '0'],
            'true' => ['{x}', ['x' => true], '1'],
            'empty string query' => ['{?x}', ['x' => ''], '?x='],
            'top-level null skipped' => ['{?x,y}', ['x' => null, 'y' => 'yes'], '?y=yes'],
            'stringable object' => ['{x}', ['x' => new StringableValue('ok')], 'ok'],
            'stringable object in list' => ['{x}', ['x' => [new StringableValue('ok')]], 'ok'],
            'stringable object in map' => ['{?x*}', ['x' => ['a' => new StringableValue('ok')]], '?a=ok'],
            'empty stringable in exploded path map' => ['{/x*}', ['x' => ['name' => new StringableValue('')]], '/name'],
            'list' => ['{/x*}', ['x' => ['red', 'green']], '/red/green'],
            'map' => ['{?x*}', ['x' => ['a' => 'b']], '?a=b'],
            'nested exploded map extension' => ['{?x*}', ['x' => ['a' => ['b' => 'c']]], '?a%5Bb%5D=c'],
            'reserved key encoding collision keeps both pairs' => ['{+x*}', ['x' => ['a b' => '1', 'a%20b' => '2']], 'a%20b=1,a%20b=2'],
            'null list member skipped' => ['{?x*}', ['x' => ['a', null]], '?x=a'],
            'null map member skipped' => ['{?x*}', ['x' => ['a' => null, 'b' => 'c']], '?b=c'],
            'null member in simple list' => ['{x}', ['x' => ['red', null, 'blue']], 'red,blue'],
            'all null map members undefined' => ['X{.x}', ['x' => ['a' => null]], 'X'],
            'all null list members defined' => ['{#x}', ['x' => [null]], '#'],
            'null nested query leaf skipped' => ['{?x*}', ['x' => ['a' => ['b' => null, 'c' => 'v']]], '?a%5Bc%5D=v'],
            'null member with invalid utf-8 key skipped' => ['{?x*}', ['x' => ["\xC3\x28" => null, 'kept' => 'v']], '?kept=v'],
            'nested null member with invalid utf-8 key skipped' => ['{?x*}', ['x' => ['k' => ["\xC3\x28" => null], 'kept' => 'v']], '?kept=v'],
            'all nested members null with invalid utf-8 key undefined' => ['{?x*}', ['x' => ['k' => ["\xC3\x28" => null]]], ''],
            'valid multibyte value' => ['{x}', ['x' => "caf\xC3\xA9 \xF0\x9F\x98\x80"], 'caf%C3%A9%20%F0%9F%98%80'],
            'false in query' => ['{?x}', ['x' => false], '?x=0'],
            'bools in list' => ['{x}', ['x' => [true, false]], '1,0'],
            'false in exploded map' => ['{?x*}', ['x' => ['a' => false]], '?a=0'],
            'bools in nested query map' => ['{?x*}', ['x' => ['a' => ['b' => false, 'c' => true]]], '?a%5Bb%5D=0&a%5Bc%5D=1'],
        ];
    }

    /**
     * @dataProvider supportedVariableShapeProvider
     *
     * @param array<string,mixed> $variables
     */
    public function testExpandsSupportedVariableShapes(string $template, array $variables, string $expansion): void
    {
        self::assertSame($expansion, UriTemplate::expand($template, $variables));
    }

    /**
     * @return array<string,array{0:string, 1:array<string,mixed>, 2:string}>
     */
    public static function floatValueProvider(): array
    {
        return [
            'fixed notation' => ['{x}', ['x' => 3.5], '3.5'],
            'negative fixed notation' => ['{x}', ['x' => -2.25], '-2.25'],
            'scientific notation plus encoded' => ['{x}', ['x' => 1.0E+20], '1.0E%2B20'],
            'scientific notation plus reserved' => ['{+x}', ['x' => 1.0E+20], '1.0E+20'],
            'scientific notation plus fragment' => ['{#x}', ['x' => 1.0E+20], '#1.0E+20'],
            'scientific notation plus query' => ['{?x}', ['x' => 1.0E+20], '?x=1.0E%2B20'],
            'float in list' => ['{x}', ['x' => [1.5, 2.5]], '1.5,2.5'],
            'float in exploded map' => ['{?x*}', ['x' => ['a' => 3.5]], '?a=3.5'],
            'float in nested query map' => ['{?x*}', ['x' => ['a' => ['b' => 3.5]]], '?a%5Bb%5D=3.5'],
            'float prefix' => ['{x:3}', ['x' => 37.5], '37.'],
        ];
    }

    /**
     * @dataProvider floatValueProvider
     *
     * @param array<string,mixed> $variables
     */
    public function testExpandsFloatValues(string $template, array $variables, string $expansion): void
    {
        self::assertSame($expansion, UriTemplate::expand($template, $variables));
    }

    public function testFloatExpansionFollowsThePrecisionIniSetting(): void
    {
        $previous = \ini_get('precision');
        self::assertNotFalse($previous);

        try {
            \ini_set('precision', '14');
            self::assertSame('0.3', UriTemplate::expand('{x}', ['x' => 0.1 + 0.2]));
            self::assertSame('1.0E-5', UriTemplate::expand('{x}', ['x' => 0.00001]));

            \ini_set('precision', '17');
            self::assertSame('0.30000000000000004', UriTemplate::expand('{x}', ['x' => 0.1 + 0.2]));
        } finally {
            \ini_set('precision', $previous);
        }
    }

    public function testFloatExpansionIsLocaleIndependent(): void
    {
        $previous = \setlocale(\LC_NUMERIC, '0');
        self::assertNotFalse($previous);

        if (\setlocale(\LC_NUMERIC, 'de_DE.UTF-8', 'de_DE.utf8', 'de_DE', 'fr_FR.UTF-8', 'fr_FR.utf8', 'fr_FR') === false) {
            self::markTestSkipped('No comma-decimal locale is available.');
        }

        try {
            self::assertSame('3.5', UriTemplate::expand('{x}', ['x' => 3.5]));
            self::assertSame('?x=3.5', UriTemplate::expand('{?x}', ['x' => 3.5]));
            self::assertSame('?a=3.5', UriTemplate::expand('{?x*}', ['x' => ['a' => 3.5]]));
            self::assertSame('?a%5Bb%5D=3.5', UriTemplate::expand('{?x*}', ['x' => ['a' => ['b' => 3.5]]]));
            self::assertSame('1.0E%2B25', UriTemplate::expand('{x}', ['x' => 1.0E+25]));
        } finally {
            \setlocale(\LC_NUMERIC, $previous);
        }
    }

    /**
     * @return array<string,array{0:string, 1:array<string,mixed>}>
     */
    public static function invalidVariableShapeProvider(): array
    {
        $resource = \fopen('php://temp', 'r');
        self::assertIsResource($resource);

        return [
            'stdClass scalar' => ['{x}', ['x' => new \stdClass()]],
            'closure scalar' => ['{x}', ['x' => static function (): void {}]],
            'resource scalar' => ['{x}', ['x' => $resource]],
            'object in list' => ['{?x}', ['x' => [new \stdClass()]]],
            'object in map' => ['{?x}', ['x' => ['a' => new \stdClass()]]],
            'nested list in list' => ['{?x}', ['x' => [['a']]]],
            'nested array in unexploded map' => ['{?x}', ['x' => ['a' => ['b' => 'c']]]],
            'nested array in non-query exploded map' => ['{/x*}', ['x' => ['a' => ['b' => 'c']]]],
            'nested object in query extension' => ['{?x*}', ['x' => ['a' => ['b' => new \stdClass()]]]],
            'nested stringable object in query extension' => ['{?x*}', ['x' => ['a' => ['b' => new StringableValue('ok')]]]],
            'invalid utf-8 scalar' => ['{x}', ['x' => "\xC3"]],
            'invalid utf-8 reserved scalar' => ['{+x}', ['x' => "\xC3"]],
            'invalid utf-8 list member' => ['{x}', ['x' => ['ok', "\xC3"]]],
            'invalid utf-8 map key' => ['{?x*}', ['x' => ["\xC3" => 'v']]],
            'invalid utf-8 nested value' => ['{?x*}', ['x' => ['a' => ['b' => "\xC3"]]]],
            'invalid utf-8 nested key' => ['{?x*}', ['x' => ['a' => ["\xC3" => 'v']]]],
            'nan scalar' => ['{x}', ['x' => \NAN]],
            'infinity scalar' => ['{x}', ['x' => \INF]],
            'negative infinity scalar' => ['{x}', ['x' => -\INF]],
            'nan in list' => ['{x}', ['x' => [\NAN]]],
            'infinity in map' => ['{?x*}', ['x' => ['a' => \INF]]],
            'nan in nested query map' => ['{?x*}', ['x' => ['a' => ['b' => \NAN]]]],
        ];
    }

    /**
     * @dataProvider invalidVariableShapeProvider
     *
     * @param array<string,mixed> $variables
     */
    public function testRejectsInvalidVariableShapes(string $template, array $variables): void
    {
        $this->assertInvalidTemplate($template, $variables);
    }

    public function testRejectsStringableLeavesInNestedQueryArrays(): void
    {
        try {
            UriTemplate::expand('{?x*}', ['x' => ['a' => ['b' => new StringableValue('ok')]]]);
            self::fail('Expected InvalidArgumentException was not thrown.');
        } catch (\InvalidArgumentException $e) {
            self::assertStringContainsString('variable "x[a][b]"', $e->getMessage());
            self::assertStringContainsString('expected scalar or nested array', $e->getMessage());
        }
    }

    public function testIgnoresUnusedInvalidVariableShapes(): void
    {
        self::assertSame('ok', UriTemplate::expand('{x}', ['x' => 'ok', 'unused' => new \stdClass()]));
    }

    /**
     * @return array<string,array{0:string, 1:string}>
     */
    public static function repeatedStringableProvider(): array
    {
        return [
            'same expression' => ['{x,x}', '1,1'],
            'separate expressions' => ['{x}-{x}', '1-1'],
            'mixed operators' => ['{x}{?x}', '1?x=1'],
        ];
    }

    /**
     * @dataProvider repeatedStringableProvider
     */
    public function testReusesStringableValuesForEveryOccurrence(string $template, string $expansion): void
    {
        self::assertSame($expansion, UriTemplate::expand($template, ['x' => new CountingStringable()]));
    }

    public function testCallsToStringExactlyOncePerExpansion(): void
    {
        $counter = new CountingStringable();

        self::assertSame('11?x=1', UriTemplate::expand('{x}{x}{?x}', ['x' => $counter]));
        self::assertSame(1, $counter->calls);

        self::assertSame('22?x=2', UriTemplate::expand('{x}{x}{?x}', ['x' => $counter]));
        self::assertSame(2, $counter->calls);
    }

    /**
     * @return array<string,array{0:string}>
     */
    public static function definednessTemplateProvider(): array
    {
        return [
            'undefined referenced first' => ['{x}{y}{x}'],
            'undefined referenced last' => ['{y}{x}'],
        ];
    }

    /**
     * @dataProvider definednessTemplateProvider
     */
    public function testBindsDefinednessBeforeValuesAreFormed(string $template): void
    {
        $slot = null;
        $mutator = new SideEffectStringable('v', static function () use (&$slot): void {
            $slot = 'now defined';
        });

        self::assertSame('v', UriTemplate::expand($template, ['x' => &$slot, 'y' => $mutator]));
    }

    public function testDetachesValuesBeforeValuesAreFormed(): void
    {
        $slot = 'before';
        $mutator = new SideEffectStringable('first', static function () use (&$slot): void {
            $slot = 'after';
        });
        $variables = ['a' => $mutator, 'b' => &$slot];

        self::assertSame('first-before', UriTemplate::expand('{a}-{b}', $variables));

        $slot = 'before';

        self::assertSame('before-first-before', UriTemplate::expand('{b}-{a}-{b}', $variables));
    }

    public function testFormsFloatsBeforeExpansionBegins(): void
    {
        $precision = (string) \ini_get('precision');
        $mutator = new SideEffectStringable('M', static function (): void {
            \ini_set('precision', '3');
        });

        try {
            \ini_set('precision', '14');

            self::assertSame(
                '1.23456789-M-1.23456789',
                UriTemplate::expand('{x}-{m}-{x}', ['x' => 1.23456789, 'm' => $mutator])
            );

            \ini_set('precision', '14');

            self::assertSame(
                '?a%5Bb%5D=1.23456789M&a%5Bb%5D=1.23456789',
                UriTemplate::expand('{?q*}{m}{&q*}', ['q' => ['a' => ['b' => 1.23456789]], 'm' => $mutator])
            );
        } finally {
            \ini_set('precision', $precision);
        }
    }

    public function testValidatesEveryExpressionBeforeValuesAreFormed(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('unsupported operator');

        UriTemplate::expand('{x}{!y}', ['x' => new \stdClass()]);
    }

    public function testValidatesStringValuesWhileValuesAreFormed(): void
    {
        $throwing = new SideEffectStringable('v', static function (): void {
            throw new \DomainException('should not be reached');
        });

        try {
            UriTemplate::expand('{a}{b}', ['a' => "\xC3\x28", 'b' => $throwing]);
            self::fail('Expected an InvalidArgumentException.');
        } catch (\InvalidArgumentException $e) {
            self::assertStringContainsString('must be valid UTF-8', $e->getMessage());
        }
    }

    public function testBindsRejectionsBeforeValuesAreFormed(): void
    {
        $member = new \stdClass();
        $mutator = new SideEffectStringable('a', static function () use (&$member): void {
            $member = 'now-valid';
        });

        try {
            UriTemplate::expand('{a}{b}{b}', ['a' => $mutator, 'b' => [&$member]]);
            self::fail('Expected an InvalidArgumentException.');
        } catch (\InvalidArgumentException $e) {
            self::assertStringContainsString('expected scalar or stringable object; got stdClass', $e->getMessage());
        }

        self::assertSame('now-valid', $member);
    }

    public function testRejectsSharedArrayGraphMembersWithoutMaterializingThem(): void
    {
        $graph = ['a', 'b'];
        for ($i = 0; $i < 25; ++$i) {
            $graph = [$graph, $graph];
        }

        $start = \microtime(true);

        try {
            UriTemplate::expand('{x}', ['x' => $graph]);
            self::fail('Expected an InvalidArgumentException.');
        } catch (\InvalidArgumentException $e) {
            self::assertStringContainsString('expected scalar or stringable object; got array', $e->getMessage());
        }

        self::assertLessThan(1.0, \microtime(true) - $start);
    }

    public function testExpandsRepeatedExpressionsWithoutPerOccurrenceStorage(): void
    {
        $template = \str_repeat('{x}', 200000);

        self::assertSame(\str_repeat('y', 200000), UriTemplate::expand($template, ['x' => 'y']));
    }

    public function testExpandsManyDistinctExpressionsWithoutRetainingParses(): void
    {
        $template = '';
        for ($i = 0; $i < 20000; ++$i) {
            $template .= '{v'.$i.'}';
        }

        self::assertSame('', UriTemplate::expand($template, []));
    }

    public function testSharesNestedQueryStructuresThatCannotChange(): void
    {
        $shared = ['v', 'w'];

        self::assertSame(
            '?a%5B0%5D%5B0%5D=v&a%5B0%5D%5B1%5D=w&a%5B1%5D%5B0%5D=v&a%5B1%5D%5B1%5D=w',
            UriTemplate::expand('{?x*}', ['x' => ['a' => [$shared, $shared]]])
        );
    }

    public function testRejectsReferenceCyclesWithoutMaterializingThem(): void
    {
        $cycle = [];
        $cycle[0] = &$cycle;
        $cycle[1] = &$cycle;

        $this->assertInvalidTemplate('{?x*}', ['x' => ['k' => $cycle]]);
    }

    /**
     * @return array<string,array{0:mixed}>
     */
    public static function detachedMutationProvider(): array
    {
        return [
            'mutation to infinity' => [\INF],
            'mutation to array' => [[]],
        ];
    }

    /**
     * @dataProvider detachedMutationProvider
     *
     * @param mixed $mutation
     */
    public function testListMembersAreDetachedBeforeValuesAreFormed($mutation): void
    {
        $slot = 'ok';
        $mutator = new SideEffectStringable('first', static function () use (&$slot, $mutation): void {
            $slot = $mutation;
        });

        self::assertSame('first,ok', UriTemplate::expand('{x}', ['x' => [$mutator, &$slot]]));
    }

    /**
     * @return array<string,array{0:string, 1:array<string,mixed>}>
     */
    public static function repeatedVarspecPrefixOnCompositeProvider(): array
    {
        return [
            'prefix before explode' => ['{x:1,x*}', ['x' => ['red', 'green']]],
            'explode before prefix' => ['{x*,x:1}', ['x' => ['red', 'green']]],
            'all null list prefix before simple' => ['{l:1}{l}', ['l' => [null]]],
            'all null list prefix after simple' => ['{l}{l:1}', ['l' => [null]]],
        ];
    }

    /**
     * @dataProvider repeatedVarspecPrefixOnCompositeProvider
     *
     * @param array<string,mixed> $variables
     */
    public function testRejectsPrefixModifiersOnRepeatedCompositeOccurrences(string $template, array $variables): void
    {
        $this->assertInvalidTemplate($template, $variables);
    }

    public function testNestedQueryReferencesAreDetachedBeforeValuesAreFormed(): void
    {
        $leaf = 'safe';
        $mutator = new SideEffectStringable('v', static function () use (&$leaf): void {
            $leaf = 'evil';
        });
        $variables = ['m' => ['a' => ['b' => &$leaf]], 'x' => $mutator];

        self::assertSame('?a%5Bb%5D=safev&a%5Bb%5D=safe', UriTemplate::expand('{?m*}{x}{&m*}', $variables));

        $leaf = 'safe';

        self::assertSame('v?a%5Bb%5D=safe', UriTemplate::expand('{x}{?m*}', $variables));
    }

    public function testNestedQueryReferenceMutationToInvalidValuesHasNoEffect(): void
    {
        $leaf = 'safe';
        $mutator = new SideEffectStringable('v', static function () use (&$leaf): void {
            $leaf = \INF;
        });

        self::assertSame('v?a%5Bb%5D=safe', UriTemplate::expand('{x}{?m*}', ['m' => ['a' => ['b' => &$leaf]], 'x' => $mutator]));
    }

    public function testRejectsRecursiveArrayVariables(): void
    {
        $recursive = [];
        $recursive['self'] = &$recursive;

        $this->assertInvalidTemplate('{?recursive*}', ['recursive' => $recursive]);
    }

    public function testRejectsTooDeepArrayVariables(): void
    {
        $tooDeep = 'leaf';

        for ($i = 0; $i < 66; ++$i) {
            $tooDeep = ['x' => $tooDeep];
        }

        $this->assertInvalidTemplate('{?x*}', ['x' => ['a' => $tooDeep]]);
    }

    public function testAcceptsMaximumDepthArrayVariables(): void
    {
        $deepest = 'leaf';
        $key = 'a';

        for ($i = 0; $i < 64; ++$i) {
            $deepest = ['x' => $deepest];
            $key .= '%5Bx%5D';
        }

        self::assertSame(
            '?'.$key.'=leaf',
            UriTemplate::expand('{?x*}', ['x' => ['a' => $deepest]])
        );
    }

    /**
     * @return array<string,array{0:string, 1:array<string,mixed>, 2:string}>
     */
    public static function deterministicArrayShapeProvider(): array
    {
        return [
            'dense list' => ['{?x*}', ['x' => ['a', 'b']], '?x=a&x=b'],
            'zero-based sparse numeric map' => ['{?x*}', ['x' => [0 => 'a', 2 => 'b']], '?0=a&2=b'],
            'sparse numeric map' => ['{?x*}', ['x' => [1 => 'a', 3 => 'b']], '?1=a&3=b'],
            'mixed map' => ['{?x*}', ['x' => [0 => 'a', 'b' => 'c']], '?0=a&b=c'],
        ];
    }

    /**
     * @dataProvider deterministicArrayShapeProvider
     *
     * @param array<string,mixed> $variables
     */
    public function testExpandsDeterministicArrayShapes(string $template, array $variables, string $expansion): void
    {
        self::assertSame($expansion, UriTemplate::expand($template, $variables));
    }

    /**
     * @return array<int,array{0:string, 1:array{operator:string, values:array<int,array{value:string, modifier:string, position?:int}>}}>
     */
    public static function expressionProvider(): array
    {
        return [
            [
                '{+var*}', [
                    'operator' => '+',
                    'values' => [
                        ['modifier' => '*', 'value' => 'var'],
                    ],
                ],
            ],
            [
                '{?keys,var,val}', [
                    'operator' => '?',
                    'values' => [
                        ['value' => 'keys', 'modifier' => ''],
                        ['value' => 'var', 'modifier' => ''],
                        ['value' => 'val', 'modifier' => ''],
                    ],
                ],
            ],
            [
                '{+x,hello,y}', [
                    'operator' => '+',
                    'values' => [
                        ['value' => 'x', 'modifier' => ''],
                        ['value' => 'hello', 'modifier' => ''],
                        ['value' => 'y', 'modifier' => ''],
                    ],
                ],
            ],
        ];
    }

    /**
     * @dataProvider expressionProvider
     *
     * @param array{operator:string, values:array<int,array{value:string, modifier:string, position?:int}>} $data
     */
    public function testParsesExpressions(string $exp, array $data): void
    {
        $class = new \ReflectionClass(UriTemplate::class);

        $method = $class->getMethod('parseExpression');

        if (PHP_VERSION_ID < 80100) {
            $method->setAccessible(true);
        }

        $exp = \substr($exp, 1, -1);

        self::assertSame($data, $method->invokeArgs(null, [$exp]));
    }

    public static function nestedQueryKeyEncodingProvider(): array
    {
        return [
            'space in nested top-level key' => [
                '{?x*}',
                ['x' => ['a b' => ['c' => 'd']]],
                '?a%20b%5Bc%5D=d',
            ],
            'reserved slash in nested top-level key' => [
                '{?x*}',
                ['x' => ['a/b' => ['c' => 'd']]],
                '?a%2Fb%5Bc%5D=d',
            ],
            'percent triplet text in nested top-level key' => [
                '{?x*}',
                ['x' => ['a%2Fb' => ['c' => 'd']]],
                '?a%252Fb%5Bc%5D=d',
            ],
            'space in nested child key and value' => [
                '{?x*}',
                ['x' => ['a b' => ['c d' => 'e f']]],
                '?a%20b%5Bc%20d%5D=e%20f',
            ],
            'continuation operator nested key' => [
                '{&x*}',
                ['x' => ['a b' => ['c d' => 'e f']]],
                '&a%20b%5Bc%20d%5D=e%20f',
            ],
            'scalar map key remains encoded' => [
                '{?x*}',
                ['x' => ['a b' => 'c d']],
                '?a%20b=c%20d',
            ],
        ];
    }

    /**
     * @dataProvider nestedQueryKeyEncodingProvider
     */
    public function testNestedQueryKeysAreEncodedOnce(string $template, array $variables, string $expansion): void
    {
        self::assertSame($expansion, UriTemplate::expand($template, $variables));
    }

    public static function emptyNestedQueryArrayProvider(): array
    {
        return [
            'empty nested array before scalar sibling' => [
                '{?x*}',
                ['x' => ['empty' => [], 'b' => 'c']],
                '?b=c',
            ],
            'empty nested array after scalar sibling' => [
                '{?x*}',
                ['x' => ['b' => 'c', 'empty' => []]],
                '?b=c',
            ],
            'continuation operator empty nested array' => [
                '{&x*}',
                ['x' => ['empty' => [], 'b' => 'c']],
                '&b=c',
            ],
            'all nested arrays empty' => [
                '{?x*}',
                ['x' => ['a' => [], 'b' => []]],
                '',
            ],
            'empty nested array before next variable' => [
                '{?x*,y}',
                ['x' => ['empty' => []], 'y' => 'c'],
                '?y=c',
            ],
            'empty nested array after non-empty nested array' => [
                '{?x*}',
                ['x' => ['a' => ['b' => 'c'], 'empty' => []]],
                '?a%5Bb%5D=c',
            ],
            'empty scalar value is preserved' => [
                '{?x*}',
                ['x' => ['empty' => '', 'nested' => []]],
                '?empty=',
            ],
        ];
    }

    /**
     * @dataProvider emptyNestedQueryArrayProvider
     */
    public function testSkipsEmptyNestedQueryArrays(string $template, array $variables, string $expansion): void
    {
        self::assertSame($expansion, UriTemplate::expand($template, $variables));
    }

    /**
     * @return array<string,array{0:string, 1:array<string,mixed>, 2:string}>
     */
    public static function emptyStringKeyNestedQueryArrayProvider(): array
    {
        return [
            'top-level empty-string key with nested-array value' => [
                '{?x*}',
                ['x' => ['' => ['a' => 'v']]],
                '?%5Ba%5D=v',
            ],
            'top-level empty-string key with deeper nesting' => [
                '{?x*}',
                ['x' => ['' => ['a' => ['b' => 'v']]]],
                '?%5Ba%5D%5Bb%5D=v',
            ],
            'continuation operator top-level empty-string key' => [
                '{&x*}',
                ['x' => ['' => ['a' => 'v']]],
                '&%5Ba%5D=v',
            ],
            'top-level empty-string key with scalar value' => [
                '{?x*}',
                ['x' => ['' => 'v']],
                '?=v',
            ],
            'top-level empty-string key with empty nested array' => [
                '{?x*}',
                ['x' => ['' => []]],
                '',
            ],
            'nested empty-string key append syntax' => [
                '{?x*}',
                ['x' => ['a' => ['' => 'v']]],
                '?a%5B%5D=v',
            ],
        ];
    }

    /**
     * @dataProvider emptyStringKeyNestedQueryArrayProvider
     *
     * @param array<string,mixed> $variables
     */
    public function testExpandsEmptyStringKeysInNestedQueryArrays(string $template, array $variables, string $expansion): void
    {
        self::assertSame($expansion, UriTemplate::expand($template, $variables));
    }

    /**
     * @ticket https://github.com/guzzle/guzzle/issues/90
     */
    public function testAllowsNestedArrayExpansion(): void
    {
        $result = UriTemplate::expand('http://example.com{+path}{/segments}{?query,data*,foo*}', [
            'path' => '/foo/bar',
            'segments' => ['one', 'two'],
            'query' => 'test',
            'data' => [
                'more' => ['fun', 'ice cream'],
            ],
            'foo' => [
                'baz' => [
                    'bar' => 'fizz',
                    'test' => 'buzz',
                ],
                'bam' => 'boo',
            ],
        ]);

        self::assertSame('http://example.com/foo/bar/one,two?query=test&more%5B0%5D=fun&more%5B1%5D=ice%20cream&baz%5Bbar%5D=fizz&baz%5Btest%5D=buzz&bam=boo', $result);
    }

    public function testRejectsNativePhpSerialization(): void
    {
        $template = (new \ReflectionClass(UriTemplate::class))->newInstanceWithoutConstructor();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(UriTemplate::class.' should never be serialized');

        \serialize($template);
    }

    public function testRejectsNativePhpUnserialization(): void
    {
        $class = UriTemplate::class;

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage($class.' should never be unserialized');

        \unserialize(\sprintf('O:%d:"%s":0:{}', \strlen($class), $class));
    }

    /**
     * @return \Generator<int,array{0:string, 1:array<int,string>, 2:array<string,mixed>},mixed,void>
     */
    public static function specComplianceProvider(): \Generator
    {
        foreach (['spec-examples.json', 'spec-examples-by-section.json', 'extended-tests.json'] as $filename) {
            foreach (self::parseSpecExamples($filename) as $example) {
                yield $example;
            }
        }
    }

    /**
     * @dataProvider specComplianceProvider
     *
     * @param array<int,string>   $expansions
     * @param array<string,mixed> $variables
     */
    public function testSpecCompliance(string $template, array $expansions, array $variables): void
    {
        self::assertContains(UriTemplate::expand($template, $variables), $expansions);
    }

    /**
     * @return \Generator<int,array{0:string, 1:array<int,string>, 2:array<string,mixed>},mixed,void>
     */
    private static function parseSpecExamples(string $filename): \Generator
    {
        foreach (self::loadSpecFixture($filename) as $example) {
            $variables = $example['variables'];
            foreach ($example['testcases'] as $case) {
                yield [$case[0], (array) $case[1], $variables];
            }
        }
    }

    /**
     * @param array<string,mixed> $variables
     */
    private function assertInvalidTemplate(string $template, array $variables = []): void
    {
        $this->expectException(\InvalidArgumentException::class);

        UriTemplate::expand($template, $variables);
    }

    /**
     * @dataProvider invalidSpecProvider
     *
     * @param array<string,mixed> $variables
     */
    public function testRejectsInvalidSpecTemplates(string $template, array $variables): void
    {
        $this->assertInvalidTemplate($template, $variables);
    }

    /**
     * @return \Generator<string,array{0:string, 1:array<string,mixed>},mixed,void>
     */
    public static function invalidSpecProvider(): \Generator
    {
        foreach (self::loadSpecFixture('negative-tests.json') as $groupName => $group) {
            foreach ($group['testcases'] as $index => $case) {
                if ($case[1] !== false) {
                    continue;
                }

                yield \sprintf('%s #%d %s', $groupName, $index, $case[0]) => [
                    $case[0],
                    $group['variables'],
                ];
            }
        }
    }

    /**
     * @return array<string,array{level:int, variables:array<string,mixed>, testcases:array<int,array{0:string, 1:string|array<int,string>|false}>}>
     */
    private static function loadSpecFixture(string $filename): array
    {
        $contents = \file_get_contents(\sprintf('%s/../vendor/uri-template/tests/%s', __DIR__, $filename));
        self::assertIsString($contents);

        $decoded = \json_decode($contents, true);
        self::assertIsArray($decoded);

        return $decoded;
    }
}

final class StringableValue
{
    private string $value;

    public function __construct(string $value)
    {
        $this->value = $value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}

final class CountingStringable
{
    public int $calls = 0;

    public function __toString(): string
    {
        return (string) ++$this->calls;
    }
}

final class SideEffectStringable
{
    private string $value;

    private \Closure $sideEffect;

    public function __construct(string $value, \Closure $sideEffect)
    {
        $this->value = $value;
        $this->sideEffect = $sideEffect;
    }

    public function __toString(): string
    {
        ($this->sideEffect)();

        return $this->value;
    }
}
