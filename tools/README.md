# Validation and performance

Run the complete suite with `php vendor/bin/phpunit` or `composer test`.
BCMath-specific tests skip if the optional extension is absent; native generated
arithmetic tests still run. CI configuration is outside this validation change.

`BusinessFormulaTest` covers bindings, reusable formulas, inspection and backend
compatibility, validation without callbacks, lazy branches and unit conversion,
rounding policies, callable isolation, diagnostics, and configuration revocation.
Run `php examples/business.php` for the executable integration recipes.

## Native pipeline matrices

Run `php vendor/bin/phpunit --filter NativePipelineTest` for the bounded
exhaustive native matrices. They cover:

- Every integer 0..255 in six explicit-base spellings and four expression
  contexts; decimal underscore normalization for integers 1000..1255.
- All ordered pairs of 22 operators, three grouping patterns, two whitespace
  forms, and plain/numeric-unit/custom-unit operands. A fixed, trusted PHP
  expression provides the reference for native precedence, types, and syntax
  rejection; the separate rational oracle provides independent arithmetic
  checks without eval.
- Signed amounts -3..3 paired with each other, four arithmetic operators,
  eight suffixes including regex metacharacters, and positive/negative/nonlinear
  conversions. Division by zero is checked separately.
- Every disallowed byte among 0..255 inserted at every position in a source
  containing both function and custom-unit callbacks; rejection must precede
  either callback.
- Boolean truth tables across keyword case and whitespace spellings, signed
  nonlinear conversion spacing, every registration order for three overlapping
  suffixes, cache invalidation, function/number/keyword boundaries, bitwise and
  division/remainder domains, resource thresholds, floating-point bit patterns
  under nine INI precision combinations, and output-buffer preservation across
  success, syntax failure, callback output/errors, and non-finite results.

Failures print the expression or mutation byte/offset. The matrices are
exhaustive over these specified finite domains, not all possible expressions
or PHP runtime environments. Global INI and output-buffer state is restored.

`NativeGrammarPipelineTest` reuses these matrices against `NativeGrammarBackend`.
The generated rational checks also exercise that backend. Additional dedicated
tests check generated-code boundaries, trusted target validation, portable mode,
registry isolation, decimal-method rejection, and retained memory.

## Backend compatibility

`tests/BackendCompatibilityTest.php` checks shared arithmetic, precedence,
functions, signed/nonlinear units, registry lifecycles, errors, and short
circuiting. Expected results are explicit, rather than merely asserting that
both backends agree. It also records intentional differences: float precision,
intermediate PHP booleans, numeric type comparisons, modulo coercion, native
functions/bitwise operations, scientific unit literals, and large base literals.

`tests/PortableValidationTest.php` checks the opt-in shared grammar on native,
strict decimal, and rounded decimal configurations, rejection before custom
code/backend execution, and toggling the mode. See the
[expression contract](../docs/EXPRESSION_CONTRACT.md) for its precise scope.

## Independent generated checks

`tests/GeneratedArithmeticTest.php` generates bounded expression trees from
seeds 37, 9973, and 24117. `tests/Support/ArithmeticOracle.php` evaluates those
trees with integer numerator/denominator arithmetic and renders expected
decimals through integer long division. It uses neither eval, BCMath, nor the
package parser. Separate unparenthesized formulas test precedence. Division
rounding is checked against integer quotients/remainders; square-root rounding
is checked against exact squares just below, at, and above rounding boundaries.

Failures include the seed, case, expression, and relevant rounding policy.
To reproduce or extend a run, set `EXPRESSION_TEST_SEED` to an integer 0..32748:

```powershell
$env:EXPRESSION_TEST_SEED = '1234'
php vendor/bin/phpunit --filter 'GeneratedArithmeticTest|SecurityBoundaryTest'
Remove-Item Env:EXPRESSION_TEST_SEED
```

The generator owns its PRNG state and does not seed PHP's global random
functions. Oracle operands are bounded to keep integer calculations within
32-bit range. This is bounded property testing, not exhaustive proof.

## Security boundary

