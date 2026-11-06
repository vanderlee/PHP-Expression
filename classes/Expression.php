<?php

declare(strict_types=1);

namespace Vanderlee\Expression;

use Throwable;
use Vanderlee\Expression\Backend\Backend;
use Vanderlee\Expression\Backend\ExactBackend;
use Vanderlee\Expression\Backend\FloatBackend;
use Vanderlee\Expression\Backend\GrammarParser;
use Vanderlee\Expression\Backend\NativeGrammarBackend;
use Vanderlee\Expression\Backend\NativePhpBackend;
use Vanderlee\Expression\Backend\TreeBackend;
use Vanderlee\Expression\Backend\UnitNormalizer;
use Vanderlee\Expression\Unit\DecimalUnit;
use Vanderlee\Expression\Unit\Unit;

class Expression
{
    private const MAX_UNIT_COUNT = 128;
    private const MAX_UNIT_SUFFIX_LENGTH = 64;
    private const MAX_COMPILED_SOURCES = 8;

    private const DEFAULT_FUNCTIONS = [
        'round_half_even' => 'Vanderlee\\Expression\\NativeRounding::halfEven',
        'round_half_up' => 'Vanderlee\\Expression\\NativeRounding::halfUp',
        'truncate' => 'Vanderlee\\Expression\\NativeRounding::towardZero',
        'round_increment' => 'Vanderlee\\Expression\\NativeRounding::increment',
        'abs' => 'abs',
        'acos' => 'acos',
        'acosh' => 'acosh',
        'asin' => 'asin',
        'asinh' => 'asinh',
        'atan2' => 'atan2',
        'atan' => 'atan',
        'atanh' => 'atanh',
        'ceil' => 'ceil',
        'cos' => 'cos',
        'cosh' => 'cosh',
        'deg2rad' => 'deg2rad',
        'exp' => 'exp',
        'expm1' => 'expm1',
        'floor' => 'floor',
        'fmod' => 'fmod',
        'getrandmax' => 'getrandmax',
        'hypot' => 'hypot',
        'is_finite' => 'is_finite',
        'is_infinite' => 'is_infinite',
        'is_nan' => 'is_nan',
        'log10' => 'log10',
        'log1p' => 'log1p',
        'log' => 'log',
        'max' => 'max',
        'min' => 'min',
        'mt_getrandmax' => 'mt_getrandmax',
        'pi' => 'pi',
        'pow' => 'pow',
        'rad2deg' => 'rad2deg',
        'round' => 'round',
        'sin' => 'sin',
        'sinh' => 'sinh',
        'sqrt' => 'sqrt',
        'tan' => 'tan',
        'tanh' => 'tanh',
    ];

    /**
     * @var array<string, callable>
     */
    private $functions = [];

    /**
     * @var array<string, int|float|string|Unit|DecimalUnit>
     */
    private $units = [];

    /** @var Backend */
    private $backend;

    /** @var bool */
    private $portableValidation;
    private $revision = 0;

    /** @var array|null Last source routing decision, scoped to the registry revision. */
    private $sourceRoute;

    /** @var array Native plans in least-recently-used order, without owner references. */
    private $compiledSources = [];

    public function __construct(?Backend $backend = null, bool $portableValidation = false)
    {
        $this->backend = $backend ?? new NativePhpBackend();
        $this->portableValidation = $portableValidation;
        $this->resetFunctions();
    }

    public function resetFunctions(): void
    {
        $this->configurationChanged();
        $this->functions = self::DEFAULT_FUNCTIONS;
    }

    /** Restrict subsequent evaluations to the documented shared grammar. */
    public function setPortableValidation(bool $enabled): void
    {
        $this->configurationChanged();
        $this->portableValidation = $enabled;
    }

    /**
     * @param mixed $function
     * @throws Exception
     */
    public function addFunction(string $alias, $function = null): void
    {
        $alias = FunctionValidator::alias($alias);
        if ($alias === 'if') {
            throw new DiagnosticException('reserved_name', 'Reserved function name `' . $alias . '`');
        }
        $function = FunctionValidator::target($function ?: $alias);

        $this->configurationChanged();
        $this->functions[$alias] = $function;
    }

    public function removeFunction(string $alias): void
    {
        $this->configurationChanged();
        unset($this->functions[strtolower($alias)]);
    }

    public function clearFunctions(): void
    {
        $this->configurationChanged();
        $this->functions = [];
    }

