<?php

namespace App\Service;

use App\DTO\PayslipDashboardKpiDto;
use App\Service\Contract\PayslipMetricsCalculatorInterface;
use IntlDateFormatter;

class PayslipMetricsCalculator implements PayslipMetricsCalculatorInterface
{
    public function calculateForYear(iterable $payslips): PayslipDashboardKpiDto
    {
        $totalGross = 0.0;
        $totalDeductions = 0.0;
        $count = 0;
        $lastNetSalary = 0.0;
        $lastMonth = '-';

        $payslipsArray = is_array($payslips) ? $payslips : iterator_to_array($payslips);

        if (count($payslipsArray) > 0) {
            $latestPayslip = $payslipsArray[0];
            $lastNetSalary = $latestPayslip->getNetAmount();

            $formatter = new IntlDateFormatter('fr_FR', IntlDateFormatter::NONE, IntlDateFormatter::NONE, null, null, 'MMMM yyyy');
            $lastMonth = ucfirst($formatter->format($latestPayslip->getStartDate()));

            foreach ($payslipsArray as $payslip) {
                $totalGross += $payslip->getGrossAmount();
                $totalDeductions += $payslip->getDeductions();
                $count++;
            }
        }

        $averageGross = $count > 0 ? ($totalGross / $count) : 0.0;

        return new PayslipDashboardKpiDto(
            lastNetSalary: $lastNetSalary,
            averageGrossSalary: $averageGross,
            totalDeductions: $totalDeductions,
            lastPayslipMonth: $lastMonth
        );
    }
}
