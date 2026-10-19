@extends('layouts.admin')

@section('title', 'Messages')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <p class="page-title mb-1">Messages</p>
            <p class="page-subtitle mb-0">
                @if ($thread === 'therapist')
                    Conversations with your patients from the RehabPlus app.
                @else
                    Clinic inbox: appointment and home-visit requests and app questions from patients.
                @endif
            </p>
        </div>
        <a href="{{ route('messages.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-clockwise me-1"></i>Refresh</a>
    </div>

    @if ($error)
        <div class="alert alert-danger">{{ $error }}</div>
    @else
        <div class="card shadow-sm border-0">
            <div class="list-group list-group-flush">
                @forelse ($conversations as $c)
                    <a href="{{ route('messages.show', $c['patientUid']) }}"
                       class="list-group-item list-group-item-action d-flex align-items-center gap-3 py-3 {{ $c['unread'] ? 'fw-semibold' : '' }}">
                        <div class="user-avatar flex-shrink-0">{{ strtoupper(mb_substr($c['patientName'], 0, 1)) }}</div>
                        <div class="flex-grow-1 min-w-0">
                            <div class="d-flex justify-content-between">
                                <span>{{ $c['patientName'] }}</span>
                                <small class="text-muted">{{ $c['lastMessageAt']?->setTimezone(config('app.timezone'))->diffForHumans() }}</small>
                            </div>
                            <div class="text-muted small text-truncate">
                                @if ($c['lastMessage'])
                                    {{ $c['lastSenderRole'] === 'staff' ? 'You: ' : '' }}{{ $c['lastMessage'] }}
                                @else
                                    No messages yet{{ $c['condition'] ? ' · '.$c['condition'] : '' }}
                                @endif
                            </div>
                        </div>
                        @if ($c['unread'])
                            <span class="badge rounded-pill bg-danger">{{ $c['unread'] }}</span>
                        @endif
                    </a>
                @empty
                    <div class="text-center text-muted py-5">
                        {{ $thread === 'therapist' ? 'No patients are assigned to you yet.' : 'No patients yet.' }}
                    </div>
                @endforelse
            </div>
        </div>
    @endif
@endsection
