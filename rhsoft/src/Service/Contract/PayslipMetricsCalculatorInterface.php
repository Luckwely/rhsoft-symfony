<?php

namespace App\Service\Contract;

use App\DTO\PayslipDashboardKpiDto;

interface PayslipMetricsCalculatorInterface
{
    /**
     * @param iterable $payslips
     */
    public function calculateForYear(iterable $payslips): PayslipDashboardKpiDto;
}
