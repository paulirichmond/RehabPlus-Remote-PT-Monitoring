@extends('layouts.app')

@section('title', $patient->name)

@section('content')
    <div class="topbar">
        <div>
            <a href="{{ route('patients.index') }}">← Back to patients</a>
        </div>
    </div>

    <div class="panel">
        <h1>{{ $patient->name }}</h1>
        <div class="badge">{{ $patient->condition }}</div>
        <div class="grid-two" style="margin-top: 20px;">
            <div>
                <h3>Medical Summary</h3>
                <p>{{ $patient->medical_summary ?? 'No summary recorded yet.' }}</p>
            </div>
            <div>
                <h3>Therapy Plan</h3>
                <p>{{ $patient->therapy_plan ?? 'No therapy plan recorded yet.' }}</p>
            </div>
        </div>
    </div>

    <div class="stats" style="margin-top: 24px;">
        <div class="stat"><strong>Compliance</strong><div style="font-size: 28px; margin-top: 8px;">{{ $compliance }}%</div></div>
        <div class="stat"><strong>Avg Pain</strong><div style="font-size: 28px; margin-top: 8px;">{{ $avgPain }}</div></div>
        <div class="stat"><strong>Records</strong><div style="font-size: 28px; margin-top: 8px;">{{ $records->count() }}</div></div>
    </div>

    <div class="panel">
        <div class="toolbar">
            <h3>Exercise Records</h3>
            <a href="{{ route('exercises.create', $patient->id) }}" class="btn">Add Record</a>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Exercise</th>
                        <th>Sets</th>
                        <th>Pain</th>
                        <th>Date</th>
                        <th>Notes</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($records as $record)
                        <tr>
                            <td>{{ $record->exercise_name }}</td>
                            <td>{{ $record->sets_completed }}/{{ $record->sets_prescribed }}</td>
                            <td>{{ $record->pain_level }}</td>
                            <td>{{ \Carbon\Carbon::parse($record->recorded_at)->format('M d, Y') }}</td>
                            <td>{{ Str::limit($record->notes ?? 'No notes', 40) }}</td>
                            <td>
                                <div class="nav">
                                    <a href="{{ route('exercises.edit', [$patient->id, $record->id]) }}">Edit</a>
                                    <form method="POST" action="{{ route('exercises.delete', [$patient->id, $record->id]) }}" onsubmit="return confirm('Delete this exercise record?');" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">No exercise records yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
