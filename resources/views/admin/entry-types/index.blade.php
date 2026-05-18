@extends('layouts.app')

@section('title', __('Eintragstypen'))
@section('nav-title', __('Eintragstypen'))

@section('content')
<x-page-shell gap="6">
    <x-slot:toolbar>
        <x-page-toolbar>
            <x-slot:actions>
                <a href="{{ route('admin.entry-types.create') }}" data-entry-modal-trigger class="btn btn-primary btn-sm">
                    + {{ __('Eintragstyp anlegen') }}
                </a>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <x-table>
        <thead>
            <tr>
                <th class="w-12"><x-sort-th column="sort" :route="route('admin.entry-types.index')" :sort="$sort ?? null" :dir="$dir ?? 'asc'" default="sort">#</x-sort-th></th>
                <th><x-sort-th column="label" :route="route('admin.entry-types.index')" :sort="$sort ?? null" :dir="$dir ?? 'asc'">{{ __('Bezeichnung') }}</x-sort-th></th>
                <th><x-sort-th column="slug" :route="route('admin.entry-types.index')" :sort="$sort ?? null" :dir="$dir ?? 'asc'">{{ __('Slug') }}</x-sort-th></th>
                <th>{{ __('Flags') }}</th>
                <th class="text-center"><x-sort-th column="entries" :route="route('admin.entry-types.index')" :sort="$sort ?? null" :dir="$dir ?? 'asc'">{{ __('Einträge') }}</x-sort-th></th>
                <th class="text-center"><x-sort-th column="is_active" :route="route('admin.entry-types.index')" :sort="$sort ?? null" :dir="$dir ?? 'asc'">{{ __('Aktiv') }}</x-sort-th></th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($entryTypes as $type)
                <tr>
                    <td class="text-base-content/60">{{ $type->sort }}</td>
                    <td class="font-medium">
                        <span class="inline-flex items-center gap-2">
                            <x-icon :name="$type->icon ?: 'task_alt'" class="text-{{ $type->color ?: 'primary' }}" />
                            {{ $type->label }}
                        </span>
                        @if ($type->description)
                            <div class="text-xs text-base-content/60">{{ $type->description }}</div>
                        @endif
                    </td>
                    <td class="font-mono text-sm text-base-content/60">{{ $type->slug }}</td>
                    <td>
                        <div class="flex flex-wrap gap-1">
                            @if ($type->requires_customer) <span class="badge badge-xs badge-info">{{ __('Kunde') }}</span> @endif
                            @if ($type->requires_schedule) <span class="badge badge-xs badge-info">{{ __('Termin') }}</span> @endif
                            @if ($type->requires_address) <span class="badge badge-xs badge-info">{{ __('Adresse') }}</span> @endif
                            @if ($type->requires_tour) <span class="badge badge-xs badge-info">{{ __('Tour') }}</span> @endif
                            @if ($type->allow_priority) <span class="badge badge-xs badge-ghost">{{ __('Priorität') }}</span> @endif
                            @if ($type->allow_tour && ! $type->requires_tour) <span class="badge badge-xs badge-ghost">{{ __('Tour opt.') }}</span> @endif
                        </div>
                    </td>
                    <td class="text-center">{{ $type->diary_entries_count ?? 0 }}</td>
                    <td class="text-center">
                        @if ($type->is_active)
                            <span class="badge badge-success badge-sm">{{ __('Ja') }}</span>
                        @else
                            <span class="badge badge-error badge-sm">{{ __('Nein') }}</span>
                        @endif
                    </td>
                    <td class="text-right">
                        <div class="flex justify-end gap-2">
                            <a href="{{ route('admin.entry-types.edit', $type) }}" data-entry-modal-trigger class="btn btn-ghost btn-xs">{{ __('Bearbeiten') }}</a>
                            <form method="POST" action="{{ route('admin.entry-types.destroy', $type) }}"
                                  data-confirm-dialog
                                  data-confirm-message="{{ __('Eintragstyp wirklich löschen?') }}"
                                  data-confirm-label="{{ __('Löschen') }}">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-ghost btn-xs text-error">{{ __('Löschen') }}</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="p-0"><x-empty-state :compact="true" :title="__('Keine Eintragstypen vorhanden')" /></td>
                </tr>
            @endforelse
        </tbody>
    </x-table>

    <div>{{ $entryTypes->links() }}</div>
</x-page-shell>
@endsection
