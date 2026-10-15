@extends('layouts.guest')
@section('title', 'Create an account')
@section('subtitle', 'We will email you a 6-digit code to confirm your address.')

@section('content')
    <form method="POST" action="{{ route('register.store') }}" class="form">
        @csrf
        <label>Name
            <input type="text" name="name" value="{{ old('name') }}" required autofocus autocomplete="name" maxlength="80">
        </label>
        <label>Email
            <input type="email" name="email" value="{{ old('email') }}" required autocomplete="username" inputmode="email">
        </label>
        <label>Password <small>(min 8 characters)</small>
            <input type="password" name="password" required autocomplete="new-password">
        </label>
        <label>Confirm password
            <input type="password" name="password_confirmation" required autocomplete="new-password">
        </label>
        <button class="btn primary wide" type="submit">Send me the code</button>
    </form>
    <p class="auth-links">Already have an account? <a href="{{ route('login') }}">Sign in</a></p>
@endsection
