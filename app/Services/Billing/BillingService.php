<?php

namespace App\Services\Billing;

class BillingService
{
    /**
     * Payment records kept in the session.
     */
    public function records(): array
    {
        return session()->get('billing_records') ?? [];
    }

    public function overview(): array
    {
        $payments = $this->records();

        foreach ($payments as &$payment) {
            $payment['fee'] = $this->formatPeso($this->normalizeFee($payment['fee'] ?? 0));
        }
        unset($payment);

        $totalFees = 0.0;
        foreach ($payments as $payment) {
            $totalFees += $this->normalizeFee($payment['fee'] ?? 0);
        }

        $paidCount = count(array_filter($payments, static fn (array $payment): bool => ($payment['status'] ?? '') === 'Paid'));
        $statusText = count($payments) > 0 ? round(($paidCount / count($payments)) * 100) . '% collected' : '0% collected';

        return [
            'payments' => $payments,
            'sessionFees' => $this->formatPeso($totalFees),
            'paymentStatus' => $statusText,
            'outstanding' => $this->formatPeso(max(0, $totalFees * 0.35)),
            'paymentHistory' => count($payments),
        ];
    }

    public function add(array $input): void
    {
        $payments = $this->records();
        $payments[] = [
            'patient' => $input['patient'],
            'session' => $input['session'],
            'fee' => $this->formatPeso((float) $input['fee']),
            'status' => $input['status'] !== '' ? $input['status'] : 'Pending',
            'date' => $input['date'],
        ];
        session()->set('billing_records', $payments);
    }

    public function formatPeso(float $amount): string
    {
        return '₱ ' . number_format($amount, 2, '.', ',');
    }

    /**
     * Turn a stored fee such as "₱ 1,500.00" back into a number.
     */
    public function normalizeFee($value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        $clean = preg_replace('/[^0-9.\-]/', '', (string) $value);
        return (float) ($clean !== '' ? $clean : 0);
    }
}
