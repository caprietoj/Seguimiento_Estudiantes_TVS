<?php

namespace App\Enums;

use App\Models\Setting;

enum MapPerformanceLevel: string
{
    case Low = 'low';
    case LowAverage = 'low_average';
    case Average = 'average';
    case HighAverage = 'high_average';
    case High = 'high';

    public function label(): string
    {
        return match ($this) {
            self::Low => 'Bajo (< 21%)',
            self::LowAverage => 'Promedio bajo (21–40%)',
            self::Average => 'Promedio (41–60%)',
            self::HighAverage => 'Promedio alto (61–80%)',
            self::High => 'Alto (> 80%)',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::Low => 'Bajo',
            self::LowAverage => 'Prom. bajo',
            self::Average => 'Promedio',
            self::HighAverage => 'Prom. alto',
            self::High => 'Alto',
        };
    }

    /**
     * Deduce el nivel NWEA MAP a partir del percentil (1-99).
     */
    public static function fromPercentile(?int $percentile): ?self
    {
        if ($percentile === null) {
            return null;
        }

        $corteBajo = (int) Setting::get('map_corte_bajo', 21);
        $cortePromBajo = (int) Setting::get('map_corte_promedio_bajo', 40);
        $corteProm = (int) Setting::get('map_corte_promedio', 60);
        $cortePromAlto = (int) Setting::get('map_corte_promedio_alto', 80);

        return match (true) {
            $percentile < $corteBajo => self::Low,
            $percentile <= $cortePromBajo => self::LowAverage,
            $percentile <= $corteProm => self::Average,
            $percentile <= $cortePromAlto => self::HighAverage,
            default => self::High,
        };
    }
}
