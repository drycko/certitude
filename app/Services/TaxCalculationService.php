<?php

namespace App\Services;

class TaxCalculationService
{
    /**
     * Calculate tax for an invoice.
     *
     * @param float $amount The subtotal amount before tax
     * @return array Tax calculation details
     */
    public function calculateTaxForInvoice(float $amount): array
    {
        // Get tax configuration from config or database
        $taxRate = config('app.tax_rate', 15.0); // Default 15% VAT
        $taxName = config('app.tax_name', 'VAT');
        $taxType = config('app.tax_type', 'vat');
        $taxInclusive = config('app.tax_inclusive', false);

        if ($taxInclusive) {
            // Tax is already included in the amount
            $totalAmount = $amount;
            $taxAmount = $amount * ($taxRate / (100 + $taxRate));
            $subtotalAmount = $amount - $taxAmount;
        } else {
            // Tax needs to be added to the amount
            $subtotalAmount = $amount;
            $taxAmount = $amount * ($taxRate / 100);
            $totalAmount = $subtotalAmount + $taxAmount;
        }

        return [
            'subtotal_amount' => round($subtotalAmount, 2),
            'tax_amount' => round($taxAmount, 2),
            'total_amount' => round($totalAmount, 2),
            'tax_rate' => $taxRate,
            'tax_name' => $taxName,
            'tax_type' => $taxType,
            'tax_inclusive' => $taxInclusive,
            'tax_id' => null, // Can be linked to a tax record in database
        ];
    }

    /**
     * Calculate tax for a specific amount with custom tax rate.
     *
     * @param float $amount
     * @param float $customTaxRate
     * @param bool $inclusive
     * @return array
     */
    public function calculateCustomTax(float $amount, float $customTaxRate, bool $inclusive = false): array
    {
        if ($inclusive) {
            $totalAmount = $amount;
            $taxAmount = $amount * ($customTaxRate / (100 + $customTaxRate));
            $subtotalAmount = $amount - $taxAmount;
        } else {
            $subtotalAmount = $amount;
            $taxAmount = $amount * ($customTaxRate / 100);
            $totalAmount = $subtotalAmount + $taxAmount;
        }

        return [
            'subtotal_amount' => round($subtotalAmount, 2),
            'tax_amount' => round($taxAmount, 2),
            'total_amount' => round($totalAmount, 2),
            'tax_rate' => $customTaxRate,
            'tax_inclusive' => $inclusive,
        ];
    }
}
