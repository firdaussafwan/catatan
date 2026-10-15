@extends('layouts.guest')
@section('title', 'Create your account')
@section('subtitle', 'First-time setup. This page closes itself once your account exists.')

@section('content')
    <form method="POST" action="{{ route('setup.store') }}" class="form">
        @csrf
        <label>Name
            <input type="text" name="name" value="{{ old('name') }}" required autofocus autocomplete="name">
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
        <button class="btn primary wide" type="submit">Create account</button>
    </form>
    <p class="auth-links"><a href="{{ route('login') }}">Back to sign in</a></p>
@endsection
