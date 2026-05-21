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
     */
    public function testExpandsValidModifiers(string $template, array $variables, string $expansion): void
    {
        self::assertSame($expansion, UriTemplate::expand($template, $variables));
    }

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

    public static function prefixOnCompositeProvider(): array
    {
        return [
            'list simple' => ['{list:1}', ['list' => ['red', 'green']]],
            'map simple' => ['{keys:1}', ['keys' => ['semi' => ';']]],
            'reserved map' => ['{+keys:1}', ['keys' => ['semi' => ';']]],
            'matrix map' => ['{;keys:1}', ['keys' => ['semi' => ';']]],
        ];
    }

    /**
     * @dataProvider prefixOnCompositeProvider
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

    public static function supportedVariableShapeProvider(): array
    {
        return [
            'string' => ['{x}', ['x' => 'value'], 'value'],
            'int zero' => ['{x}', ['x' => 0], '0'],
            'float' => ['{x}', ['x' => 37.76], '37.76'],
            'false' => ['{x}', ['x' => false], ''],
            'true' => ['{x}', ['x' => true], '1'],
            'empty string query' => ['{?x}', ['x' => ''], '?x='],
            'top-level null skipped' => ['{?x,y}', ['x' => null, 'y' => 'yes'], '?y=yes'],
            'stringable object' => ['{x}', ['x' => new StringableValue('ok')], 'ok'],
            'stringable object in list' => ['{x}', ['x' => [new StringableValue('ok')]], 'ok'],
            'stringable object in map' => ['{?x*}', ['x' => ['a' => new StringableValue('ok')]], '?a=ok'],
            'list' => ['{/x*}', ['x' => ['red', 'green']], '/red/green'],
            'map' => ['{?x*}', ['x' => ['a' => 'b']], '?a=b'],
            'nested exploded map extension' => ['{?x*}', ['x' => ['a' => ['b' => 'c']]], '?a%5Bb%5D=c'],
        ];
    }

    /**
     * @dataProvider supportedVariableShapeProvider
     */
    public function testExpandsSupportedVariableShapes(string $template, array $variables, string $expansion): void
    {
        self::assertSame($expansion, UriTemplate::expand($template, $variables));
    }

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
            'nested null in list' => ['{?x*}', ['x' => ['a', null]]],
            'nested null in map' => ['{?x*}', ['x' => ['a' => null]]],
            'nested object in query extension' => ['{?x*}', ['x' => ['a' => ['b' => new \stdClass()]]]],
        ];
    }

    /**
     * @dataProvider invalidVariableShapeProvider
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

final class StringableValue
{
    /** @var string */
    private $value;

    public function __construct(string $value)
    {
        $this->value = $value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