`tests/SecurityBoundaryTest.php` exercises native, strict decimal, and rounded
decimal configurations. It checks forbidden PHP constructs, malformed source,
resource limits, deterministic forbidden-token mutations, registry revocation,
registration bounds, short circuiting, retained exception causes, and host
output-buffer preservation. Function and unit spies establish that invalid
source is rejected before callbacks execute.

Native custom-unit evaluation performs a syntax/allowlist preflight with dummy
numeric operands before host conversion code runs. Numeric factors retain the
ordinary path. PHP comments are rejected as outside the mathematical grammar.
The preflight compiles an unreachable return instead of creating a closure.
Each backend retains only the last successfully checked generated PHP string,
skipping syntax compilation when that text is unchanged. Allowlist preparation
still checks current function targets before consulting this syntax-only cache;
unit conversions and function execution remain live. Failed syntax checks are
never cached, and the cache retains no host objects. Repeated checks do not
retain closure compilation data. A sustained regression
test measures retained memory across 10,000 distinct custom-unit expressions.
Runtime failures in otherwise valid expressions can occur after trusted
callbacks have run; callback side effects are not transactional or sandboxed.

## Reproducible benchmarks

```text
php tools/benchmark-performance.php --list
php tools/benchmark-performance.php --iterations=5000 --runs=7 --output=tools/results/before.json
php tools/benchmark-performance.php --iterations=5000 --runs=7 --baseline=tools/results/before.json --output=tools/results/after.json
php tools/benchmark-performance.php unit_suffix_regex --backend=native
php tools/benchmark-performance.php --backend=native --source-mode=alternate
php tools/benchmark-performance.php --backend=native --execution=compiled
php tools/benchmark-performance.php switching_bound_formulas --backend=native --source-mode=alternate
php tools/benchmark-performance.php switching_bound_formulas --backend=native --source-mode=alternate --execution=compiled
php tools/benchmark-performance.php switching_lazy_formulas --backend=native --source-mode=alternate --execution=compiled
php tools/benchmark-performance.php --backend=grammar
php tools/benchmark-performance.php --backend=rounded --json
```

The unit fixture registers 120 suffixes and references only registered suffixes.
Cases include scalar/base arithmetic, booleans, decimal formulas, named bindings,
lazy conditions, nested functions, long expressions, custom units, and rounded division/roots. Cases
unsupported by a selected arithmetic policy are explicitly skipped; selecting
only unsupported combinations fails. Results are verified against known
answers before timing and after every sample.

The default `--execution=evaluate` measures full public API evaluation, including
any parsing and normalization required for each call. `--execution=compiled`
compiles each source outside the timed loop and measures reusable
`CompiledExpression` evaluation, including binding validation and callbacks.
Reports with different execution modes or bindings are not compared.
The default `repeat` source mode measures repeated source
on one evaluator, including internal cache hits. `alternate` switches between
two sources on every call. Most cases use an equivalent parenthesized source;
`switching_bound_formulas` and `switching_lazy_formulas` use different formulas
with independently checked results. Alternation misses single-entry caches but
fits within the eight-entry native compiled-plan cache. Compiled execution retains
one formula object per distinct source outside the timed loop and reuses those
objects while switching. Both variants are checked before timing and after each sample;
reports with different source modes are not compared. Registry construction
is excluded. Each case has configurable warmup, iteration count, and sample
count. Reports retain samples, median/min/max, evaluations per second, and
nanoseconds per evaluation. `hrtime()` is used where available, with a
`microtime()` fallback on older PHP.

JSON reports capture PHP version, OS, architecture, timer, extensions, relevant
INI settings, and parameters. Comparisons require matching case names and
both source texts and flag differing environments. A positive change percentage
means slower. Use the same machine, PHP configuration, warmup, iteration count,
and sample count, with an otherwise idle system. Reports do not establish
cross-machine or universal performance claims.

Memory measurements include per-run setup. On PHP with
`memory_reset_peak_usage()`, per-case peak/delta are reported after warmup;
otherwise the peak is process-wide and the case delta is unavailable. Cases
share a process, so autoloaded code and runtime caches can persist. Memory
numbers describe this harness, not isolated request memory consumption.
Compiled-mode memory measurements also include construction of the reusable formulas.

