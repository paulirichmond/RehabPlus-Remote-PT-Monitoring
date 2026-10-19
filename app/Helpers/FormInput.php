<?php

namespace App\Helpers;

use CodeIgniter\HTTP\IncomingRequest;

class FormInput
{
    /**
     * Read the given POST fields as trimmed strings.
     */
    public static function trimmed(IncomingRequest $request, array $fields): array
    {
        $values = [];
        foreach ($fields as $field) {
            $values[$field] = trim((string) $request->getPost($field));
        }

        return $values;
    }

    /**
     * True when any of the required fields is empty.
     */
    public static function missing(array $values, array $required): bool
    {
        foreach ($required as $field) {
            if (($values[$field] ?? '') === '') {
                return true;
            }
        }

        return false;
    }
}
