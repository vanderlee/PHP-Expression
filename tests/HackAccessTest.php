<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Vanderlee\Expression\Exception;
use Vanderlee\Expression\Expression;

class HackAccessTest extends TestCase
{
    /**
     * @var bool
     */
    private static $booleanLiteralAliasCalled = false;

    /**
     * @var Expression
     */
    protected $object;

    public function testPhpFunction(): void
    {

        $this->expectException(Exception::class);
        $this->object->evaluate('print(1)');
    }

    public function testClassMethod(): void
    {

        $this->expectException(Exception::class);
        $this->object->evaluate('DateTime::getLastErrors()');
    }

    public function testClassConstant(): void
    {

        $this->expectException(Exception::class);
        $this->object->evaluate('DateTime::ISO8601');
    }

    public function testClassVariable(): void
    {

        $this->expectException(Exception::class);
        $this->object->evaluate('DateTime::$foo');
    }

    public function testObjectMethod(): void
    {
        /** @noinspection PhpUnusedLocalVariableInspection */
        $clazz = new DateTime;
        $this->expectException(Exception::class);
        $this->object->evaluate('$clazz->getTimestamp()');
    }

    public function testObjectVariable(): void
    {
        /** @noinspection PhpUnusedLocalVariableInspection */
        $clazz = new DateTime;
        $this->expectException(Exception::class);
        $this->object->evaluate('$clazz->foo');
    }

    public static function dataBooleanLiteralEvalEscapes(): array
    {
        return [
            ['true);phpinfo();//'],
            ['false);system(1);//'],
            ['true);eval(1);//'],
            ['false);include(1);//'],
            ['true || exit(1)'],
            ['false || die(1)'],
            ['false) or phpinfo() or (false'],
            ['TRUE);PHPINFO();//'],
        ];
    }

    /**
     * @dataProvider dataBooleanLiteralEvalEscapes
     */
    public function testBooleanLiteralsCannotEscapeEval(string $expression): void
    {
        $this->expectException(Exception::class);
        $this->object->evaluate($expression);
    }

    public function testBooleanLiteralsCannotBeOverriddenByFunctionAliases(): void
    {
        self::$booleanLiteralAliasCalled = false;
        $this->object->addFunction('true', 'HackAccessTest::recordBooleanLiteralAliasCall');
        $this->object->addFunction('false', 'HackAccessTest::recordBooleanLiteralAliasCall');

        foreach (['true()', 'false()'] as $expression) {
            try {
                $this->object->evaluate($expression);
                $this->fail(sprintf('Expression `%s` did not throw', $expression));
            } catch (Exception $exception) {
                $this->assertFalse(self::$booleanLiteralAliasCalled);
            }
        }
    }

    public static function recordBooleanLiteralAliasCall(): int
    {
        self::$booleanLiteralAliasCalled = true;

        return 1;
    }

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp(): void
    {
        $this->object = new Expression();
    }
}