# Business formula API

## Bind values separately from formulas

```php
use Vanderlee\Expression\Expression;

$expression = new Expression();
echo $expression->evaluate('price * quantity', ['price' => '19.95', 'quantity' => 3]);
```

The optional second argument is a map of numeric inputs. Names match
`[a-z_]\w*` case-insensitively. Names that differ only in case cannot both appear
in a binding map. Boolean literals and operator keywords remain reserved syntax.
Do not prefix names with `$`. A variable may share a function alias: `fee` is a
variable and `fee(2)` is a function call.

Native inputs accept finite integers, floats, and decimal/scientific numeric
strings. Decimal backends accept integers and decimal/scientific strings, rejecting
floats to avoid importing binary approximation. Values never become expression
source. Objects, arrays, booleans, and code snippets are rejected. All referenced
variables must be supplied, including those in unused branches; extra numeric
bindings are allowed and validated. Bindings apply to one evaluation only.

## Validate, inspect, and reuse

```php
$formula = $expression->compile('if(quantity >= 10, price * quantity * 0.9, price * quantity)');
$metadata = $formula->inspect();
// variables: [price, quantity]; functions: [if]; units: []
$first = $formula->evaluate(['price' => '20', 'quantity' => 10]);
$second = $formula->evaluate(['price' => '20', 'quantity' => 2]);

$expression->validate('price * quantity'); // throws on invalid syntax
$metadata = $expression->inspect('price * quantity');
```

Compilation parses once and retains a reusable syntax tree. It performs no
function calls or unit conversions, and requires no variable values. Inspection
returns sorted, unique `variables`, `functions` (aliases), and `units`, plus
`backend` (class name), `portable` (whether portable validation is enabled), and
`compatibility` checks for `native_grammar`, `decimal`, and `portable`. These
checks report syntax/function/unit support without executing callbacks. Decimal
compatibility is null if BCMath is unavailable. A true result does not promise
identical numerical behavior or freedom from runtime errors across backends.
Validation checks grammar, function argument counts, and known backend support;
it cannot predict value-dependent failures such as division by zero, callback
errors, or decimal precision limits for future inputs.

Compiled formulas remain attached to their originating evaluator and backend.
Adding, removing, clearing, or resetting functions/units, or changing portable
validation invalidates them. Recompile after such changes. Mutation inside a
registered callable or unit object is outside this configuration tracking.
Compiled objects are intended for in-process reuse, not persistent serialization.
Persist source and configuration instead. Native compiled evaluation still
generates PHP from the tree per execution; it does not cache machine code.

The default native backend keeps its legacy path for ordinary expressions.
Variables, instance callables, rounding helpers, and `if()` select the grammar
path. All compiled native expressions use that grammar, whose boundaries are
documented in [EXPRESSION_CONTRACT.md](EXPRESSION_CONTRACT.md). Third-party
backends must implement `Backend\TreeBackend` to support compilation and bindings;
their existing `Backend` interface is unchanged. `evaluateTree()` receives the
validated tree, registries, bindings, and an exact-mode flag. Such backends are
trusted to honor exactness and numeric policy.

## Lazy business conditions

`if(condition, whenTrue, whenFalse)` evaluates only the selected branch:

```php
$expression->evaluate('if(quantity > 0, total / quantity, 0)', [
    'quantity' => 0, 'total' => 20,
]); // 0
```

Both branches must be syntactically valid and use permitted functions. Conditions
use numeric truth: zero is false. `if` is a language construct and cannot be
replaced or removed through the function registry. Ternary syntax remains
unsupported. Native formulas containing `if()` defer unit conversion until its
operand is used; ordinary native formulas retain eager unit conversion.

## Explicit rounding

| Function | Behavior |
| --- | --- |
| `round_half_even(value, precision = 0)` | Halfway cases go to the nearest even retained digit. |
| `round_half_up(value, precision = 0)` | Halfway cases go away from zero. |
| `truncate(value, precision = 0)` | Discard digits toward zero. |
| `round_increment(value, increment)` | Round to a positive increment, halfway away from zero. |

Precision must be an integer from -999 through 999. Negative precision rounds
to tens, hundreds, etc. Native calculations remain approximate and extreme
precisions may exceed float range. Decimal calculations use BCMath strings.
`round_increment(1.23, 0.05)` returns `1.25`, including in strict decimal mode;
its quotient is rounded directly without requiring a finite decimal expansion.

These functions participate in the normal allowlist lifecycle. Removing one
disables it, and `resetFunctions()` restores it. Existing `round()` behavior is
unchanged. Explicit rounding does not change the division/root policy: in strict
decimal mode `round_half_even(1/3, 2)` still fails while evaluating `1/3`. Choose
a rounded BCMath backend when intermediate divisions need rounding.

## Instance-local business functions

```php
$fixedFee = 5;
$expression->addFunction('shipping', function ($weight) use ($fixedFee) {
    return $fixedFee + 2 * $weight;
});
echo $expression->evaluate('shipping(weight)', ['weight' => 3]);
```

Closures, callable objects, callable arrays, and the existing named functions
and static method strings are supported. Argument counts are checked when
compiling against the callable's reflected signature. Native callables receive
native numeric values; decimal callables receive canonical decimal strings and
must return a decimal string or integer. Avoid using PHP float arithmetic inside
a decimal callback; use BCMath or another exact implementation. Portable mode
continues to reject custom functions whose arithmetic semantics it cannot verify.

Callables and unit converters are trusted application code. They may have side
effects and must enforce their own runtime/resource policies. Compilation does
not sandbox them, and evaluation cannot undo their side effects.

## Errors suitable for an editor

```php
try {
    $expression->compile('price * (quantity + 1');
} catch (\Vanderlee\Expression\DiagnosticException $error) {
    $code = $error->getErrorCode(); // expected_token
    $offset = $error->getOffset(); // zero-based byte offset, or null
    $message = $error->getMessage();
}
```

`DiagnosticException` extends the existing library `Exception`, so existing
exception handlers still work. Codes include `empty_expression`,
`unsupported_syntax`, `unexpected_token`, `expected_operand`, `expected_token`,
`unknown_function`, `invalid_argument_count`, `unsupported_function`,
`missing_variable`, `invalid_variable`, `reserved_name`, `stale_configuration`,
`unsupported_backend`, `invalid_result`, `non_finite_result`, `resource_limit`,
`invalid_argument`, `validation_error`, and `evaluation_error`.

Token and missing-variable errors include source positions. Broader validation
and runtime errors may have no position and retain their cause through
`getPrevious()`. Registration and legacy facade result-policy errors may still
use the base `Exception`; applications should retain a fallback handler.

See [examples/business.php](../examples/business.php) for executable pricing,
measurement, billing, and threshold-rule recipes.
