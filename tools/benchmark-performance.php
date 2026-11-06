<?php

declare(strict_types=1);

use Vanderlee\Expression\Backend\BcMathBackend;
use Vanderlee\Expression\Backend\NativeGrammarBackend;
use Vanderlee\Expression\Expression;
use Vanderlee\Expression\Unit\DecimalUnit;
use Vanderlee\Expression\Unit\Unit;

require __DIR__ . '/../vendor/autoload.php';

// Do not change global precision, PRNG, or BCMath settings.
$options = ['case' => 'all', 'backend' => 'all', 'source-mode' => 'repeat', 'execution' => 'evaluate', 'iterations' => 5000, 'runs' => 7, 'warmup' => 200, 'json' => false, 'output' => null, 'baseline' => null];
try {
    foreach (array_slice($argv, 1) as $argument) {
        if ($argument === '--help') {
            echo "Usage: php tools/benchmark-performance.php [case] [--case=NAME] [--backend=all|native|grammar|exact|rounded]\n";
            echo "       [--iterations=N] [--runs=N] [--warmup=N] [--json] [--output=FILE] [--baseline=FILE] [--list]\n";
            echo "       [--source-mode=repeat|alternate]\n";
            echo "       [--execution=evaluate|compiled]\n";
            exit(0);
        }
        if ($argument === '--json') { $options['json'] = true; continue; }
        if ($argument === '--list') { $options['list'] = true; continue; }
        if (substr($argument, 0, 2) !== '--') {
            if ($options['case'] !== 'all') { throw new InvalidArgumentException('Only one case may be selected'); }
            $options['case'] = $argument;
            continue;
        }
        $parts = explode('=', substr($argument, 2), 2);
        if (count($parts) !== 2 || !array_key_exists($parts[0], $options) || $parts[0] === 'json') {
            throw new InvalidArgumentException('Unknown argument: ' . $argument);
        }
        $options[$parts[0]] = $parts[1];
    }
    foreach (['iterations' => [1, 1000000], 'runs' => [1, 31], 'warmup' => [0, 100000]] as $key => $range) {
        if (!ctype_digit((string) $options[$key]) || $options[$key] < $range[0] || $options[$key] > $range[1]) {
            throw new InvalidArgumentException("$key must be between {$range[0]} and {$range[1]}");
        }
        $options[$key] = (int) $options[$key];
    }
    if (!in_array($options['backend'], ['all', 'native', 'grammar', 'exact', 'rounded'], true)) { throw new InvalidArgumentException('Unknown backend'); }
    if (!in_array($options['source-mode'], ['repeat', 'alternate'], true)) { throw new InvalidArgumentException('Unknown source mode'); }
    if (!in_array($options['execution'], ['evaluate', 'compiled'], true)) { throw new InvalidArgumentException('Unknown execution mode'); }
    $cases = [
        'unit_suffix_regex' => [
            'source' => '1unit119 + 2unit099 + 3unit010 + 4unit001', 'expected' => '361',
            'setup' => static function (Expression $expression, string $backend): void {
                for ($i = 0; $i < 120; ++$i) { $expression->addUnit(sprintf('unit%03d', $i), $i + 1); }
            },
        ],
        'numeric_normalization' => ['source' => '0x10 + 0b1010 + 0o17 + 1_250 + 3.5', 'expected' => '1294.5'],
        'boolean_whitespace' => ['source' => 'not ( 0 ) and ( 1 or 0 ) xor 0', 'expected' => '1'],
        'resource_limits' => ['source' => 'sin(1) + cos(2) + sqrt(9) + max(1, 2, 3) + min(4, 5)', 'expected' => sin(1) + cos(2) + 10, 'backends' => ['native', 'grammar']],
        'eval_formatting' => ['source' => '1 + 2 * 3 + 4 / 2', 'expected' => '9'],
        'bound_formula' => ['source' => 'price * quantity * (1 - discount)', 'variables' => ['price' => '20', 'quantity' => 3, 'discount' => '0.1'], 'expected' => '54'],
        'switching_bound_formulas' => [
            'source' => 'price * quantity', 'alternate_source' => 'price * (quantity + 1)',
            'variables' => ['price' => '20', 'quantity' => 3],
            'expected' => '60', 'alternate_expected' => '80',
        ],
        'switching_lazy_formulas' => [
            'source' => 'if(quantity > 10, price * quantity * 0.9, price * quantity)',
            'alternate_source' => 'if(quantity > 2, price * quantity * 0.9, price * quantity)',
            'variables' => ['price' => '20', 'quantity' => 3],
            'expected' => '60', 'alternate_expected' => '54',
        ],
        'lazy_formula' => ['source' => 'if(quantity > 10, price * quantity * 0.9, price * quantity)', 'variables' => ['price' => '20', 'quantity' => 3], 'expected' => '60'],
        'decimal_formula' => ['source' => 'round((19.95 * 3 + 4.50) * 1.21, 2)', 'expected' => '77.86'],
        'nested_functions' => ['source' => 'max(abs(-3),min(4,5)) + pow(2,3)', 'expected' => '12'],
        'long_expression' => ['source' => implode('+', array_fill(0, 64, 'abs(-1)')), 'expected' => '64'],
        'rounded_division_root' => [
            'source' => 'sqrt(2) + 1/3', 'expected' => '1.74754689570642838213',
            'native_expected' => sqrt(2) + 1 / 3, 'backends' => ['native', 'grammar', 'rounded'],
        ],
        'custom_units' => [
            'source' => '10-2step+3step**2', 'expected' => '42',
            'setup' => static function (Expression $expression, string $backend): void {
                $expression->addUnit('step', in_array($backend, ['native', 'grammar'], true) ? new class implements Unit {
                    public function convert(float $amount): float { return $amount * 2; }
                } : new class implements DecimalUnit {
                    public function convert(string $amount): string { return bcmul($amount, '2', 0); }
                });
            },
        ],
    ];
    if (isset($options['list'])) { echo implode("\n", array_keys($cases)) . "\n"; exit(0); }
    if ($options['case'] !== 'all') {
        if (!isset($cases[$options['case']])) { throw new InvalidArgumentException('Unknown case: ' . $options['case']); }
        $cases = [$options['case'] => $cases[$options['case']]];
    }
    if (in_array($options['backend'], ['exact', 'rounded'], true) && !extension_loaded('bcmath')) { throw new RuntimeException('Selected decimal backend requires ext-bcmath'); }
    $backends = $options['backend'] === 'all' ? ['native', 'grammar', 'exact', 'rounded'] : [$options['backend']];
    $skipped = [];
    $results = [];
    $nanosecondClock = function_exists('hrtime');
    $clock = static function () use ($nanosecondClock): float { return $nanosecondClock ? (float) hrtime(true) : microtime(true) * 1000000000; };
    $validate = static function ($actual, $expected, string $backend): void {
        if (!in_array($backend, ['native', 'grammar'], true)) {
            if ($actual !== $expected) { throw new RuntimeException('Decimal result mismatch: ' . $actual . ' != ' . $expected); }
        } elseif (!is_finite($actual) || abs($actual - (float) $expected) > 1e-12 * max(1, abs((float) $expected))) {
            throw new RuntimeException('Native benchmark result mismatch');
        }
    };
    foreach ($cases as $name => $case) {
        foreach ($backends as $backend) {
            if (!in_array($backend, $case['backends'] ?? ['native', 'grammar', 'exact', 'rounded'], true)) {
                $skipped[] = ['case' => $name, 'backend' => $backend, 'reason' => 'Unsupported by this arithmetic policy'];
                continue;
            }
            if (!in_array($backend, ['native', 'grammar'], true) && !extension_loaded('bcmath')) {
                $skipped[] = ['case' => $name, 'backend' => $backend, 'reason' => 'Optional ext-bcmath is unavailable'];
                continue;
            }
            $factory = static function () use ($backend, $case): Expression {
                $expression = $backend === 'native' ? new Expression()
                    : ($backend === 'grammar' ? new Expression(new NativeGrammarBackend())
                        : new Expression(new BcMathBackend($backend === 'rounded' ? 20 : null)));
                if (isset($case['setup'])) { $case['setup']($expression, $backend); }
                return $expression;
            };
            $method = in_array($backend, ['native', 'grammar'], true) ? 'evaluate' : ($backend === 'exact' ? 'evaluateExact' : 'evaluateDecimal');
            $expected = in_array($backend, ['native', 'grammar'], true) ? ($case['native_expected'] ?? $case['expected']) : $case['expected'];
            $sources = [
                $case['source'],
                $options['source-mode'] === 'alternate'
                    ? ($case['alternate_source'] ?? '(' . $case['source'] . ')') : $case['source'],
            ];
            $expectedResults = [
                $expected,
                $options['source-mode'] === 'alternate' ? ($case['alternate_expected'] ?? $expected) : $expected,
            ];
            $variables = $case['variables'] ?? [];
            $prepare = static function (Expression $expression) use ($sources, $method, $variables, $options): callable {
                if ($options['execution'] === 'compiled') {
                    // Retain each formula once; switching never recompiles it.
                    $first = $expression->compile($sources[0]);
                    $formulas = [
                        $first,
                        $sources[1] === $sources[0] ? $first : $expression->compile($sources[1]),
                    ];
                    return static function (int $index) use ($formulas, $method, $variables) {
                        return $formulas[$index]->$method($variables);
                    };
                }
                return static function (int $index) use ($expression, $sources, $method, $variables) {
                    return $expression->$method($sources[$index], $variables);
                };
            };
            $expression = $factory();
            $evaluate = $prepare($expression);
            foreach ([0, 1] as $index) {
                $validate($evaluate($index), $expectedResults[$index], $backend);
            }
            for ($i = 0; $i < $options['warmup']; ++$i) { $evaluate($i % 2); }
            unset($evaluate, $expression);
            $resetPeak = function_exists('memory_reset_peak_usage');
            if ($resetPeak) { memory_reset_peak_usage(); }
            $baselineMemory = memory_get_usage(false);
            $samples = [];
            for ($run = 0; $run < $options['runs']; ++$run) {
                $expression = $factory();
                $evaluate = $prepare($expression);
                $started = $clock();
                for ($i = 0; $i < $options['iterations']; ++$i) { $actual = $evaluate($i % 2); }
                $samples[] = ($clock() - $started) / 1000000;
                $validate($actual, $expectedResults[($options['iterations'] - 1) % 2], $backend);
                foreach ([0, 1] as $index) {
                    $validate($evaluate($index), $expectedResults[$index], $backend);
                }
                unset($evaluate, $expression);
            }
            $peak = memory_get_peak_usage(false);
            sort($samples);
            $middle = intdiv(count($samples), 2);
            $median = count($samples) % 2 ? $samples[$middle] : ($samples[$middle - 1] + $samples[$middle]) / 2;
            $results[] = [
                'case' => $name, 'backend' => $backend, 'expression' => $case['source'], 'result' => $actual,
                'source_mode' => $options['source-mode'],
                'sources' => $sources,
                'execution' => $options['execution'], 'variables' => $variables,
                'iterations_per_run' => $options['iterations'], 'runs' => $options['runs'],
                'median_ms' => $median, 'min_ms' => min($samples), 'max_ms' => max($samples), 'samples_ms' => $samples,
                'evaluations_per_second' => $median > 0 ? $options['iterations'] * 1000 / $median : null,
                'nanoseconds_per_evaluation' => $median * 1000000 / $options['iterations'],
                'memory_baseline_bytes' => $baselineMemory, 'memory_peak_bytes' => $peak,
                'memory_peak_delta_bytes' => $resetPeak ? max(0, $peak - $baselineMemory) : null,
                'memory_peak_reset_for_case' => $resetPeak,
            ];
        }
    }
    if ($results === []) { throw new RuntimeException('No selected case is supported by the selected backend'); }
    $extensions = get_loaded_extensions();
    sort($extensions);
    $report = [
        'format_version' => 1, 'recorded_at_utc' => gmdate('c'),
        'environment' => [
            'php_version' => PHP_VERSION, 'os' => PHP_OS, 'architecture_bits' => PHP_INT_SIZE * 8,
            'clock' => $nanosecondClock ? 'hrtime' : 'microtime', 'extensions' => $extensions,
            'opcache_cli' => ini_get('opcache.enable_cli'), 'jit' => ini_get('opcache.jit'),
            'precision' => ini_get('precision'), 'serialize_precision' => ini_get('serialize_precision'), 'bcmath_scale' => ini_get('bcmath.scale'),
        ],
        'parameters' => $options,
        'measurement_scope' => $options['execution'] === 'compiled'
            ? 'Reusable compiled formula evaluations; registration and compilation excluded from timing. Memory includes per-run setup; peak delta requires memory_reset_peak_usage, otherwise peak is process-wide.'
            : 'Full public API evaluations; registration excluded from timing. Memory includes per-run setup; peak delta requires memory_reset_peak_usage, otherwise peak is process-wide.',
        'results' => $results, 'skipped' => $skipped,
    ];
    if ($options['baseline'] !== null) {
        $baseline = json_decode(file_get_contents($options['baseline']), true);
        if (!is_array($baseline) || ($baseline['format_version'] ?? null) !== 1 || !isset($baseline['results'], $baseline['environment'])) { throw new InvalidArgumentException('Invalid baseline JSON report'); }
        $previous = [];
        foreach ($baseline['results'] as $row) { $previous[$row['case'] . '/' . $row['backend']] = $row; }
        $comparisons = [];
        foreach ($results as $row) {
            $key = $row['case'] . '/' . $row['backend'];
            if (!isset($previous[$key]) || $previous[$key]['expression'] !== $row['expression']) { continue; }
            if (($previous[$key]['source_mode'] ?? 'repeat') !== $row['source_mode']) { continue; }
            $oldSources = $previous[$key]['sources'] ?? [
                $previous[$key]['expression'],
                ($previous[$key]['source_mode'] ?? 'repeat') === 'alternate'
                    ? '(' . $previous[$key]['expression'] . ')' : $previous[$key]['expression'],
            ];
            if ($oldSources !== $row['sources']) { continue; }
            if (($previous[$key]['execution'] ?? 'evaluate') !== $row['execution']
                || ($previous[$key]['variables'] ?? []) !== $row['variables']) { continue; }
            $old = $previous[$key]['nanoseconds_per_evaluation'];
            $comparisons[] = ['case' => $row['case'], 'backend' => $row['backend'], 'change_percent' => $old > 0 ? 100 * ($row['nanoseconds_per_evaluation'] / $old - 1) : null];
        }
        $report['baseline_comparison'] = ['environment_matches' => $baseline['environment'] === $report['environment'], 'results' => $comparisons];
    }
    $json = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
    if ($json === false) { throw new RuntimeException('Cannot encode benchmark report'); }
    if ($options['output'] !== null) {
        $directory = dirname($options['output']);
        if (!is_dir($directory) && !mkdir($directory, 0777, true)) { throw new RuntimeException('Cannot create output directory'); }
        if (file_put_contents($options['output'], $json . "\n") === false) { throw new RuntimeException('Cannot write output report'); }
    }
    if ($options['json']) { echo $json . "\n"; }
    else {
        printf("PHP %s | %s | %d-bit | %s | execution=%s source=%s | iterations=%d runs=%d warmup=%d\n", PHP_VERSION, PHP_OS, PHP_INT_SIZE * 8, $report['environment']['clock'], $options['execution'], $options['source-mode'], $options['iterations'], $options['runs'], $options['warmup']);
        foreach ($results as $row) {
            printf("%-24s %-7s median=%9.3fms rate=%10.0f/s ns/eval=%10.0f peak=%8dB delta=%s\n", $row['case'], $row['backend'], $row['median_ms'], $row['evaluations_per_second'], $row['nanoseconds_per_evaluation'], $row['memory_peak_bytes'], $row['memory_peak_delta_bytes'] === null ? 'n/a (process peak)' : $row['memory_peak_delta_bytes'] . 'B');
        }
        foreach ($skipped as $skip) { printf("SKIP %s/%s: %s\n", $skip['case'], $skip['backend'], $skip['reason']); }
        if (isset($report['baseline_comparison'])) {
            echo 'Baseline environment match: ' . ($report['baseline_comparison']['environment_matches'] ? 'yes' : 'NO; timings are not directly comparable') . "\n";
            foreach ($report['baseline_comparison']['results'] as $row) { printf("CHANGE %s/%s: %+.1f%% ns/evaluation\n", $row['case'], $row['backend'], $row['change_percent']); }
        }
        if ($options['output'] !== null) { echo 'JSON report: ' . $options['output'] . "\n"; }
    }
} catch (Throwable $exception) {
    fwrite(STDERR, 'Benchmark failed: ' . $exception->getMessage() . "\n");
    exit(1);
}
