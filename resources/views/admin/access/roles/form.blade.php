@extends('layouts.app')

@php($isEdit = (bool) ($role->id ?? false))

@section('title', $isEdit ? __('access.title.role_edit', ['name' => $role->name]) : __('access.title.role_new'))
@section('nav-title', $isEdit ? __('access.title.role_edit', ['name' => $role->name]) : __('access.title.role_new'))

@section('content')
<x-page-shell gap="6">
    <form method="POST"
          action="{{ $isEdit ? route('admin.access.roles.update', $role) : route('admin.access.roles.store') }}"
          class="space-y-6">
        @csrf
        @if ($isEdit) @method('PUT') @endif

        <div class="card bg-base-100 shadow-sm">
            <div class="card-body space-y-4">
                <x-form-group :label="__('access.field.role_name')" name="name" required>
                    <input type="text"
                           name="name"
                           value="{{ old('name', $role->name) }}"
                           class="input input-bordered w-full font-mono"
                           pattern="[a-z0-9._-]+"
                           maxlength="80"
                           @if ($isEdit) readonly @else required @endif />
                    <p class="text-xs text-base-content/60 mt-1">{{ __('access.help.role_name') }}</p>
                </x-form-group>
            </div>
        </div>

        <div class="card bg-base-100 shadow-sm">
            <div class="card-body space-y-4">
                <h3 class="text-lg font-semibold">{{ __('access.title.permissions') }}</h3>
                <p class="text-sm text-base-content/60">{{ __('access.help.role_permissions') }}</p>

                @include('admin.access._permission_matrix', [
                    'grouped' => $permissions,
                    'assigned' => $assigned,
                    'name' => 'permissions[]',
                ])
            </div>
        </div>

        <div class="flex justify-end gap-2">
            <a href="{{ route('admin.access.roles.index') }}" class="btn btn-ghost btn-sm">{{ __('access.action.cancel') }}</a>
            <button type="submit" class="btn btn-primary btn-sm">
                {{ $isEdit ? __('access.action.save') : __('access.action.create') }}
            </button>
        </div>
    </form>
</x-page-shell>
@endsection
