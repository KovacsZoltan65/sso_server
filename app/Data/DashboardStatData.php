<?php

namespace App\Data;

use Spatie\LaravelData\Data;

class DashboardStatData extends Data
{
    public function __construct(
        public string $label,
        public string $value,
        public string $icon,
        public string $tone,
    ) {
    }
}
