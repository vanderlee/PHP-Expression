<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Vanderlee\Expression\Exception;
use Vanderlee\Expression\Expression;

class NormalizationTest extends TestCase
{
    public function testRemoveFunctionNormalizesAliasCase(): void
    {
        $expression = new Expression();
        $expression->addFunction('Magnitude', 'abs');
        $this->assertSame(2.0, $expression->evaluate('magnitude(-2)'));

        $expression->removeFunction('MAGNITUDE');

        $this->expectException(Exception::class);
        $expression->evaluate('magnitude(-2)');
    }

    public function testUppercaseBasePrefixesAreAccepted(): void
    {
        $expression = new Expression();

        $this->assertSame(26.0, $expression->evaluate('0X10 + 0B10 + 0O10'));
    }
}
