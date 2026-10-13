<?php

namespace App\Support\Import;

use Illuminate\Support\Str;

/**
 * Compara nombres escritos a mano en el Excel con los registrados en la aplicación.
 *
 * Tolera tildes, mayúsculas, espacios dobles, puntuación y el orden de las palabras
 * ("Pérez Gómez Ana" = "Ana Pérez Gómez"), y devuelve un porcentaje de similitud 0–100.
 */
class NameMatcher
{
    /** Desde este porcentaje se empareja automáticamente (con aviso si no es 100 %). */
    public const AUTO = 88;

    /** Diferencia mínima entre el mejor y el segundo candidato para no considerarlo ambiguo. */
    public const AMBIGUITY_GAP = 4;

    public static function normalize(string $value): string
    {
        $value = Str::ascii(mb_strtolower($value));
        $value = (string) preg_replace('/[^a-z0-9 ]+/', ' ', $value);

        return trim((string) preg_replace('/\s+/', ' ', $value));
    }

    /** Palabras del nombre ordenadas alfabéticamente, para que el orden no importe. */
    public static function key(string $value): string
    {
        $tokens = array_filter(explode(' ', self::normalize($value)), fn (string $t) => $t !== '');
        sort($tokens);

        return implode(' ', $tokens);
    }

    public static function similarity(string $a, string $b): float
    {
        $ka = self::key($a);
        $kb = self::key($b);

        if ($ka === '' || $kb === '') {
            return 0.0;
        }

        if ($ka === $kb) {
            return 100.0;
        }

        similar_text($ka, $kb, $byText);
        $maxLen = max(strlen($ka), strlen($kb));
        $byEdit = $maxLen <= 255 ? (1 - levenshtein($ka, $kb) / $maxLen) * 100 : $byText;

        // Un nombre contenido en el otro ("Ana Pérez" dentro de "Ana María Pérez Gómez")
        // cuenta como coincidencia fuerte solo si comparte al menos dos palabras.
        $ta = explode(' ', $ka);
        $tb = explode(' ', $kb);
        $shared = count(array_intersect($ta, $tb));
        $bySubset = $shared >= 2 && $shared === min(count($ta), count($tb)) ? 90.0 : 0.0;

        return round(max($byText, $byEdit, $bySubset), 1);
    }

    /**
     * Mejor candidato de una lista.
     *
     * @template T
     *
     * @param  iterable<T>  $candidates
     * @param  callable(T): string  $nameOf
     * @return array{match: T|null, score: float, ambiguous: bool, runner_up: T|null}
     */
    public static function best(string $name, iterable $candidates, callable $nameOf): array
    {
        $best = null;
        $bestScore = 0.0;
        $second = null;
        $secondScore = 0.0;

        foreach ($candidates as $candidate) {
            $score = self::similarity($name, $nameOf($candidate));

            if ($score > $bestScore) {
                [$second, $secondScore] = [$best, $bestScore];
                [$best, $bestScore] = [$candidate, $score];
            } elseif ($score > $secondScore) {
                [$second, $secondScore] = [$candidate, $score];
            }
        }

        $ambiguous = $best !== null
            && $bestScore < 100
            && $secondScore >= self::AUTO
            && ($bestScore - $secondScore) < self::AMBIGUITY_GAP;

        return ['match' => $best, 'score' => $bestScore, 'ambiguous' => $ambiguous, 'runner_up' => $second];
    }
}
