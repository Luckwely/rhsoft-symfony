<?php

namespace App\Service;

use App\DTO\PayslipDashboardKpiDto;
use App\Service\Contract\PayslipMetricsCalculatorInterface;

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
            $lastNetSalary = (float) ($latestPayslip->getSalaireNet() ?? 0);

            $months = [
                1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
                5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
                9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre',
            ];
            $lastMonth = ($months[$latestPayslip->getMois()] ?? (string) $latestPayslip->getMois())
                .' '.$latestPayslip->getAnnee();

            foreach ($payslipsArray as $payslip) {
                $totalGross += (float) ($payslip->getSalaireBrut() ?? 0);
                $totalDeductions += (float) ($payslip->getCotisations() ?? 0);
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
