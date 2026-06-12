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
            'mixed_list' => ['red', ''],
            'kv_empty' => ['a' => '', 'b' => 'x'],
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
            ['{;mixed_list*}',      ';mixed_list=red;mixed_list'],
            ['{?mixed_list*}',      '?mixed_list=red&mixed_list='],
            ['{&mixed_list*}',      '&mixed_list=red&mixed_list='],
            ['{;kv_empty*}',        ';a;b=x'],
            ['{?kv_empty*}',        '?a=&b=x'],
            ['X{.kv_empty*}',       'X.a=.b=x'],
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
            'reserved unicode and slash characters' => ['{+var:2}', ['var' => "\xC3\xA9/clair"], '%C3%A9/'],
            'query unicode character' => ['{?var:1}', ['var' => "\xC3\xA9clair"], '?var=%C3%A9'],
            'pct triplet counts as one character' => ['{var:1}', ['var' => '%2Fabc'], '%252F'],
            'reserved pct triplet counts as one character' => ['{+var:1}', ['var' => '%2Fabc'], '%2F'],
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

    public function testRejectsInvalidUtf8PrefixValues(): void
    {
        $this->assertInvalidTemplate('{var:1}', ['var' => "\xC3"]);
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
            'list' => ['{/x*}', ['x' => ['red', 'green']], '/red/green'],
            'map' => ['{?x*}', ['x' => ['a' => 'b']], '?a=b'],
            'nested exploded map extension' => ['{?x*}', ['x' => ['a' => ['b' => 'c']]], '?a%5Bb%5D=c'],
            'reserved key encoding collision keeps both pairs' => ['{+x*}', ['x' => ['a b' => '1', 'a%20b' => '2']], 'a%20b=1,a%20b=2'],
            'null list member skipped' => ['{?x*}', ['x' => ['a', null]], '?x=a'],
            'null map member skipped' => ['{?x*}', ['x' => ['a' => null, 'b' => 'c']], '?b=c'],
            'null member in simple list' => ['{x}', ['x' => ['red', null, 'blue']], 'red,blue'],
            'all null map members undefined' => ['X{.x}', ['x' => ['a' => null]], 'X'],
            'all null list members undefined' => ['{#x}', ['x' => [null]], ''],
            'null nested query leaf skipped' => ['{?x*}', ['x' => ['a' => ['b' => null, 'c' => 'v']]], '?a%5Bc%5D=v'],
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
            'invalid utf-8 scalar' => ['{x}', ['x' => "\xC3"]],
            'invalid utf-8 reserved scalar' => ['{+x}', ['x' => "\xC3"]],
            'invalid utf-8 list member' => ['{x}', ['x' => ['ok', "\xC3"]]],
            'invalid utf-8 map key' => ['{?x*}', ['x' => ["\xC3" => 'v']]],
            'invalid utf-8 nested value' => ['{?x*}', ['x' => ['a' => ['b' => "\xC3"]]]],
            'invalid utf-8 nested key' => ['{?x*}', ['x' => ['a' => ["\xC3" => 'v']]]],
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

    public function testIgnoresUnusedInvalidVariableShapes(): void
    {
        self::assertSame('ok', UriTemplate::expand('{x}', ['x' => 'ok', 'unused' => new \stdClass()]));
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
