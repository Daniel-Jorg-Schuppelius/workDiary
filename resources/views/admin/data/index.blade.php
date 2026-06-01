@extends('layouts.app')

@section('title', __('Datentransfer'))
@section('nav-title', __('Datentransfer'))

@section('content')
<x-index-page :subtitle="__('Daten von :org als CSV oder Excel exportieren — gleiche Spalten wie der Import (Round-Trip).', ['org' => $organization->name])">
    <x-slot:actions>
        <x-icon-btn icon="upload_file" tone="primary" size="sm"
                    :href="route('admin.imports.create')"
                    show-label>{{ __('Import starten') }}</x-icon-btn>
    </x-slot:actions>

    @include('admin.data._tabs')

    @error('export')
        <div class="alert alert-error mt-4">
            <span class="material-symbols-outlined" aria-hidden="true">error</span>
            <span>{{ $message }}</span>
        </div>
    @enderror

    <form method="POST" action="{{ route('admin.data.export') }}" class="card bg-base-100 shadow-sm mt-4">
        @csrf
        <div class="card-body space-y-4">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <label class="form-control">
                    <span class="label-text">{{ __('Entität') }}</span>
                    <select name="entity" class="select select-sm select-bordered w-full">
                        @foreach ($entities as $e)
                            <option value="{{ $e->value }}" @selected($entity->value === $e->value)>{{ $e->label() }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="form-control">
                    <span class="label-text">{{ __('Format') }}</span>
                    <select name="format" class="select select-sm select-bordered w-full">
                        @foreach ($formats as $f)
                            <option value="{{ $f->value }}">{{ $f->label() }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="form-control">
                    <span class="label-text">{{ __('Status') }}</span>
                    <input type="text" name="status" class="input input-sm input-bordered w-full"
                           placeholder="{{ __('z. B. active, archived') }}" />
                </label>

                <label class="form-control">
                    <span class="label-text">{{ __('Suche') }}</span>
                    <input type="text" name="q" class="input input-sm input-bordered w-full"
                           placeholder="{{ __('Name, Nummer …') }}" />
                </label>

                <label class="form-control">
                    <span class="label-text">{{ __('Von (Datum)') }}</span>
                    <input type="date" name="from" class="input input-sm input-bordered w-full" />
                </label>

                <label class="form-control">
                    <span class="label-text">{{ __('Bis (Datum)') }}</span>
                    <input type="date" name="to" class="input input-sm input-bordered w-full" />
                </label>
            </div>

            <div class="text-sm text-base-content/70">
                {{ __('Nicht zutreffende Filter werden je Entität ignoriert. Zeitraum-Filter wirken auf Schichtpläne und Touren.') }}
            </div>

            <div class="card-actions justify-end">
                <button type="submit" class="btn btn-primary btn-sm">
                    <span class="material-symbols-outlined" aria-hidden="true">download</span>
                    {{ __('Export erstellen') }}
                </button>
            </div>
        </div>
    </form>

    <h3 class="mt-6 mb-2 text-sm font-semibold text-base-content/70">{{ __('Letzte Exporte') }}</h3>

    @if ($runs->isEmpty())
        <x-empty-state framed
            icon='<span class="material-symbols-outlined" aria-hidden="true">download</span>'
            :title="__('Noch keine Exporte vorhanden')"
            :message="__('Erstelle oben einen Export, um ihn hier wiederzufinden.')" />
    @else
        <x-table>
            <x-slot:head>
                <tr>
                    <th>{{ __('Entität') }}</th>
                    <th>{{ __('Format') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th class="text-right">{{ __('Zeilen') }}</th>
                    <th>{{ __('Erstellt') }}</th>
                    <th></th>
                </tr>
            </x-slot:head>
            @foreach ($runs as $run)
                <tr>
                    <td>{{ $run->entity->label() }}</td>
                    <td><span class="badge badge-sm badge-ghost uppercase">{{ $run->format->value }}</span></td>
                    <td><span class="badge badge-sm">{{ $run->state->label() }}</span></td>
                    <td class="text-right tabular-nums">{{ $run->rows_total }}</td>
                    <td class="text-sm">{{ optional($run->created_at)->format('Y-m-d H:i') }}</td>
                    <td class="flex justify-end gap-1">
                        @if ($run->state->canDownload())
                            <x-icon-btn icon="download" size="sm" :href="route('admin.data.download', $run)" />
                        @endif
                        <form method="POST" action="{{ route('admin.data.destroy', $run) }}"
                              onsubmit="return confirm('{{ __('Export wirklich löschen?') }}');">
                            @csrf @method('DELETE')
                            <x-icon-btn icon="delete" size="sm" tone="ghost" type="submit" />
                        </form>
                    </td>
                </tr>
            @endforeach
        </x-table>
    @endif
</x-index-page>
@endsection
