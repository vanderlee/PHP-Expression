# Expression contract

This contract describes the built-in `NativePhpBackend` and `BcMathBackend`.
Third-party backends are responsible for their own evaluation semantics.
Portable validation checks the shared grammar; it does not execute either
backend to predict a result.

## Native grammar backend

`NativeGrammarBackend` is an optional native-float backend. Construct an
`Expression` with it; no BCMath extension is required. The default backend
remains `NativePhpBackend`, and portable validation remains independently
optional. Decimal-string evaluation methods reject both native backends
before invoking callbacks.

The grammar backend shares tokenization and precedence parsing with the
decimal grammar, adding native bitwise `&`, `^`, `|`, `<<`, `>>` and `<>`.
It generates explicitly grouped PHP expressions from the complete syntax
tree. Literal tokens are canonicalized, function targets are selected and
validated from the trusted registry, and converted units are referenced through
a fixed local array. No unvalidated source fragments enter `eval()`. Syntax,
literal, and target validation finish before unit conversion callbacks run.

PHP numeric types, coercion, comparisons, logical short-circuiting, native
functions, eager source-order unit conversion, finite-result checks, output
capture, and wrapped exceptions are retained. Unit registry normalization is
the same as the legacy native backend. Callback code remains trusted host code.

This is a mathematical grammar, not a general PHP syntax compatibility layer:

- Increment/decrement is rejected; legacy `10--2u` remains signed-unit
  subtraction. Use spaces or parentheses for plain repeated signs.
- Chained comparisons require parentheses. Comments and NUL bytes are rejected.
- Explicit bases and underscore-separated numeric tokens are canonicalized
  independent of PHP version; fractional/base underscore forms may be accepted
  where the legacy normalization did not support them on older PHP versions.
- Unit amounts must be plain decimal literals. Suffix matching uses exact
  registered spelling. Number recognition takes precedence over suffixes that
  overlap numeric notation, so avoid suffixes such as `e2`.
- The source retains the common limits. Unit grouping counts toward native
  parenthesis depth; recursive parser nesting is additionally limited to 128.
  Flat operator chains are bounded by the input length and a tree-depth budget.

Parsing adds overhead and this backend does not cache compiled expressions.
Measure your workload with `php tools/benchmark-performance.php --backend=grammar`
and `--backend=native` using matching settings. `--backend=all` includes both.
The grammar backend is tested using the same bounded exhaustive native pipeline
matrices, independent rational arithmetic, hostile syntax/registry targets,
and repeated-expression memory checks.

## Inputs, results, and errors

Expressions contain numeric literals, registered unit amounts, parentheses,
operators, boolean literals, and registered function calls. Function aliases
and boolean keywords are case-insensitive. Variables, strings, arrays, PHP
statements, comments, and NUL bytes are rejected. Whitespace separates tokens:
`1 2` is invalid. Function arguments are comma-separated.

`evaluate()` returns a finite float on every backend. Converting a decimal
result to float can lose precision. `evaluateDecimal()` preserves the decimal
backend's string result and selected rounding policy. `evaluateExact()` uses
strict exact evaluation, even on a rounded `BcMathBackend`. Native evaluation
does not support either decimal-string method. Errors are reported as
`Vanderlee\Expression\Exception`; wrapped callback/backend errors retain their
cause.

Input length is limited to 4,096 bytes, parenthesis depth to 128, and function
calls to 128. Backend normalization and evaluation impose additional limits.
Decimal arithmetic has a 1,000-digit budget and integer exponents between
-1,000 and 1,000. Limits also apply to skipped branches during validation.
They do not limit the execution time or side effects of trusted custom code.

## Operators and precedence

From highest to lowest precedence in the shared grammar:

| Operators | Meaning and association |
| --- | --- |
| `**` | Power, right-associative |
| unary `+`, `-`, `!`, `not` | Sign or logical negation |
| `*`, `/`, `%` | Multiplication, division, remainder; left-associative |
| `+`, `-` | Addition/subtraction; left-associative |
| `<`, `<=`, `>`, `>=` | Numeric ordering; chaining requires parentheses |
| `==`, `!=`, `===`, `!==`, `<=>` | Equality/type equality/spaceship; chaining requires parentheses |
| `&&`, `and` | Logical AND; left-associative |
| `||`, `or` | Logical OR; left-associative |
| `xor`, `^^` | Logical XOR; left-associative |

Textual `and` and `or` are normalized to `&&` and `||`, so their precedence
differs from raw PHP's textual operators. `not` means `!`. For example,
`2**3**2` is `512`, `-2**2` is `-4`, and `1 xor 1 || 1` is `0`.
Parentheses determine grouping. `&&` and `||` short-circuit evaluation;
XOR evaluates both operands. Zero is false and nonzero is true.

Native bitwise operators inherit PHP semantics and are outside the decimal
grammar. Portable mode rejects them, `%`, `===`, and `!==`.

## Backend differences

| Feature | Native PHP | Decimal |
| --- | --- | --- |
| Arithmetic | PHP integers and binary floats; final result is float | Decimal strings; digit budget enforced |
| `/`, negative powers, `sqrt` | Approximate result where needed | Strict mode requires a finite exact decimal; rounded mode applies configured scale/policy |
| Comparisons/logical results | Intermediate PHP booleans | Numeric decimal `1`/`0` |
| `===`, `!==` | PHP type-sensitive comparisons | Numeric comparisons, like `==`/`!=` |
| `%` | PHP integer coercion | Requires integer operands |
| Functions | Registered callable targets | Decimal built-ins or trusted user-defined callbacks; other PHP internal functions rejected |
| Unit conversions | Numeric factors stored as floats; `Unit` callbacks eager during normalization | Numeric factors retained; `DecimalUnit` callbacks evaluated with their branch |
| Base literals | May lose precision when beyond integer range | Exact within digit budget |