    /**
     * @param float|int|string|Unit|DecimalUnit $unitsize Numeric values must be finite.
     * @throws Exception
     */
    public function addUnit(string $suffix, $unitsize = 1): void
    {
        if ($suffix === '') {
            throw new Exception('empty unit suffix');
        }

        if (strlen($suffix) > self::MAX_UNIT_SUFFIX_LENGTH) {
            throw new Exception('unit suffix too long');
        }

        if (!array_key_exists($suffix, $this->units) && count($this->units) >= self::MAX_UNIT_COUNT) {
            throw new Exception('too many registered units');
        }

        if (!$unitsize instanceof Unit && !$unitsize instanceof DecimalUnit) {
            if (!is_numeric($unitsize)) {
                throw new Exception('unit conversion must be numeric or a Unit');
            }

            if (is_float($unitsize) && !is_finite($unitsize)) {
                throw new Exception('unit conversion must be finite');
            }
        }

        if ($this->backend instanceof UnitNormalizer) {
            $unitsize = $this->backend->normalizeUnit($unitsize);
        }

        $this->configurationChanged();
        $this->units[$suffix] = $unitsize;
    }

    public function removeUnit(string $suffix): void
    {
        $this->configurationChanged();
        unset($this->units[$suffix]);
    }

    public function clearUnits(): void
    {
        $this->configurationChanged();
        $this->units = [];
    }

    private function configurationChanged(): void
    {
        ++$this->revision;
        $this->sourceRoute = null;
        $this->compiledSources = [];
    }

    public function evaluate(string $expression, array $variables = []): float
    {
        if ($this->requiresTree($expression, $variables)) {
            return $this->compile($expression)->evaluate($variables);
        }
        $result = (float)$this->runBackend($expression);
        if (!is_finite($result)) {
            throw new Exception('expression result must be finite; use evaluateExact() for large decimals');
        }
        return $result;
    }

    private function requiresTree(string $source, array $variables): bool
    {
        if ($variables !== []) {
            return true;
        }
        if ($this->sourceRoute !== null
            && $this->sourceRoute[0] === $source
            && $this->sourceRoute[1] === $this->revision) {
            return $this->sourceRoute[2];
        }
        $requiresTree = $this->determineSourceRoute($source);
        // Bound retained source memory even for inputs rejected by later validation.
        if (strlen($source) <= 4096) {
            $this->sourceRoute = [$source, $this->revision, $requiresTree];
        }
        return $requiresTree;
    }

    private function determineSourceRoute(string $source): bool
    {
        if (preg_match('~\bif\s*\(~i', $source)) {
            return true;
        }
        if (preg_match('~\b(?:round_half_even|round_half_up|truncate|round_increment)\s*\(~i', $source)) {
            return true;
        }
        preg_match_all('~\b([a-z_]\w*)\s*\(~i', $source, $calls);
        foreach ($calls[1] as $alias) {
            $target = $this->functions[strtolower($alias)] ?? null;
            if (is_string($target) && strpos($target, '\\') !== false) {
                return true;
            }
        }
        preg_match_all('~(?<![\w.])[a-z_]\w*\b(?!\s*\()(?!\w)~i', $source, $matches);
        foreach ($matches[0] as $name) {
            if (!in_array(strtolower($name), ['true', 'false', 'not', 'and', 'or', 'xor'], true)) {
                return true;
            }
        }
        foreach ($this->functions as $target) {
            if (!is_string($target)) {
                return true;
            }
        }
        return false;
    }

    /** Parse without executing registered functions or unit conversions. */
    public function compile(string $source): CompiledExpression
    {
        try {
            return $this->compileSource($source);
        } catch (DiagnosticException $error) {
            throw $error;
        } catch (Throwable $error) {
            throw new DiagnosticException('validation_error', $error->getMessage(), null, $error);
        }
    }

    private function compileSource(string $source): CompiledExpression
    {
        // Prefix keys so numeric sources remain string keys during eviction.
        $sourceKey = ':' . $source;
        if (isset($this->compiledSources[$sourceKey])) {
            $prepared = $this->compiledSources[$sourceKey];
            unset($this->compiledSources[$sourceKey]);
            $this->compiledSources[$sourceKey] = $prepared;
            return new CompiledExpression(
                $this,
                $prepared[0],
                $this->revision,
                $prepared[1],
                $prepared[2]
            );
        }
        ResourceLimits::assertExpression($source);
        if (!$this->backend instanceof TreeBackend && !$this->backend instanceof NativePhpBackend) {
            throw new DiagnosticException('unsupported_backend', 'Compilation requires a TreeBackend');
        }
        $native = $this->backend instanceof FloatBackend;
        $tree = (new GrammarParser($source, $this->functions, $this->units, $native, true))->parse();
        $nativeExecution = null;
        if ($native) {
            $prepared = (new NativeGrammarBackend())->prepareTree($tree);
            if ($this->backend instanceof NativePhpBackend || $this->backend instanceof NativeGrammarBackend) {
                $nativeExecution = $prepared;
            }
        }
        if ($this->backend instanceof \Vanderlee\Expression\Backend\BcMathBackend) {
            $this->backend->validateTree($tree, $this->units);
        }
        if ($this->portableValidation) {
            PortableValidator::validate($source, $this->functions, $this->units);
        }
        $metadata = [
            'variables' => [],
            'functions' => [],
            'units' => [],
            'backend' => get_class($this->backend),
            'portable' => $this->portableValidation
        ];
        $this->inspectNode($tree, $metadata, 0);
        foreach (['variables', 'functions', 'units'] as $key) {
            $metadata[$key] = array_values(array_unique($metadata[$key]));
            sort($metadata[$key]);
        }
        $metadata['compatibility'] = $this->compatibility($source);
        if ($nativeExecution !== null) {
            $this->compiledSources[$sourceKey] = [$tree, $metadata, $nativeExecution];
            if (count($this->compiledSources) > self::MAX_COMPILED_SOURCES) {
                array_shift($this->compiledSources);
            }
        }
        return new CompiledExpression($this, $tree, $this->revision, $metadata, $nativeExecution);
    }

