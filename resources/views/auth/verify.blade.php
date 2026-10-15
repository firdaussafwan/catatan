@extends('layouts.guest')
@section('title', 'Enter your code')
@section('subtitle', 'Type the 6-digit code we emailed you.')

@section('content')
    <form method="POST" action="{{ route('register.verify.store') }}" class="form">
        @csrf
        <label>Email
            <input type="email" name="email" value="{{ $email }}" required autocomplete="username" inputmode="email">
        </label>
        <label>6-digit code
            <input type="text" name="code" class="code-input" required autofocus inputmode="numeric" autocomplete="one-time-code"
                   pattern="[0-9]{6}" maxlength="6" placeholder="123456">
        </label>
        <button class="btn primary wide" type="submit">Confirm and create my account</button>
    </form>

    <form method="POST" action="{{ route('register.resend') }}" class="resend-form">
        @csrf
        <input type="hidden" name="email" value="{{ $email }}">
        <button class="btn wide" type="submit">Send me a new code</button>
    </form>
    <p class="auth-links">Wrong email? <a href="{{ route('register') }}">Start again</a> · <a href="{{ route('login') }}">Sign in</a></p>
@endsection
