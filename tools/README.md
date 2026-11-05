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
The preflight compiles an unreachable return instead of creating a closure;
repeated checks do not retain closure compilation data. A sustained regression
test measures retained memory across 10,000 distinct custom-unit expressions.
Runtime failures in otherwise valid expressions can occur after trusted
callbacks have run; callback side effects are not transactional or sandboxed.

## Reproducible benchmarks

```text
php tools/benchmark-performance.php --list
php tools/benchmark-performance.php --iterations=5000 --runs=7 --output=tools/results/before.json
php tools/benchmark-performance.php --iterations=5000 --runs=7 --baseline=tools/results/before.json --output=tools/results/after.json
php tools/benchmark-performance.php unit_suffix_regex --backend=native
php tools/benchmark-performance.php --backend=grammar
php tools/benchmark-performance.php --backend=rounded --json
```

The unit fixture registers 120 suffixes and references only registered suffixes.
Cases include scalar/base arithmetic, booleans, decimal formulas, nested
functions, long expressions, custom units, and rounded division/roots. Cases
unsupported by a selected arithmetic policy are explicitly skipped; selecting
only unsupported combinations fails. Results are verified against known
answers before timing and after every sample.

Timing measures repeated full public API evaluation, including parsing and
normalization; there is no compiled-expression reuse API. Registry construction
is excluded. Each case has configurable warmup, iteration count, and sample
count. Reports retain samples, median/min/max, evaluations per second, and
nanoseconds per evaluation. `hrtime()` is used where available, with a
`microtime()` fallback on older PHP.

JSON reports capture PHP version, OS, architecture, timer, extensions, relevant
INI settings, and parameters. Comparisons require matching case names and
expression text and flag differing environments. A positive change percentage
means slower. Use the same machine, PHP configuration, warmup, iteration count,
and sample count, with an otherwise idle system. Reports do not establish
cross-machine or universal performance claims.

Memory measurements include per-run setup. On PHP with
`memory_reset_peak_usage()`, per-case peak/delta are reported after warmup;
otherwise the peak is process-wide and the case delta is unavailable. Cases
share a process, so autoloaded code and runtime caches can persist. Memory
numbers describe this harness, not isolated request memory consumption.

Tool source is versionable; generated JSON under `tools/results/` is ignored.
