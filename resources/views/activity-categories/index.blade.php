@extends('layouts.app')
@section('title', __('Tätigkeiten') . ' — WorkDiary')
@section('nav-title', __('Tätigkeiten'))

@php
    /** @var \Illuminate\Support\Collection $categories */
    $types = \App\Models\ActivityCategory::TYPES;
@endphp

@section('content')
    <x-page-shell gap="6">
        <x-slot:toolbar>
            <x-page-toolbar :title="__('Tätigkeiten')" :subtitle="__('Verwaltet die Kategorien für nicht-projektgebundene Arbeitszeit.')" />
        </x-slot:toolbar>

        <x-card :title="__('Neue Tätigkeit')">
            <form method="POST" action="{{ route('activity-categories.store') }}" class="grid gap-3 md:grid-cols-4">
                @csrf
                <label class="form-control md:col-span-1">
                    <span class="label-text text-xs">{{ __('Schlüssel') }}</span>
                    <input name="key" required class="input input-bordered input-sm" placeholder="team_meeting" pattern="[a-z0-9_\-]+">
                </label>
                <label class="form-control md:col-span-1">
                    <span class="label-text text-xs">{{ __('Bezeichnung') }}</span>
                    <input name="label" required class="input input-bordered input-sm">
                </label>
                <label class="form-control md:col-span-1">
                    <span class="label-text text-xs">{{ __('Typ') }}</span>
                    <select name="activity_type" class="select select-bordered select-sm">
                        @foreach ($types as $t)
                            <option value="{{ $t }}">{{ $t }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="form-control md:col-span-1">
                    <span class="label-text text-xs">{{ __('Reihenfolge') }}</span>
                    <input name="sort_order" type="number" min="0" max="999" value="100" class="input input-bordered input-sm">
                </label>
                <div class="flex items-center gap-4 md:col-span-3">
                    <label class="label cursor-pointer gap-2"><input type="checkbox" name="counts_as_work" value="1" checked class="checkbox checkbox-sm"><span class="label-text text-xs">{{ __('Zählt als Arbeit') }}</span></label>
                    <label class="label cursor-pointer gap-2"><input type="checkbox" name="billable_default" value="1" class="checkbox checkbox-sm"><span class="label-text text-xs">{{ __('Standardmäßig abrechenbar') }}</span></label>
                    <label class="label cursor-pointer gap-2"><input type="checkbox" name="active" value="1" checked class="checkbox checkbox-sm"><span class="label-text text-xs">{{ __('Aktiv') }}</span></label>
                </div>
                <x-icon-btn icon="add" tone="primary" size="sm" type="submit" class="md:col-span-1"
                            show-label>{{ __('Anlegen') }}</x-icon-btn>
            </form>
        </x-card>

        <x-card padding="p-0">
            <x-table table-sort="client" bare>
                <x-slot:head>
                    <tr>
                        <x-table.th sort type="string">{{ __('Schlüssel') }}</x-table.th>
                        <x-table.th sort type="string">{{ __('Bezeichnung') }}</x-table.th>
                        <x-table.th sort type="string">{{ __('Typ') }}</x-table.th>
                        <x-table.th sort type="string" align="center">{{ __('Arbeit') }}</x-table.th>
                        <x-table.th sort type="string" align="center">{{ __('Abrechenbar') }}</x-table.th>
                        <x-table.th sort type="string" align="center">{{ __('Aktiv') }}</x-table.th>
                        <th></th>
                    </tr>
                </x-slot:head>
                @forelse ($categories as $c)
                    <tr>
                        <td><code class="text-xs">{{ $c->key }}</code></td>
                        <td>{{ $c->label }}</td>
                        <td><span class="badge badge-sm badge-ghost">{{ $c->activity_type }}</span></td>
                        <td class="text-center">{!! $c->counts_as_work ? '✓' : '—' !!}</td>
                        <td class="text-center">{!! $c->billable_default ? '✓' : '—' !!}</td>
                        <td class="text-center">{!! $c->active ? '✓' : '—' !!}</td>
                        <td class="text-right">
                            <form method="POST" action="{{ route('activity-categories.destroy', $c) }}" class="inline"
                                  data-confirm-dialog
                                  data-confirm-message="{{ __('Wirklich löschen?') }}"
                                  data-confirm-label="{{ __('Löschen') }}">
                                @csrf @method('DELETE')
                                <x-icon-btn icon="delete" tone="error" type="submit" :label="__('Löschen')" />
                            </form>
                        </td>
                    </tr>
                @empty
                    <x-table.empty icon='<span class="material-symbols-outlined" aria-hidden="true">category</span>' :colspan="7" :title="__('Noch keine Tätigkeiten')" compact />
                @endforelse
            </x-table>
        </x-card>
    </x-page-shell>
@endsection
