# Business use cases

PHP-Expression is a good fit when a PHP application needs configurable numeric
calculations without introducing a separate calculation service or a full
scripting language. Its business value is allowing formulas to change without
changing application code, and allowing users to enter calculations directly.

These cases describe capabilities available in version 1.1.0. Comparisons are
against alternative approaches, not benchmarks or claims of superiority over
specific products. See [README.md](README.md) for the API and backend policies.

## 1. Configurable sales quotes

**Business need:** Sales operations need to adjust markups, discounts, minimum
charges, and quantity calculations without waiting for a software release.

**Example:** `max(75, round(42.50 * 6 * 0.9, 2))` applies a discount and a minimum
quote amount after the application supplies the numeric inputs.

**Why choose this library:**

- Compared with hardcoded calculations, storing an approved expression makes
  formula changes a configuration task.
- Compared with a spreadsheet workflow, evaluation can happen inside the quote
  application, alongside its existing product data and approval process.
- Compared with unrestricted PHP snippets, the expression language exposes a
  limited set of operators and explicitly permitted functions.
- The BCMath backend supports decimal calculations without binary floating-point
  conversion when results are retained as decimal strings.

**Boundary:** The application must bind numeric inputs separately from the formula,
manage formula versions and permissions, and validate business ranges.
A pricing system with product relationships,
promotion priorities, and exceptions needs additional business logic.

## 2. Material and production estimates

**Business need:** An estimator needs to calculate purchase quantities, waste
allowances, package counts, or batch costs for a customer order.

**Example:** `ceil((125 * 1.08) / 12)` calculates the number of packages required
for 125 items with an 8% allowance and 12 items per package.

**Why choose this library:**

- Compared with a fixed numeric field, an expression records how the estimate
  was calculated and allows users to enter their working directly.
- Compared with building a custom parser, arithmetic, parentheses, rounding,
  and error handling are already available.
- Compared with moving every estimate to an external tool, the calculation can
  stay in the order workflow and return a result immediately.

**Boundary:** This is a calculation component. It does not optimize production
schedules, track stock, or model bills of materials. The application must retain
the formula and relevant inputs if estimates need to be reproduced later.

## 3. Unit-aware ordering and measurement entry

**Business need:** Customers or staff enter dimensions for flooring, signage,
cabling, furniture, or other made-to-measure products.

**Example:** `2m + 35cm` evaluates to `2.35` when the application registers `m`
as 1 and `cm` as 0.01, using metres as its base unit.

**Why choose this library:**

- Compared with requiring manual conversion, registered suffixes allow familiar
  units to appear directly in the input.
- Compared with separate calculators, users can combine conversion and
  arithmetic in one field.
- Compared with a full physical-units system, simple conversion factors can be
  sufficient when the application already knows the expected measurement type.

**Boundary:** Units convert to numbers; the library does not track dimensions or
reject incompatible combinations such as length plus weight. Choose a dimensional
analysis library when physical correctness must be enforced across formulas.

## 4. Embedded budgeting and scenario planning

**Business need:** Users explore recurring costs, setup fees, capacity, or
break-even assumptions inside a PHP planning application.

**Example:** `(1250 * 12 + 3500) / 240` calculates annual cost per planned unit.

**Why choose this library:**

- Compared with a plain amount field, a formula lets users express assumptions
  without first calculating the answer elsewhere.
- Compared with a spreadsheet engine, a single numeric expression needs no cell
  references, workbook model, or spreadsheet import workflow.
- Compared with a remote calculation service, evaluation can run within the
  existing PHP application without a separate service integration.

**Boundary:** Choose a spreadsheet engine when users need linked cells, tables,
multi-step models, or workbook compatibility. This library returns one numeric
result per expression and does not manage dependencies between calculations.

## 5. Decimal charges and allocation calculations

**Business need:** A billing application needs configurable calculations for
usage charges, fees, or distributing amounts, with deliberate rounding choices.

**Example:** `round(19.95 * 3, 2)` calculates a decimal amount; `100 / 3` requires
an explicit division scale and rounding policy in rounded decimal mode.

**Why choose this library:**

- Compared with native floating-point formula evaluation, the optional BCMath
  backend preserves decimal-string arithmetic and offers explicit policies for
  division and square roots.
- Compared with hand-coded decimal operations, the expression syntax lets an
  approved formula express several operations together.
- Strict exact evaluation can reject a calculation that would otherwise need
  rounding, making that decision explicit in the application.

**Boundary:** BCMath requires `ext-bcmath`. Use `evaluateExact()` or
`evaluateDecimal()` to retain decimal strings; `evaluate()` converts the result
to float. Division and square-root policies apply to intermediate operations,
not just the final amount. Currency types, tax rules, reconciliation, and
allocation of rounding remainders belong in application code or a money library.

## 6. Simple numeric eligibility and validation rules

**Business need:** An application allows administrators to configure numeric
thresholds for order acceptance, capacity limits, or alerts.

**Example:** `750 >= 500 and 12 <= 24` returns 1 when both conditions hold.
The application supplies the order values before evaluation.

**Why choose this library:**

- Compared with hardcoded thresholds, an approved numeric rule can be stored
  and changed independently of a deployment.
- Compared with a general rule engine, a single arithmetic or boolean condition
  may be all the workflow requires.
- Allowed functions can be restricted to the operations needed by the business.

**Boundary:** The library produces a numeric result; the application decides what
action follows. Named numeric bindings and lazy `if()` expressions support simple
policies. Choose a rule engine when rules need strings, dates, collections,
priorities, explanations, or coordinated actions. Ternary syntax is unsupported.

## Choosing it for an application

The strongest fit is a PHP application with small, independent numeric formulas
and a clear reason for users or administrators to edit them. The native backend
covers general numeric calculations; the BCMath backend covers decimal-string
calculations with strict exactness or explicit rounding. Portable validation can
restrict syntax to the documented shared grammar, but backend numerical behavior
still differs.

Application integration remains necessary:

- Supply numeric inputs through the separate variable-binding map. Register
  trusted domain functions when needed; closures and callable objects can carry
  application context without global functions.
- Define allowed functions and units, validate result ranges, and present useful
  errors to users.
- Store formulas, inputs, backend settings, and versions when reproducibility
  matters. Restrict editing and approve changes when business consequences warrant it.
- Treat registered functions and unit converters as trusted host code. Expression
  resource limits do not bound their execution time or side effects.

For a single fixed formula, ordinary PHP arithmetic is usually simpler. For
linked financial models, a spreadsheet engine may be more suitable. For rich
business policies, use a rule engine. For physical dimensions or currency-domain
guarantees, use a specialized library, potentially alongside PHP-Expression.

The [business formula API](docs/BUSINESS_FORMULAS.md) covers reusable parsed
formulas, validation, inspection, diagnostics, conditions, and rounding. Run
`php examples/business.php` for working examples of these business cases.