    public function validate(string $source): void
    {
        $this->compile($source);
    }

    private function inspectNode(array $node, array &$metadata, int $depth): void
    {
        if ($depth > 2048) {
            throw new DiagnosticException('resource_limit', 'Expression tree too deeply nested');
        }
        if ($node[0] === 'variable') {
            $metadata['variables'][] = $node[1];
        } elseif ($node[0] === 'unit') {
            $metadata['units'][] = $node[2];
        } elseif ($node[0] === 'call' || $node[0] === 'conditional') {
            if ($node[0] === 'call') {
                FunctionValidator::assertArguments($node[1], count($node[2]), $node[3], $node[4]);
            }
            $metadata['functions'][] = $node[3] ?? 'if';
            foreach ($node[2] as $child) {
                $this->inspectNode($child, $metadata, $depth + 1);
            }
        } elseif ($node[0] === 'unary') {
            $this->inspectNode($node[2], $metadata, $depth + 1);
        } elseif ($node[0] === 'binary') {
            $this->inspectNode($node[2], $metadata, $depth + 1);
            $this->inspectNode($node[3], $metadata, $depth + 1);
        }
    }

    private function compatibility(string $source): array
    {
        $checks = ['native_grammar' => null, 'decimal' => null, 'portable' => null];
        foreach ($checks as $name => $_) {
            if ($name === 'decimal' && !extension_loaded('bcmath')) {
                continue;
            }
            try {
                if ($name === 'portable') {
                    PortableValidator::validate($source, $this->functions, $this->units);
                } else {
                    $tree = (new GrammarParser(
                        $source, $this->functions, $this->units, $name === 'native_grammar', true
                    ))->parse();
                    if ($name === 'native_grammar') {
                        (new NativeGrammarBackend())->validateTree($tree);
                    } else {
                        (new \Vanderlee\Expression\Backend\BcMathBackend())->validateTree($tree, $this->units);
                    }
                }
                $checks[$name] = true;
            } catch (Throwable $error) {
                $checks[$name] = false;
            }
        }
        return $checks;
    }

    /** @return float|string */
    private function runBackend(string $expression, bool $exact = false)
    {
        ResourceLimits::assertExpression($expression);
        try {
            if ($this->portableValidation) {
                PortableValidator::validate($expression, $this->functions, $this->units);
            }
            $result = $exact && $this->backend instanceof ExactBackend
                ? $this->backend->evaluateExact($expression, $this->functions, $this->units)
                : $this->backend->evaluate($expression, $this->functions, $this->units);
        } catch (Exception $exception) {
            throw $exception;
        } catch (Throwable $throwable) {
            throw new DiagnosticException('evaluation_error', $throwable->getMessage(), null, $throwable);
        }
        if (is_float($result)) {
            if (!is_finite($result)) {
                throw new Exception('backend must return a finite float');
            }
        } elseif (!is_string($result) || preg_match('~^[+-]?(?:\d+(?:\.\d*)?|\.\d+)$~D', $result) !== 1) {
            throw new Exception('backend must return a finite float or decimal string');
        }
        return $result;
    }

    /** Preserve exact decimal results; approximate float backends are rejected. */
    public function evaluateExact(string $expression, array $variables = []): string
    {
        if ($this->requiresTree($expression, $variables)) {
            return $this->compile($expression)->evaluateExact($variables);
        }
        if ($this->backend instanceof FloatBackend) {
            throw new Exception('evaluateExact() requires a decimal backend');
        }
        $result = $this->runBackend($expression, true);
        if (!is_string($result)) {
            throw new Exception('evaluateExact() requires a decimal-string backend result');
        }
        return $result;
    }

