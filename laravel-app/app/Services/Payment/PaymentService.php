<?php

namespace App\Services\Payment;

use App\Services\Clinic\ClinicRecord;
use App\Services\Clinic\ClinicStore;
use Carbon\CarbonImmutable;

/** Billing and payment records (Firestore `payments`, see ClinicStore). */
class PaymentService
{
    public function __construct(private ClinicStore $store)
    {
    }

    public function overview(): array
    {
        $payments = $this->store->all(ClinicStore::PAYMENTS)
            ->sortByDesc(fn (ClinicRecord $p) => $p->date)
            ->values();
        $fee = fn (ClinicRecord $p) => (float) $p->fee;

        return [
            'sessionFees' => '₱'.number_format($payments->sum($fee), 2),
            'paymentStatus' => $payments->where('status', 'Paid')->count(),
            'outstanding' => '₱'.number_format($payments->whereIn('status', ['Pending', 'Partial'])->sum($fee), 2),
            'paymentHistory' => $payments->count(),
            'payments' => $payments->map(fn (ClinicRecord $p) => new ClinicRecord(
                ['fee' => number_format((float) $p->fee, 2, '.', '')] + $p->toArray(),
            )),
        ];
    }

    public function create(array $data): string
    {
        return $this->store->create(ClinicStore::PAYMENTS, [
            'patient' => $data['patient'],
            'session' => $data['session'],
            'fee' => round((float) $data['fee'], 2),
            'status' => $data['status'],
            'date' => CarbonImmutable::parse($data['date'])->toDateString(),
        ]);
    }

    public function updateStatus(string $id, string $status): void
    {
        $this->store->findOrFail(ClinicStore::PAYMENTS, $id);
        $this->store->update(ClinicStore::PAYMENTS, $id, ['status' => $status]);
    }

    public function delete(string $id): void
    {
        $this->store->findOrFail(ClinicStore::PAYMENTS, $id);
        $this->store->delete(ClinicStore::PAYMENTS, $id);
    }
}
