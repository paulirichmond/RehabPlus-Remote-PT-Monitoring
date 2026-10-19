<?php

namespace App\Services\TherapyNote;

class TherapyNoteService
{
    public function overview(): array
    {
        $notes = session()->get('therapy_notes') ?? [];

        return [
            'notes' => $notes,
            'recentCount' => count($notes),
            'todayNotes' => 0,
        ];
    }

    public function add(array $input): void
    {
        $notes = session()->get('therapy_notes') ?? [];
        $notes[] = [
            'patient' => $input['patient'],
            'therapist' => $input['therapist'],
            'topic' => $input['topic'],
            'note' => $input['note'],
            'date' => $input['date'],
        ];
        session()->set('therapy_notes', $notes);
    }
}
