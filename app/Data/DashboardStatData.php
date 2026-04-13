<?php

namespace App\Data;

use Spatie\LaravelData\Data;

/**
 * @phpstan-type DashboardStatPayload array{
 *     label: string,
 *     value: string,
 *     icon: string,
 *     tone: string
 * }
 */
class DashboardStatData extends Data
{
    /**
     * Summary of __construct
     * @param string $label
     * @param string $value
     * @param string $icon
     * @param string $tone
     */
    public function __construct(
        public string $label,
        public string $value,
        public string $icon,
        public string $tone,
    ) {
    }
}
