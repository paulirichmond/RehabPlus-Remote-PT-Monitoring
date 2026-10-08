@extends('layouts.app')

@section('title', 'Patient Directory')

@section('content')
    <div class="topbar">
        <h1>Patient Directory</h1>
        <div class="nav">
            <a href="{{ route('dashboard') }}">Dashboard</a>
            <a href="{{ route('appointments.index') }}">Appointments</a>
            <a href="{{ route('users.index') }}">Users</a>
            <a class="btn" href="{{ route('patients.create') }}">Add Patient</a>
        </div>
    </div>

    <div class="panel">
        <div class="toolbar">
            <div class="nav">
                <form method="GET" action="{{ route('patients.index') }}" class="inline">
                    <input type="text" name="search" value="{{ $search }}" placeholder="Search patient or condition" style="padding:10px 12px; border:1px solid #d1d5db; border-radius:8px;">
                    <select name="condition" style="padding:10px 12px; border:1px solid #d1d5db; border-radius:8px;">
                        <option value="">All conditions</option>
                        @foreach($conditions as $condition)
                            <option value="{{ $condition }}" {{ $selectedCondition === $condition ? 'selected' : '' }}>{{ $condition }}</option>
                        @endforeach
                    </select>
                    <button type="submit">Filter</button>
                </form>
            </div>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Patient</th>
                        <th>Condition</th>
                        <th>Therapy Plan</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($data as $patient)
                        <tr>
                            <td><strong>{{ $patient->name }}</strong></td>
                            <td><span class="badge">{{ $patient->condition }}</span></td>
                            <td>{{ Str::limit($patient->therapy_plan ?? 'No therapy plan yet', 60) }}</td>
                            <td>
                                <div class="nav">
                                    <a class="button button-secondary" href="{{ route('patients.show', $patient->id) }}">View</a>
                                    <a class="button button-secondary" href="{{ route('patients.edit', $patient->id) }}">Edit</a>
                                    <form method="POST" action="{{ route('patients.delete', $patient->id) }}" onsubmit="return confirm('Delete this patient?');" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4">No patients match the current search.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
