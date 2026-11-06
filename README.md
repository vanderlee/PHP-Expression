PHP-Expression
==============

Simple and fast PHP expression parser, based on secure use of eval().

For application integration, see the [business formula API](docs/BUSINESS_FORMULAS.md)
and [runnable recipes](examples/business.php). Named numeric bindings, reusable
parsed formulas, side-effect-free validation, inspection, structured diagnostics,
lazy `if()` conditions, explicit rounding helpers, and instance-local callables
are available:

```php
$expression = new \Vanderlee\Expression\Expression();
$formula = $expression->compile('if(quantity >= 10, price * quantity * 0.9, price * quantity)');
echo $formula->evaluate(['price' => '20', 'quantity' => 10]); // 180
```

See the [expression contract](docs/EXPRESSION_CONTRACT.md) for operator
precedence, backend differences, unit semantics, and the optional shared
grammar. Enable portable validation with `new Expression(null, true)` or
`$expression->setPortableValidation(true)`. It rejects incompatible syntax
before evaluation; numerical precision and runtime errors remain backend policies.

For grammar-validated native execution, select the optional backend:

```php
$expression = new \Vanderlee\Expression\Expression(
    new \Vanderlee\Expression\Backend\NativeGrammarBackend()
);
```

It parses the whole expression before callbacks run and generates PHP from
the syntax tree. It retains native arithmetic and uses `eval()` for generated
code only. The default remains `NativePhpBackend`. See the
[grammar backend contract](docs/EXPRESSION_CONTRACT.md#native-grammar-backend)
for compatibility boundaries and benchmark instructions.

Using eval() is evil()
----------------------
This class tries to expose a subset of PHP functionality not by removing the
bad features, but by explicitly allowing only the good features.

IF YOU FIND ANY (POTENTIAL) SECURITY ISSUE, PLEASE REPORT!

Since PHP-Expression uses `eval()`, it can also handle PHP syntax, including
parenthesis, arithmetic, functions and more.

Things you are allowed to do
----------------------------
-	Use numbers, either floating-point or integer, including negative.
-	Use a number of different bases, such as decimal, octal, hexadecimal.
-	Use most basic math functions such as `abs()`, `min()`, `max()` and `sqrt()`.
-	Use basic arithmetic operators `+`, `-`, `*`, `/` and `%`.
-	Use parenthesis.
-	Use comparisons (results return `1`/`0` decimal).
-	Use `true` and `false` boolean literals (returned as `1` and `0`).
-	Use boolean operators in comparisons.
-	Use bitwise operators.
-   Use textual boolean operators like `and`, `xor` and `or`

Things we've added
------------------
-	Binary numbers, using a `0b` prefix. i.e. `0b1001110`.
-	New functions/function aliases.
-	Support to use number "types" using prefixes.
-   Boolean operator `^^` for logical XOR (same as `xor`).
-   Textual boolean operator `not` (same as `!`).

Things you should NOT be able to do
-----------------------------------
-	Access functions not explicitly permitted.
-	Access static classs method not explicitly permitted.
-	Access class properties or functions.
-	Access system constants.
-	Access host PHP variables, local or global. Explicit numeric expression
    bindings are supported without exposing PHP scope.
-	Access Expression class itself.
-   Access namespace functions. 
-	Access arrays of any kind, using either brackets or accolades.
-	Use strings.
-	Return anything except numbers (integer or floating-point, 1/0 for boolean results).

Things you cannnot do
---------------------
-   Ternary operator (`x ? y : z`) not supported.
-   Elvis operator (`x ?: z`) not supported.
-   Null-coalescing operator (`x ?? z`) not supported.
-   Spaceship operator (`x <=> y`) only supported on PHP 7 and up (it's a PHP 7 feature).

Available functions
-------------------
The native backend enables arithmetic and rounding functions (`abs`, `ceil`,
`floor`, `fmod`, `min`, `max`, `pow`, `round`, `sqrt`), trigonometric and hyperbolic
functions, logarithms, exponentials, angle conversions, `hypot`, `pi`, and
finite-value inspection. It also enables `round_half_even`, `round_half_up`,
`truncate`, and `round_increment`. The decimal backend supports the arithmetic
subset described below and those four rounding helpers.

`if(condition, whenTrue, whenFalse)` is a lazy language construct. Use
`clearFunctions()`, `removeFunction()`, and `addFunction()` to configure the
function allowlist. Random functions require explicit opt-in. Full integration
details are in the [business formula API](docs/BUSINESS_FORMULAS.md).

Arithmetic normalization
------------------------
Whitespace separates tokens: malformed expressions such as `1 2` and
`1 . 2` are rejected rather than joined into a number. Unit suffixes must
immediately follow their numeric amount and cannot be followed by another
identifier, number, or decimal point.

Converted unit amounts are grouped as individual operands. A binary minus
remains subtraction: `10-2dB` means `10 - convert(2)`, while `10+(-2dB)`
means `10 + convert(-2)`. A negative amount immediately followed by its
suffix is converted as a signed amount. These differ for nonlinear units.
Unit replacement preserves float precision and supports scientific notation.

Numeric precision
-----------------
`evaluate()` uses native PHP arithmetic and returns a finite float. Decimal
fractions are generally approximate: `0.1 + 0.2 == 0.3` evaluates to `0`.
For approximate comparisons, choose a tolerance appropriate to your inputs,
for example `abs((0.1 + 0.2) - 0.3) < 1e-12`. Large or very small values may
need a relative tolerance instead. `round(0.1 + 0.2, 2)` provides explicit
rounding; the evaluator does not automatically round intermediate results.

Integer operations retain PHP integer precision while their operands and
results fit the platform integer range. The final conversion to float can
lose integer precision beyond 2^53 on typical binary64 PHP builds. Overflow
can promote intermediate integers to floats. Underflow can produce zero.

Normalized base literals and converted unit values retain their existing
numeric precision independently of PHP's `precision`, `serialize_precision`,
and decimal separator settings. Unit factors and amounts still use floats;
this preserves their represented value rather than making it exact decimal
arithmetic. Exact decimal evaluation is available through the optional backend
below.

Native PHP backend
------------------
`new Expression()` selects `Vanderlee\Expression\Backend\NativePhpBackend`.
It owns the existing normalization and allowlist checks and executes with
`eval()`, returning a finite float. It can also be selected explicitly:

```php
$expression = new Expression(new Vanderlee\Expression\Backend\NativePhpBackend());
```

`Expression` manages registries, shared input limits, backend dispatch, and
result validation. `DecimalParser` is used only by `BcMathBackend`; it is not
part of the native path. `evaluateExact()` rejects the native backend.

Decimal backend: exact or explicitly rounded
--------------------------------------------
The default constructor retains the fast native PHP/eval path. Enable the
optional `ext-bcmath` PHP extension and select `BcMathBackend` for decimal
arithmetic without binary floating-point conversions:

```php
use Vanderlee\Expression\Expression;
use Vanderlee\Expression\Backend\BcMathBackend;

$expression = new Expression(new BcMathBackend());
echo $expression->evaluateExact('0.1 + 0.2');         // "0.3"
echo $expression->evaluateExact('0.1 + 0.2 == 0.3'); // "1"
echo $expression->evaluateExact('9007199254740993 + 1'); // "9007199254740994"

$expression->addUnit('u', '0.12345678901234567890123456789');
echo $expression->evaluateExact('2u'); // "0.24691357802469135780246913578"
```

`evaluateExact()` returns a canonical decimal string, with trailing fractional
zeros removed. Use it to preserve precision. `evaluate()` remains available
and evaluates through the selected backend, but casts the final result to a
finite float. A backend returning floats cannot be used with `evaluateExact()`.

The decimal grammar supports parentheses, decimal/scientific and base literals,
digit separators, `+`, `-`, `*`, `/`, integer `%`, integer powers `**`, numeric
comparisons, boolean literals, and boolean operators `!`, `&&`, `||`, `^^`,
`not`, `and`, `or`, and `xor`. Powers associate right-to-left; `^` is not a
decimal power operator. Comparisons return "1" or "0"; `<=>` returns "-1",
"0", or "1". All decimal values share one numeric type, so `===` and `==`
both compare numeric values. Boolean operations short-circuit where applicable.
Textual `and`/`or` use the same precedence as `&&`/`||`, as on the default path.
Whitespace separates tokens and unit suffixes are matched case-sensitively.

Supported built-in functions are `abs`, `min`, `max`, `ceil`, `floor`, `round`,
`pow`, `sqrt`, and `fmod`. `round(value, precision)` defaults to zero decimal
places and rounds halfway cases away from zero; it accepts no rounding-mode
argument. `%` requires integers; `fmod()` supports fractional operands.
The function allowlist, aliases, removal, clearing, and resetting still apply.
Functions such as `sin`, `log`, and `pi`, bitwise operators, and other PHP
syntax are unsupported and raise the library exception when used.

Exactness is strict by default: `1/8` returns "0.125", but `1/3` and `sqrt(2)` throw because
they have no finite decimal representation. There is no implicit truncation or
fallback to floats. Even `round(1/3, 2)` throws because its argument cannot be
evaluated exactly. Values/intermediate results are limited to 1000 decimal
digits (including fractional leading zeros); decimal parsing/evaluation depth
is limited to 128. Integer exponents are limited to -1000 through 1000, with
conservative digit-budget checks before exponentiation. The existing expression
length, parenthesis, function-call, and unit-registry limits also apply.

For general decimal calculations, opt into a scale for division and square
roots and use `evaluateDecimal()`:

```php
$expression = new Expression(new BcMathBackend(20, BcMathBackend::HALF_EVEN));
echo $expression->evaluateDecimal('1/3');         // "0.33333333333333333333"
echo $expression->evaluateDecimal('sqrt(2)');     // "1.4142135623730950488"
echo $expression->evaluateDecimal('round(1/3, 2)'); // "0.33"
echo $expression->evaluateExact('1/8');           // "0.125", bypasses rounding
// $expression->evaluateExact('1/3') still throws.
```

The constructor accepts `BcMathBackend(?int $scale = null, string $rounding =
BcMathBackend::HALF_UP)`. `null` retains strict evaluation. A scale of 0 through
999 specifies decimal places, not significant digits. Supported policies are
`HALF_UP` (halfway cases away from zero), `HALF_EVEN` (halfway cases to the
nearest even last retained digit), and `TOWARD_ZERO` (explicit truncation).
One guard digit plus an exact remainder/square check distinguishes genuine
ties from values above halfway; rounding is performed with BCMath strings.

The policy applies to every division and square root, including terminating
results and reciprocals of negative integer powers. Addition, subtraction,
multiplication, literals, and custom conversion results retain their decimal
digits; this is not a global fixed-scale context. Comparisons see the rounded
intermediate values. For example, at scale 2, `(1/3)*3` yields "0.99" and
`1/8` rounds to "0.13" with `HALF_UP`. Trailing zeros are still removed.
`round(value, precision)` retains its explicitly requested precision and its
half-away-from-zero behavior independently of the backend policy.

`evaluateDecimal()` returns a decimal string using the selected policy;
`evaluate()` uses that policy and casts the final value to float.
`evaluateExact()` bypasses the optional policy and still rejects non-finite
decimal expansions. No rounded result is presented as exact. Division by
zero, negative square roots, digit limits, unsupported functions, and
fractional exponents remain errors in both modes.

Use integer or decimal-string unit factors in this mode. Floats and existing
float-based `Unit` objects are rejected during decimal evaluation. For custom
conversions implement `Vanderlee\Expression\Unit\DecimalUnit`, whose
`convert(string $amount): string` receives the signed amount without a float
conversion. Registered custom functions receive decimal strings and must
return decimal strings or integers; float, boolean, and object results are
rejected. Custom functions/conversions remain trusted host code and must
preserve exactness themselves.

Other backends can implement `Vanderlee\Expression\Backend\Backend` and be
passed to the constructor. Its `evaluate(string $expression, array $functions,
array $units)` receives the original expression, the trusted callable registry,
and registered unit factors. It must parse/validate the expression, enforce its
arithmetic/resource policy, and return a finite float or an ordinary
decimal string (no exponent notation). The interface documents this return
contract without a union type for PHP 7.1 compatibility. `evaluateDecimal()`
accepts only decimal-string results. Backends with optional rounding should
implement `Backend\ExactBackend::evaluateExact()` to provide a separate strict
path; `Expression::evaluateExact()` dispatches to it. Custom backends without
this interface must ensure their string results are exact when used with
`evaluateExact()`. Backends may also implement
`Backend\UnitNormalizer::normalizeUnit($unit)` to validate and normalize unit
factors before registration; a failed registration preserves the existing unit.
The facade enforces input resource limits,
validates the returned representation, and wraps backend errors in the library
exception. Backends are trusted application code; they are not a sandbox.

Security policy
---------------
`evaluate()` returns a finite float or throws `Vanderlee\Expression\Exception`.
Results such as `sqrt(-1)`, `log(0)`, and overflowing powers are rejected.
Intermediate non-finite values can still be inspected with `is_nan()`,
`is_infinite()`, and `is_finite()` if the final result is finite.

Unit conversion factors must be finite numbers (including numeric strings),
or objects implementing `Unit` (or `DecimalUnit` on a pluggable backend).
Numeric factors are stored as floats on `NativePhpBackend`; decimal and custom
backends retain original values unless they provide a unit normalizer.
Each evaluator accepts at most 128 distinct unit suffixes, each at most 64
bytes long. Existing units may be replaced at the limit; removing or clearing
units frees capacity. Unit conversions must also return finite values;
conversion errors are wrapped in the library exception with their cause retained.

`rand`, `mt_rand`, `lcg_value`, and `mt_srand` are disabled by default.
Applications can explicitly enable one with, for example,
`$expression->addFunction('mt_rand')`. Random functions produce non-deterministic
results; `mt_srand` changes process-global PRNG state. `resetFunctions()` removes
these opt-ins. Register only trusted function targets and unit objects: their
code runs in the host process, and expression limits do not bound their runtime
or side effects.

These policies change previous behavior: callers that relied on non-finite
results, default random functions, or oversized unit registries must adapt.

Disclaimers
-----------
Validation and reproducible benchmark instructions are in
[tools/README.md](tools/README.md). Compatibility tests explicitly distinguish
shared behavior from intentional backend differences. The native backend
retains PHP booleans in intermediate operations; decimal comparisons use
numeric "1"/"0" values, so expressions such as `(1<2)<3` differ.
PHP comments are rejected. A negative sign must touch a numeric unit amount
to be part of the conversion: `-2u` converts a signed amount, while `- 2u`
negates the converted positive amount on both backends.

Though this class has been tested and independently reviewed by several people,
I cannot make any absolute 100% guarantee that it cannot be hacked. If you find
any potential security problem, please let us know.

Even though the Expression class itself is supposed to be secure, the return
value may still be abused if your code does not check for validity. Only numbers
can be returned, but if your code crashes on a number "666", the Expression
class cannot and will not be able to protect you. Check for valid ranges.
