<?php
namespace App\Services;

/** Safe arithmetic for saved laboratory formulas; never executes PHP code. */
class ReportFormulas
{
    public static function key(mixed $value): string { return is_scalar($value) ? trim((string) $value) : ''; }
    public static function approved(mixed $value): bool { return in_array($value, [true, 1, '1', 'true'], true); }
    public static function matches(array $test, string $key): bool
    {
        return $key !== '' && (self::key($test['shortcut'] ?? '') === $key || self::key($test['name'] ?? '') === $key);
    }
    public static function children(mixed $value): ?array
    {
        for ($depth = 0; is_string($value) && $depth < 2; $depth++) {
            $value = json_decode($value, true);
            if (json_last_error() !== JSON_ERROR_NONE) return null;
        }
        return $value === null ? [] : (is_array($value) ? $value : null);
    }
    private function number(mixed $value): ?float
    {
        if (!is_int($value) && !is_float($value) && !is_string($value)) return null;
        $text = trim((string) $value);
        if (!preg_match('/^[+-]?(?:\d+(?:\.\d*)?|\.\d+)(?:[eE][+-]?\d+)?$/D', $text)) return null;
        $n = (float) $text; return is_finite($n) ? $n : null;
    }
    private function invalid(string $reason): array { return ['value' => null, 'complete' => false, 'reason' => $reason]; }

    public function evaluate(array $tests, array $definitions): array
    {
        $byName = []; $results = []; $visiting = [];
        foreach ($definitions as $formula) {
            $key = self::key($formula['name'] ?? '');
            if ($key === '') continue;
            if (array_key_exists($key, $byName)) {
                if (($byName[$key]['tokens'] ?? null) !== ($formula['tokens'] ?? null)) $byName[$key] = null;
            } else $byName[$key] = $formula;
        }
        $resolve = function (string $key) use (&$resolve, &$results, &$visiting, $byName, $tests): array {
            if (array_key_exists($key, $results)) return $results[$key];
            if (isset($visiting[$key]) || count($visiting) >= 128) return $this->invalid('cycle');
            $f = $byName[$key] ?? null;
            if (!$f || !is_array($f['tokens'] ?? null) || !$f['tokens'] || count($f['tokens']) > 512) return $this->invalid('invalid');
            $visiting[$key] = true; $complete = true; $digits = ''; $tokens = []; $failure = null;
            $flush = function () use (&$digits, &$tokens, &$failure): void {
                if ($digits === '') return;
                $n = $this->number($digits);
                if ($n === null) $failure = 'invalid'; else $tokens[] = $n;
                $digits = '';
            };
            foreach ($f['tokens'] as $raw) {
                $token = self::key($raw);
                if (preg_match('/^[\d.]+$/D', $token)) { $digits .= $token; continue; }
                $flush(); if ($failure) break;
                if (in_array($token, ['+', '-', '*', '/', '(', ')'], true)) { $tokens[] = $token; continue; }
                if (array_key_exists($token, $byName)) $operand = $resolve($token);
                else {
                    $matches = array_values(array_filter($tests, fn ($test) => is_array($test) && self::matches($test, $token)));
                    $n = count($matches) === 1 ? $this->number($matches[0]['result'] ?? null) : null;
                    $operand = $n === null ? $this->invalid('missing') : ['value' => $n, 'complete' => self::approved($matches[0]['is_done'] ?? false)];
                }
                if ($operand['value'] === null) { $failure = $operand['reason'] ?? 'missing'; break; }
                $complete = $complete && $operand['complete']; $tokens[] = (float) $operand['value'];
            }
            $flush();
            try { $result = $failure ? $this->invalid($failure) : ['value' => $this->arithmetic($tokens), 'complete' => $complete, 'reason' => $complete ? null : 'unapproved']; }
            catch (\RuntimeException $e) { $result = $this->invalid($e->getMessage()); }
            unset($visiting[$key]); return $results[$key] = $result;
        };
        foreach (array_keys($byName) as $key) $results[$key] = $resolve((string) $key);
        return $results;
    }

    private function arithmetic(array $tokens): float
    {
        $at = 0; $sum = null;
        $primary = function () use (&$primary, &$sum, &$at, $tokens): float {
            $token = $tokens[$at++] ?? null;
            if (is_float($token) || is_int($token)) return $token;
            if ($token === '+' || $token === '-') return ($token === '-' ? -1 : 1) * $primary();
            if ($token === '(') { $n = $sum(); if (($tokens[$at++] ?? null) !== ')') throw new \RuntimeException('invalid'); return $n; }
            throw new \RuntimeException('invalid');
        };
        $product = function () use ($primary, &$at, $tokens): float {
            $n = $primary();
            while (in_array($tokens[$at] ?? null, ['*', '/'], true)) {
                $op = $tokens[$at++]; $rhs = $primary();
                if ($op === '/' && $rhs == 0) throw new \RuntimeException('division_by_zero');
                $n = $op === '*' ? $n * $rhs : $n / $rhs;
            }
            return $n;
        };
        $sum = function () use ($product, &$at, $tokens): float {
            $n = $product();
            while (in_array($tokens[$at] ?? null, ['+', '-'], true)) { $op = $tokens[$at++]; $rhs = $product(); $n = $op === '+' ? $n + $rhs : $n - $rhs; }
            return $n;
        };
        $result = $sum();
        if ($at !== count($tokens) || !is_finite($result * 100)) throw new \RuntimeException('invalid');
        // Match JavaScript Math.round, including negative half values.
        return floor($result * 100 + 0.5) / 100;
    }
}
