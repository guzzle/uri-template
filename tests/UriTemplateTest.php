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
     */
    public function testExpandsValidVariableNames(string $template, array $variables, string $expansion): void
    {
        self::assertSame($expansion, UriTemplate::expand($template, $variables));
    }

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
        foreach (self::loadSpecFixture($filename) as $example) {
            $variables = $example['variables'];
            foreach ($example['testcases'] as $case) {
                yield [$case[0], (array) $case[1], $variables];
            }
        }
    }

    private function assertInvalidTemplate(string $template, array $variables = []): void
    {
        $this->expectException(\InvalidArgumentException::class);

        UriTemplate::expand($template, $variables);
    }

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

    private static function loadSpecFixture(string $filename): array
    {
        $contents = \file_get_contents(\sprintf('%s/../vendor/uri-template/tests/%s', __DIR__, $filename));
        self::assertIsString($contents);

        $decoded = \json_decode($contents, true);
        self::assertIsArray($decoded);

        return $decoded;
    }
}