Tool source is versionable; generated JSON under `tools/results/` is ignored.

## Switching among native formulas

Native compilation retains at most eight parsed/generated plans per evaluator,
evicting the least recently used plan. Function, unit, and validation configuration
changes clear every entry. Plans retain neither results nor evaluator references;
bindings and callback effects are evaluated on every call. `compile()` continues
to return separate formula objects, and saved objects remain usable after cache
eviction. Configuration changes still make saved objects stale.

For a known set of formulas, compile once and retain the objects explicitly:

```php
$expression = new \Vanderlee\Expression\Expression();
$formulas = [
    'subtotal' => $expression->compile('price * quantity'),
    'discounted' => $expression->compile('price * quantity * 0.9'),
];
$subtotal = $formulas['subtotal']->evaluate(['price' => '20', 'quantity' => 3]);
$discounted = $formulas['discounted']->evaluate(['price' => '20', 'quantity' => 3]);
$nextSubtotal = $formulas['subtotal']->evaluate(['price' => '25', 'quantity' => 2]);
```

The cache applies to native compiled plans, including convenience evaluations
with bindings or lazy conditions. The classic native normalization/execution
cache still retains one source per execution/preflight slot. Decimal and custom
backend caching policies are unchanged.

On the Windows x64 PHP 8.2.10 environment described below, a local comparison
with 5,000 evaluations per sample, seven samples, and 200 warmup calls reduced
the alternating `bound_formula` median from 129.2 to 8.2 microseconds per
evaluation (93.6%). The new distinct-source switching cases measured 6.4 versus
5.5 microseconds for bound formulas and 9.4 versus 8.0 microseconds for lazy
formulas, comparing convenience evaluation with retained compiled objects.
These measurements exclude compilation in compiled mode and are local timings,
not performance guarantees.

## Native performance measurements (2026-11-06)

On Windows, 64-bit PHP 8.2.10, 5,000 evaluations per sample, seven samples,
and 200 warmup calls, the native changes reduced median evaluation time by
43 to 95% across twelve repeated-source cases. Alternating-source measurements
ranged from effectively unchanged to 32% faster; changes near 1% are within
ordinary timing noise. These are local measurements against commit `9ddb849`
using the same harness and PHP settings, not a cross-version performance guarantee.

The changes move the resource-limit byte scan into PCRE, retain one routing
decision per evaluator revision, and retain one prepared native source for each
of execution and custom-unit preflight. Source and referenced function targets
must match before prepared text is reused. Scalar unit registries retain their
full suffix pattern, avoiding repeated registry scans without retaining host
unit objects. Unit conversions, allowlist preparation, PHP
execution, output checks, and finite-result checks still run for each evaluation.
Syntax preflight compilation now reuses the last successfully checked PHP text.
Results and callback effects are never cached. Cache sizes are bounded rather
than growing with the number of distinct input expressions.

The benchmark now also covers named bindings, lazy formulas, and reusable
compiled formulas. In a separate comparison using 3,000 evaluations per sample
and five samples, preparing native code and required bindings once per compiled
formula reduced medians by 39 to 70% across twelve compiled-mode cases. The
prepared callable retains generated source and variable names/offsets, while
the owner continues to supply the current functions, units, and values. Stale
configuration checks run before execution. Custom backend execution remains
delegated to that backend.

Repeated convenience evaluations with bindings or lazy conditions also reuse
the last native parse and prepared plan. Configuration changes immediately
clear that cache, and `compile()` still returns separate formula objects. This
reduced the bound-formula case from 112 to 7.7 microseconds and the lazy-formula
case from 179 to 8.7 microseconds per evaluation in the repeated-source run.
The cache stores parse data rather than a `CompiledExpression`, avoiding an
owner reference cycle. Tests exercise registry changes, stale formulas, live
callbacks and units, diagnostics, custom backend policies, host-callable release,
and retained memory across distinct compiled formulas.

Other measured candidates were combined keyword normalization, fewer generated
parentheses, and locale-independent numeric formatting on newer PHP. They did
not show a sufficiently large, reliable improvement and were left unchanged.
