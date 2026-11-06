<?php
declare(strict_types=1);
require dirname(__DIR__) . '/vendor/autoload.php';

use Vanderlee\Expression\Expression;
use Vanderlee\Expression\DiagnosticException;
use Vanderlee\Expression\Backend\BcMathBackend;

$calculator = new Expression();

// Numeric threshold rule: the host application chooses the resulting action.
$eligible = $calculator->compile('order_total >= minimum and packages <= capacity');
echo 'Order eligible: ' . $eligible->evaluate(['order_total' => 750, 'minimum' => 500, 'packages' => 12, 'capacity' => 24]) . PHP_EOL;

// Measurement input: all length units normalize to metres.
$calculator->addUnit('m', 1);
$calculator->addUnit('cm', 0.01);
echo 'Length in metres: ' . $calculator->evaluate('2m + 35cm') . PHP_EOL;

// Useful editor feedback without executing a formula.
try { $calculator->validate('length * (width + 1'); }
catch (DiagnosticException $error) {
    echo 'Validation: ' . $error->getErrorCode() . ' at byte ' . $error->getOffset() . PHP_EOL;
}

if (!extension_loaded('bcmath')) {
    echo 'Install ext-bcmath to run the decimal pricing and billing recipes.' . PHP_EOL;
    exit(0);
}

$money = new Expression(new BcMathBackend(8, BcMathBackend::HALF_EVEN));
$quote = $money->compile('round_half_even(max(minimum, price * quantity * (1 - discount)), 2)');
echo 'Quote: ' . $quote->evaluateDecimal(['minimum' => '75', 'price' => '42.50', 'quantity' => 6, 'discount' => '0.1']) . PHP_EOL;
echo 'Quote inputs: ' . implode(', ', $quote->inspect()['variables']) . PHP_EOL;

$bill = $money->compile('round_half_even(usage * rate + fixed_fee, 2)');
echo 'Usage bill: ' . $bill->evaluateExact(['usage' => '12.5', 'rate' => '0.125', 'fixed_fee' => '5']) . PHP_EOL;

// A decimal callback returns strings and uses decimal arithmetic itself.
$money->addFunction('handling', function ($packages) { return bcmul($packages, '1.25', 2); });
echo 'Handling: ' . $money->evaluateExact('handling(packages)', ['packages' => 3]) . PHP_EOL;
