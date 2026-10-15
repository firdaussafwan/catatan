@extends('layouts.guest')
@section('title', 'Sign in')

@section('content')
    <form method="POST" action="{{ route('login.attempt') }}" class="form">
        @csrf
        <label>Email
            <input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" inputmode="email">
        </label>
        <label>Password
            <input type="password" name="password" required autocomplete="current-password">
        </label>
        <label class="check">
            <input type="checkbox" name="remember" value="1" @checked(old('email') === null || old('remember'))>
            Keep me signed in on this device
        </label>
        <button class="btn primary wide" type="submit">Sign in</button>
    </form>

    <p class="auth-links">
        <a href="{{ route('password.request') }}">Forgot password?</a>
        @if ($canRegister)
            · <a href="{{ route('register') }}">Create an account</a>
        @endif
        @if ($canSetup)
            · <a href="{{ route('setup') }}">First time? Create your account</a>
        @endif
    </p>
@endsection
