<?php

namespace App\DTO;

readonly class PayslipDashboardKpiDto
{
    public function __construct(
        public float $lastNetSalary,
        public float $averageGrossSalary,
        public float $totalDeductions,
        public string $lastPayslipMonth
    ) {}
}
