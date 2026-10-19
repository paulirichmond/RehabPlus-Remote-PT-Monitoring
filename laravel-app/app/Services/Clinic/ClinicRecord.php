<?php

namespace App\Services\Clinic;

use ArrayAccess;
use JsonSerializable;
use LogicException;

/**
 * One clinic-operations document (appointment, payment, inventory item,
 * shift, time off) as handed to the views. Read-only; fields can be read as
 * properties ($record->status) or keys ($record['status']), like the Eloquent
 * models these pages used before.
 */
final class ClinicRecord implements ArrayAccess, JsonSerializable
{
    public function __construct(private array $fields)
    {
    }

    public function __get(string $name): mixed
    {
        return $this->fields[$name] ?? null;
    }

    public function __isset(string $name): bool
    {
        return isset($this->fields[$name]);
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->fields[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->fields[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new LogicException('ClinicRecord is read-only.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new LogicException('ClinicRecord is read-only.');
    }

    public function toArray(): array
    {
        return $this->fields;
    }

    public function jsonSerialize(): array
    {
        return $this->fields;
    }
}
