@extends('layouts.app')

@section('title', 'User Accounts')

@section('content')
    <div class="topbar">
        <h1>Users</h1>
        <div class="nav">
            <a href="{{ route('dashboard') }}">Dashboard</a>
            <a class="btn" href="{{ route('users.create') }}">Create Account</a>
        </div>
    </div>

    <div class="panel">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($users as $user)
                        <tr>
                            <td>{{ $user->name }}</td>
                            <td>{{ $user->email }}</td>
                            <td><span class="badge">{{ $user->role }}</span></td>
                            <td>{{ $user->is_active ? 'Active' : 'Inactive' }}</td>
                            <td>
                                <div class="nav">
                                    <a href="{{ route('users.show', $user->id) }}">View</a>
                                    <a href="{{ route('users.edit', $user->id) }}">Edit</a>
                                    <form method="POST" action="{{ route('users.delete', $user->id) }}" onsubmit="return confirm('Delete this user?');" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
