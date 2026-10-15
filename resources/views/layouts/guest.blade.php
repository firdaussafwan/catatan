@extends('layouts.base')

@section('body-class', 'guest')

@section('body')
    <main class="auth-card">
        <div class="brand brand-lg">
            <img src="{{ asset('icon.svg') }}" alt="" width="40" height="40">
            <span>{{ config('app.name') }}</span>
        </div>
        <p class="auth-sub">@yield('subtitle', 'Your private notes workspace.')</p>

        @if (session('status'))
            <div class="alert ok">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert error">
                @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            </div>
        @endif

        @yield('content')
    </main>
@endsection
