<?php

namespace App\Services\Clinic;

use App\Services\Firebase\FirestoreClient;
use Illuminate\Support\Collection;

/**
 * Clinic operations in Firestore: top-level collections only the dashboard
 * uses (the app's security rules don't list them, so phones can't read them).
 *
 *   appointments/{id}   {patient, therapist, patient_condition, contact, date "Y-m-d", time "H:i", session, notes, status, completed_at}
 *   payments/{id}       {patient, session, fee (number), status, date "Y-m-d"}
 *   inventory/{id}      {item, category, stock, reorder, status}
 *   staffShifts/{id}    {staff, role, day, shift, room}
 *   staffTimeOff/{id}   {staff, date "Y-m-d", type, time, reason}
 *
 * Every document also gets createdAt/updatedAt server timestamps. Field names
 * match the form inputs. Collections are small, so they are read whole and
 * sorted/filtered in PHP (no Firestore indexes needed).
 */
class ClinicStore
{
    public const APPOINTMENTS = 'appointments';

    public const PAYMENTS = 'payments';

    public const INVENTORY = 'inventory';

    public const SHIFTS = 'staffShifts';

    public const TIME_OFF = 'staffTimeOff';

    public function __construct(private FirestoreClient $db)
    {
    }

    /** @return Collection<int, ClinicRecord> */
    public function all(string $collection): Collection
    {
        return collect($this->db->query('', $collection))->map(fn (array $doc) => $this->record($doc));
    }

    /** The record, or null for a missing document or an id that isn't a valid document id. */
    public function find(string $collection, string $id): ?ClinicRecord
    {
        if (! FirestoreClient::isValidId($id)) {
            return null;
        }
        $doc = $this->db->get("{$collection}/{$id}");

        return $doc ? $this->record($doc) : null;
    }

    public function findOrFail(string $collection, string $id): ClinicRecord
    {
        return $this->find($collection, $id) ?? abort(404);
    }

    /** @return string the new document id */
    public function create(string $collection, array $fields): string
    {
        $id = FirestoreClient::newId();
        $this->db->commit([
            $this->db->createWrite("{$collection}/{$id}", $fields, ['createdAt', 'updatedAt']),
        ]);

        return $id;
    }

    /** Merge $fields into an existing record. */
    public function update(string $collection, string $id, array $fields): void
    {
        $this->db->commit([
            $this->db->mergeWrite("{$collection}/{$id}", $fields, serverTimestamps: ['updatedAt']),
        ]);
    }

    public function delete(string $collection, string $id): void
    {
        $this->db->delete("{$collection}/{$id}");
    }

    private function record(array $doc): ClinicRecord
    {
        $fields = $doc;
        unset($fields['_id'], $fields['_path']);

        return new ClinicRecord(['id' => $doc['_id']] + $fields);
    }
}
