{{--
  Created on   : Tue Jul 14 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : cleanup.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')
@section('title', __('backup_targets.cleanup_page.title') . ' — ' . config('app.name', 'WorkDiary'))
@section('nav-title', __('backup_targets.cleanup_page.title'))

@section('content')
<x-index-page :subtitle="__('backup_targets.cleanup_page.description')">
    <x-slot:actions>
        <a href="{{ route('admin.backup-targets.index') }}" class="btn btn-ghost btn-sm">{{ __('backup_targets.cleanup_page.back') }}</a>
    </x-slot:actions>

    @if ($error !== null)
        <div role="alert" class="alert alert-warning">
            <x-icon name="warning" />
            <span>{{ __('backup_targets.cleanup_page.error', ['message' => $error]) }}</span>
        </div>
    @elseif (count($objects) === 0)
        <x-empty-state framed icon='<span class="material-symbols-outlined" aria-hidden="true">folder_off</span>'
                       :title="__('backup_targets.cleanup_page.empty')" />
    @else
        <div class="card bg-base-100 shadow-sm">
            <div class="card-body gap-3">
                <h3 class="card-title text-base">{{ $connection->provider->label() }} — {{ $connection->name }}</h3>
                <ul class="divide-y divide-base-200">
                    @foreach ($objects as $object)
                        <li class="flex flex-wrap items-center gap-2 py-2 text-sm">
                            <span class="font-mono">{{ $object->name }}</span>
                            <span class="text-base-content/60">{{ \Illuminate\Support\Number::fileSize($object->size) }}</span>
                            @if (collect($knownPrefixes)->keys()->first(fn ($prefix) => str_ends_with((string) $prefix, '/' . $object->name)) !== null)
                                <span class="badge badge-ghost badge-sm">{{ __('backup_targets.cleanup_page.known') }}</span>
                            @else
                                <span class="badge badge-warning badge-sm">{{ __('backup_targets.cleanup_page.orphan') }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif
</x-index-page>
@endsection