    /** Return a decimal string using the selected backend's rounding policy. */
    public function evaluateDecimal(string $expression, array $variables = []): string
    {
        if ($this->requiresTree($expression, $variables)) {
            return $this->compile($expression)->evaluateDecimal($variables);
        }
        if ($this->backend instanceof FloatBackend) {
            throw new Exception('evaluateDecimal() requires a decimal backend');
        }
        $result = $this->runBackend($expression);
        if (!is_string($result)) {
            throw new Exception('evaluateDecimal() requires a decimal-string backend result');
        }
        return $result;
    }

    public function inspect(string $source): array
    {
        return $this->compile($source)->inspect();
    }

    /** @internal Called by CompiledExpression; configuration changes invalidate saved trees. */
    public function executeCompiled(
        array $tree,
        int $revision,
        array $variables,
        string $mode,
        ?callable $nativeExecution = null
    ) {
        if ($revision !== $this->revision) {
            throw new DiagnosticException(
                'stale_configuration',
                'Evaluator configuration changed; compile the formula again'
            );
        }
        if ($mode !== 'float' && $this->backend instanceof FloatBackend) {
            throw new DiagnosticException('unsupported_backend', 'Decimal results require a decimal backend');
        }
        $values = [];
        foreach ($variables as $name => $value) {
            if (!is_string($name) || !preg_match('~^[a-z_]\w*$~iD', $name)) {
                throw new DiagnosticException('invalid_variable', 'Invalid variable name');
            }
            $name = strtolower($name);
            if (array_key_exists($name, $values)) {
                throw new DiagnosticException('invalid_variable', 'Duplicate variable `' . $name . '`');
            }
            if ((!is_int($value) && !is_float($value) && !is_string($value))
                || !preg_match('~^[+-]?(?:\d+(?:\.\d*)?|\.\d+)(?:[eE][+-]?\d+)?$~D', (string)$value)
                || strlen((string)$value) > 4096) {
                throw new DiagnosticException('invalid_variable', 'Variable `' . $name . '` must be numeric');
            }
            if ($this->backend instanceof FloatBackend) {
                $value = is_string($value) ? $value + 0 : $value;
                if (!is_finite((float)$value)) {
                    throw new DiagnosticException('invalid_variable', 'Variable `' . $name . '` must be finite');
                }
            } elseif (is_float($value)) {
                throw new DiagnosticException(
                    'invalid_variable',
                    'Decimal variables require integers or decimal strings'
                );
            }
            $values[$name] = $value;
        }
        if ($nativeExecution === null || !$this->backend instanceof FloatBackend) {
            $this->assertBindings($tree, $values, 0);
        }
        try {
            if ($nativeExecution !== null && $this->backend instanceof FloatBackend) {
                $result = $nativeExecution($this->functions, $this->units, $values);
            } else {
                $backend = $this->backend instanceof NativePhpBackend ? new NativeGrammarBackend() : $this->backend;
                $result = $backend->evaluateTree($tree, $this->functions, $this->units, $values, $mode === 'exact');
            }
            if (!is_float($result) && (!is_string($result) || !preg_match(
                        '~^[+-]?(?:\d+(?:\.\d*)?|\.\d+)$~D',
                        $result
                    ))) {
                throw new DiagnosticException('invalid_result', 'Backend must return a finite float or decimal string');
            }
            if ($mode === 'float') {
                $result = (float)$result;
                if (!is_finite($result)) {
                    throw new DiagnosticException('non_finite_result', 'Expression result must be finite');
                }
            } elseif (!is_string($result)) {
                throw new DiagnosticException('invalid_result', 'Decimal backend must return a decimal string');
            }
            return $result;
        } catch (DiagnosticException $error) {
            throw $error;
        } catch (Throwable $error) {
            throw new DiagnosticException('evaluation_error', $error->getMessage(), null, $error);
        }
    }

    private function assertBindings(array $node, array $values, int $depth): void
    {
        if ($depth > 2048) {
            throw new DiagnosticException('resource_limit', 'Expression tree too deeply nested');
        }
        if ($node[0] === 'variable' && !array_key_exists($node[1], $values)) {
            throw new DiagnosticException('missing_variable', 'Unknown variable `' . $node[1] . '`', $node[2]);
        }
        if ($node[0] === 'call' || $node[0] === 'conditional') {
            foreach ($node[2] as $child) {
                $this->assertBindings($child, $values, $depth + 1);
            }
        } elseif ($node[0] === 'unary') {
            $this->assertBindings($node[2], $values, $depth + 1);
        } elseif ($node[0] === 'binary') {
            $this->assertBindings($node[2], $values, $depth + 1);
            $this->assertBindings($node[3], $values, $depth + 1);
        }
    }
}
