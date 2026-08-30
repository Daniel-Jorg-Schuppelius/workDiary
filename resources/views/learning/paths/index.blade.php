{{--
  Created on   : Sat Aug 29 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Lernpfade (Feature 149, MVP-745): Reihenfolge von Kursen mit Fristen —
  die Einarbeitung, nicht ein zweiter Pflichtkatalog.
--}}
@extends('layouts.app')
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')
@section('title', __('learning.title.paths'))
@section('nav-title', __('learning.title.paths'))
@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="__('learning.subtitle.paths')">
            <x-slot:actions>
                {{-- Läuft wiederholt: doppelte Zuweisung ist kein Fehler. --}}
                <form method="POST" action="{{ route('learning.paths.assign-by-role') }}">
                    @csrf
                    <x-icon-btn icon="auto_awesome" tone="ghost" size="sm" type="submit"
                                show-label>{{ __('learning.action.assign_by_role') }}</x-icon-btn>
                </form>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <x-card>
        <h3 class="mb-3 text-sm font-semibold">{{ __('learning.action.create_path') }}</h3>
        <form method="POST" action="{{ route('learning.paths.store') }}">
            @csrf
            <x-form-group :legend="__('learning.field.path')" icon="route" tone="primary" cols="2">
                <x-input-field name="code" :label="__('learning.field.code')" required maxlength="60" :value="old('code')" />
                <x-input-field name="title" :label="__('learning.field.title')" required minlength="2" maxlength="180" :value="old('title')" />
                <x-input-field name="target_role" :label="__('learning.field.target_role')" maxlength="60"
                               :hint="__('learning.help.target_role')" :value="old('target_role')" />
                <x-input-field name="duration_days" type="number" min="1" max="3650"
                               :label="__('learning.field.duration_days')" :value="old('duration_days')" />
            </x-form-group>
            <div class="mt-3 flex justify-end">
                <x-icon-btn icon="add" tone="primary" size="sm" type="submit" show-label>{{ __('learning.action.create_path') }}</x-icon-btn>
            </div>
        </form>
    </x-card>

    <x-table scroll="flex" hover :caption="__('learning.title.paths')">
        <x-slot:head>
            <tr>
                <th>{{ __('learning.field.code') }}</th>
                <th>{{ __('learning.field.title') }}</th>
                <th>{{ __('learning.field.target_role') }}</th>
                <th class="text-right">{{ __('learning.field.stations') }}</th>
            </tr>
        </x-slot:head>

        @forelse ($paths as $path)
            <tr>
                <td class="font-mono text-xs">{{ $path->code }}</td>
                <td><a class="link" href="{{ route('learning.paths.show', $path->sqid) }}">{{ $path->title }}</a></td>
                <td>{{ $path->target_role ?? '—' }}</td>
                <td class="text-right">{{ $path->items_count }}</td>
            </tr>
        @empty
            <x-table.empty icon="route" :message="__('learning.empty.paths')" colspan="4" />
        @endforelse
    </x-table>

    <x-pagination :paginator="$paths" standing />
</x-page-shell>
@endsection