Thus `0.1+0.2==0.3` returns native `0` but decimal `1`;
`(1<2)<3` returns native `0` but decimal `1`; `1.0===1` differs too.
Native final non-finite values are rejected, but intermediate non-finite
values can be inspected with its registered predicate functions.

Decimal `round(value, precision)` rounds halfway away from zero, independently
of the configured division/root policy. Native `round` uses PHP's default
half-up behavior when no third argument is supplied. Decimal rounding can
still differ when native inputs contain accumulated floating-point error.

## Units and registration

A suffix must immediately follow its literal amount: `2u`, not `2 u`.
Longest registered suffixes take priority. Unit suffix matching is not
uniformly case-insensitive between backends; use exact lowercase spelling
for portable expressions. Registries allow 128 suffixes of at most 64 bytes.

Converted amounts are grouped operands. A minus touching a unit amount at
operand position belongs to the conversion: `-2u` converts a signed amount,
while `- 2u` negates the conversion of positive `2`. `10-2u` subtracts the
conversion of positive `2`. `-2u**2` squares the converted signed amount.
These distinctions matter for nonlinear callbacks.

Removing/replacing registrations affects the next evaluation. Register only
trusted callback targets. Native custom-unit syntax preflight never invokes
callbacks; valid-source runtime failures can occur after callbacks run.
Callback side effects are not transactional.

## Shared implementation and backend capabilities

The two native backends extend `AbstractNativeBackend`, which owns native unit
normalization/conversion, numeric literal formatting, source guards, buffered
PHP execution, and finite-result checks. Each concrete backend owns its source
grammar and code-generation policy. Decimal arithmetic keeps its own numeric
and rounding implementation.

`GrammarParser` shares tokenization and precedence parsing between native
grammar, decimal evaluation, and portable validation. `UnitSuffixes` builds
escaped longest-first alternatives, `FunctionValidator` validates registered
targets, and `ResourceLimits` supplies common length/function-count checks.
The legacy native backend retains its suffix cache.

Backends may implement `FloatBackend` to declare float-only evaluation.
The facade rejects decimal-string requests on that capability before execution,
without checking concrete implementation classes. Existing third-party
implementations of `Backend`, `ExactBackend`, and `UnitNormalizer` remain valid.

## Opt-in portable validation

```php
use Vanderlee\Expression\Expression;
use Vanderlee\Expression\Backend\BcMathBackend;

$native = new Expression(null, true);
$decimal = new Expression(new BcMathBackend(), true);
$native->addUnit('cm', '0.01');
$decimal->addUnit('cm', '0.01');
$native->evaluate('abs(-2cm) + 3');
$decimal->evaluateExact('abs(-2cm) + 3');

// Existing instances can opt in or out for subsequent evaluations.
$native->setPortableValidation(true);
```

The default is disabled, preserving existing backend behavior. When enabled,
all three evaluation methods validate before calling the backend. Validation
requires no BCMath extension, invokes no registered code, and checks every
branch, including branches that would short-circuit.

The portable subset deliberately rejects some otherwise shared expressions:

- Decimal literals with optional scientific notation; no base prefixes,
  underscores, or leading zeros before another integer digit. `.5`, `0.5`,
  and `1e-3` are allowed; `010`, `00.5`, and `1_000` are not.
- Unit amounts use plain decimal literals and lowercase ASCII alphabetic
  suffixes with numeric factors. Scientific/base unit amounts and custom
  `Unit`/`DecimalUnit` objects are rejected when used.
- Arithmetic `+`, `-`, `*`, `/`, `**`; numeric comparisons `==`, `!=`, `<`,
  `<=`, `>`, `>=`, `<=>`; and the logical operators listed above.
  Adjacent `++`/`--` are rejected; use `2- -1` or `2-(-1)` instead of `2--1`.
- Comparison/logical results may be the final result or feed logical
  operators. They cannot feed arithmetic, another comparison, or a numeric
  function: `(1<2)&&true` is allowed; `(1<2)<3` and `abs(1<2)` are rejected.
  `true` and `false` literals themselves are numeric `1` and `0` on both
  backends and may be used as numeric operands.
- Function targets `abs`, `ceil`, `floor`, `sqrt`, `pow`, `round`, `min`,
  and `max`; registered aliases to these targets are allowed. Arguments must
  be numeric. Unary functions take one argument, `pow` two, `round` one or
  two, and `min`/`max` two through 128. Custom targets and `fmod` are rejected.
- Power exponents must be optionally signed integer literals within
  -1,000..1,000. Explicit rounding precision must be an optionally signed
  integer literal within -999..999. Computed exponents/precisions are rejected.

Portable means shared syntax and operand categories, **not identical answers
or guaranteed successful evaluation**. Precision-sensitive comparisons may
still differ. `1/3` and `sqrt(2)` validate but fail in strict decimal mode;
division by zero, overflow, digit limits, and domain errors remain runtime
policies. A third-party backend must independently implement the contract.
Call `Expression` methods to enforce this mode; direct backend calls bypass it.
