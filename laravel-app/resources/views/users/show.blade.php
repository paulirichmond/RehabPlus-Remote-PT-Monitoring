@extends('layouts.app')

@section('title', $user->name)

@section('content')
    <div class="topbar">
        <div>
            <a href="{{ route('users.index') }}">← Back to users</a>
        </div>
    </div>

    <div class="panel" style="max-width:700px; margin:0 auto;">
        <h1>{{ $user->name }}</h1>
        <p><strong>Email:</strong> {{ $user->email }}</p>
        <p><strong>Role:</strong> <span class="badge">{{ $user->role }}</span></p>
        <p><strong>Status:</strong> {{ $user->is_active ? 'Active' : 'Inactive' }}</p>
    </div>
@endsection
