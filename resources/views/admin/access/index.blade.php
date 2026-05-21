@extends('layouts.app')

@section('title', __('access.title.hub'))
@section('nav-title', __('access.title.hub'))

@section('content')
<x-page-shell gap="6">
    <x-slot:toolbar>
        <x-page-toolbar>
            <x-slot:title>
                <h1 class="text-xl font-semibold">{{ __('access.title.hub') }}</h1>
                @if ($organization)
                    <p class="text-sm text-base-content/60">
                        {{ __('access.subtitle.context', ['org' => $organization->name]) }}
                    </p>
                @endif
            </x-slot:title>
        </x-page-toolbar>
    </x-slot:toolbar>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <a href="{{ route('admin.access.roles.index') }}" class="card bg-base-100 shadow-sm hover:shadow-md transition-shadow">
            <div class="card-body">
                <div class="flex items-center gap-3">
                    <x-icon name="shield_person" class="text-primary text-3xl" />
                    <div>
                        <div class="text-2xl font-semibold">{{ $rolesCount }}</div>
                        <div class="text-sm text-base-content/60">{{ __('access.kpi.roles') }}</div>
                    </div>
                </div>
            </div>
        </a>
        <a href="{{ route('admin.access.groups.index') }}" class="card bg-base-100 shadow-sm hover:shadow-md transition-shadow">
            <div class="card-body">
                <div class="flex items-center gap-3">
                    <x-icon name="groups" class="text-secondary text-3xl" />
                    <div>
                        <div class="text-2xl font-semibold">{{ $groupsCount }}</div>
                        <div class="text-sm text-base-content/60">{{ __('access.kpi.groups') }}</div>
                    </div>
                </div>
            </div>
        </a>
        <a href="{{ route('admin.access.members.index') }}" class="card bg-base-100 shadow-sm hover:shadow-md transition-shadow">
            <div class="card-body">
                <div class="flex items-center gap-3">
                    <x-icon name="group" class="text-accent text-3xl" />
                    <div>
                        <div class="text-2xl font-semibold">{{ $membersCount }}</div>
                        <div class="text-sm text-base-content/60">{{ __('access.kpi.members') }}</div>
                    </div>
                </div>
            </div>
        </a>
        <a href="{{ route('admin.access.permissions.index') }}" class="card bg-base-100 shadow-sm hover:shadow-md transition-shadow">
            <div class="card-body">
                <div class="flex items-center gap-3">
                    <x-icon name="key" class="text-info text-3xl" />
                    <div>
                        <div class="text-2xl font-semibold">{{ $permissionsCount }}</div>
                        <div class="text-sm text-base-content/60">{{ __('access.kpi.permissions') }}</div>
                    </div>
                </div>
            </div>
        </a>
    </div>

    <div class="alert">
        <x-icon name="info" />
        <span>{{ __('access.hint.hub') }}</span>
    </div>
</x-page-shell>
@endsection
