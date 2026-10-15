@props(['user' => null, 'size' => null])
@php $u = $user ?? auth()->user(); @endphp
@if ($u->avatar_path)
    <img class="avatar {{ $size }}" src="{{ $u->avatarUrl() }}" alt="" width="42" height="42" loading="lazy">
@else
    <div class="avatar {{ $size }}" aria-hidden="true">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($u->name, 0, 1)) }}</div>
@endif
