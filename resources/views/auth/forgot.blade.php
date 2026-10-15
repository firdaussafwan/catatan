@extends('layouts.guest')
@section('title', 'Forgot password')
@section('subtitle', 'Enter your email and we will send you a reset link.')

@section('content')
    <form method="POST" action="{{ route('password.email') }}" class="form">
        @csrf
        <label>Email
            <input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" inputmode="email">
        </label>
        <button class="btn primary wide" type="submit">Send reset link</button>
    </form>
    <p class="auth-links"><a href="{{ route('login') }}">Back to sign in</a></p>
@endsection
