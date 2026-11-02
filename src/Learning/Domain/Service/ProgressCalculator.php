<?php
declare(strict_types=1);
namespace App\Learning\Domain\Service;

final class ProgressCalculator
{
    public static function percentage(int $total, int $completed): int
    {
        return $total === 0 ? 0 : (int) round($completed / $total * 100);
    }
}
