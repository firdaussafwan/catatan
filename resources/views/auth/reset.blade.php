@extends('layouts.guest')
@section('title', 'Reset password')
@section('subtitle', 'Choose a new password.')

@section('content')
    <form method="POST" action="{{ route('password.update') }}" class="form">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <label>Email
            <input type="email" name="email" value="{{ old('email', $email) }}" required autocomplete="username" inputmode="email">
        </label>
        <label>New password <small>(min 8 characters)</small>
            <input type="password" name="password" required autofocus autocomplete="new-password">
        </label>
        <label>Confirm new password
            <input type="password" name="password_confirmation" required autocomplete="new-password">
        </label>
        <button class="btn primary wide" type="submit">Update password</button>
    </form>
@endsection
