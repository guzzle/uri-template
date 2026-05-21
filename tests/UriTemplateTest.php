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
        ];

        return \array_map(static function ($t) use ($variables) {
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
     */
    public function testExpandsUriTemplates(string $template, string $expansion, array $variables): void
    {
        self::assertSame($expansion, UriTemplate::expand($template, $variables));
    }

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
     */
    public function testReservedExpansionPreservesPctTriplets(string $template, array $variables, string $expansion): void
    {
        self::assertSame($expansion, UriTemplate::expand($template, $variables));
    }

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
     */
    public function testParsesExpressions(string $exp, array $data): void
    {
        $template = new UriTemplate();

        $class = new \ReflectionClass($template);

        $method = $class->getMethod('parseExpression');

        if (PHP_VERSION_ID < 80100) {
            $method->setAccessible(true);
        }

        $exp = \substr($exp, 1, -1);

        self::assertSame($data, $method->invokeArgs($template, [$exp]));
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
     */
    public function testSpecCompliance(string $template, array $expansions, array $variables): void
    {
        self::assertContains(UriTemplate::expand($template, $variables), $expansions);
    }

    private static function parseSpecExamples(string $filename): \Generator
    {
        $examples = \file_get_contents(\sprintf('%s/../vendor/uri-template/tests/%s', __DIR__, $filename));

        foreach (\json_decode($examples, true) as $example) {
            $variables = $example['variables'];
            foreach ($example['testcases'] as $case) {
                yield [$case[0], (array) $case[1], $variables];
            }
        }
    }
}
