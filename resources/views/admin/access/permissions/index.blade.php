@extends('layouts.app')

@section('title', __('access.title.permissions'))
@section('nav-title', __('access.title.permissions'))

@section('content')
<x-index-page :subtitle="__('access.hint.permissions_readonly')">

    @foreach ($grouped as $groupKey => $items)
        @php($groupEnum = \App\Enums\User\PermissionGroup::from($groupKey))
        <div class="card bg-base-100 shadow-sm">
            <div class="card-body">
                <h3 class="card-title flex items-center gap-2">
                    <x-icon :name="$groupEnum->icon()" />
                    {{ $groupEnum->label() }}
                    <x-status-badge tone="ghost" size="sm">{{ count($items) }}</x-status-badge>
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-1 mt-2">
                    @foreach ($items as $permission)
                        <div class="flex items-baseline gap-3 py-1 border-b border-base-200 last:border-0">
                            <span class="font-mono text-xs text-base-content/60 w-1/2">{{ $permission->value }}</span>
                            <span class="text-sm">{{ $permission->label() }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endforeach
</x-index-page>
@endsection
