@extends('layouts.app')
@section('title', __('Gleitzeit – Team'))
@section('nav-title', __('Gleitzeit – Team'))
@section('content')
@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\User> $users */
    /** @var \App\Models\User $user */
    /** @var \App\Services\Calendar\WeekViewService $service */
@endphp
<x-page-shell>

    @if ($users->isNotEmpty())
        <div role="tablist" class="tabs tabs-box">
            @foreach ($users as $u)
                @php
                    $hue = $service->userHue((int) $u->id);
                    $isActive = (int) $user->id === (int) $u->id;
                    $color = "hsl({$hue} 70% 45%)";
                    $soft = "hsl({$hue} 70% 92%)";
                @endphp
                <a role="tab"
                   href="{{ route('flex.admin', ['user' => $u->id]) }}"
                   class="tab gap-2 {{ $isActive ? 'tab-active' : '' }}"
                   style="--tab-bg: {{ $soft }}; --tab-border-color: {{ $color }}; {{ $isActive ? 'color: ' . $color . ';' : '' }}">
                    <span class="inline-block h-2.5 w-2.5 rounded-full" style="background: {{ $color }};"></span>
                    <span>{{ $u->name }}</span>
                </a>
            @endforeach
        </div>
    @endif

    @include('flex.index', ['isAdmin' => true])
</x-page-shell>
@endsection
