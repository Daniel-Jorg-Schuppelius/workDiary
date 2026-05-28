@extends('layouts.app')

@section('title', __('Import #:id', ['id' => $run->id]))
@section('nav-title', __('Import #:id', ['id' => $run->id]))

@section('content')
<x-index-page :subtitle="__(':entity — :file', ['entity' => $run->entity->label(), 'file' => $run->input_filename])">
    <x-slot:actions>
        <x-icon-btn icon="arrow_back" size="sm" :href="route('admin.imports.index')" show-label>
            {{ __('Zurück') }}
        </x-icon-btn>

        @if ($run->state === \App\Enums\Import\ImportRunState::AwaitingApproval)
            <form method="POST" action="{{ route('admin.imports.confirm', $run) }}" class="inline">
                @csrf
                <button type="submit" class="btn btn-primary btn-sm">
                    <span class="material-symbols-outlined" aria-hidden="true">play_arrow</span>
                    {{ __('Import bestätigen & starten') }}
                </button>
            </form>
        @endif

        @if (in_array($run->state, [\App\Enums\Import\ImportRunState::AwaitingApproval, \App\Enums\Import\ImportRunState::Failed], true))
            <form method="POST" action="{{ route('admin.imports.destroy', $run) }}" class="inline"
                  data-confirm-dialog
                  data-confirm-message="{{ __('Import wirklich verwerfen?') }}"
                  data-confirm-icon="delete"
                  data-confirm-tone="error"
                  data-confirm-label="{{ __('Verwerfen') }}">
                @csrf @method('DELETE')
                <button type="submit" class="btn btn-error btn-sm btn-outline">
                    <span class="material-symbols-outlined" aria-hidden="true">delete</span>
                    {{ __('Verwerfen') }}
                </button>
            </form>
        @endif

        @if ($errors->total() > 0)
            <x-icon-btn icon="download" size="sm" :href="route('admin.imports.errors', $run)" show-label>
                {{ __('Fehler-CSV') }}
            </x-icon-btn>
        @endif
    </x-slot:actions>

    {{-- Status-Kacheln --}}
    <div class="grid grid-cols-2 md:grid-cols-6 gap-3">
        <div class="card bg-base-100 shadow-sm"><div class="card-body p-3">
            <div class="text-xs text-base-content/60">{{ __('Status') }}</div>
            <div class="font-semibold">{{ $run->state->label() }}</div>
        </div></div>
        <div class="card bg-base-100 shadow-sm"><div class="card-body p-3">
            <div class="text-xs text-base-content/60">{{ __('Zeilen') }}</div>
            <div class="font-semibold tabular-nums">{{ $run->rows_total }}</div>
        </div></div>
        <div class="card bg-base-100 shadow-sm"><div class="card-body p-3">
            <div class="text-xs text-base-content/60">{{ __('Neu') }}</div>
            <div class="font-semibold tabular-nums text-success">{{ $run->rows_created }}</div>
        </div></div>
        <div class="card bg-base-100 shadow-sm"><div class="card-body p-3">
            <div class="text-xs text-base-content/60">{{ __('Aktualisiert') }}</div>
            <div class="font-semibold tabular-nums">{{ $run->rows_updated }}</div>
        </div></div>
        <div class="card bg-base-100 shadow-sm"><div class="card-body p-3">
            <div class="text-xs text-base-content/60">{{ __('Übersprungen') }}</div>
            <div class="font-semibold tabular-nums">{{ $run->rows_skipped }}</div>
        </div></div>
        <div class="card bg-base-100 shadow-sm"><div class="card-body p-3">
            <div class="text-xs text-base-content/60">{{ __('Fehler') }}</div>
            <div class="font-semibold tabular-nums {{ $run->rows_failed > 0 ? 'text-error' : '' }}">{{ $run->rows_failed }}</div>
        </div></div>
    </div>

    {{-- Vorschau --}}
    @if (! empty($run->preview))
        <div class="card bg-base-100 shadow-sm">
            <div class="card-body">
                <h3 class="font-semibold">{{ __('Vorschau (erste :n Zeilen)', ['n' => count($run->preview)]) }}</h3>
                <div class="overflow-x-auto">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>#</th>
                                @foreach (($run->preview[0]['data'] ?? []) as $col => $_)
                                    <th>{{ $col }}</th>
                                @endforeach
                                <th>{{ __('Hinweise') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($run->preview as $entry)
                                <tr class="{{ ! empty($entry['issues']) ? 'bg-error/10' : '' }}">
                                    <td class="font-mono text-xs">{{ $entry['row'] }}</td>
                                    @foreach ($entry['data'] as $col => $val)
                                        <td class="text-xs">{{ \Illuminate\Support\Str::limit((string) $val, 40) }}</td>
                                    @endforeach
                                    <td class="text-xs">
                                        @foreach ($entry['issues'] ?? [] as $iss)
                                            <div><span class="badge badge-xs badge-error">{{ $iss['code'] }}</span> {{ $iss['field'] }}: {{ $iss['message'] }}</div>
                                        @endforeach
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    {{-- Fehlerliste --}}
    @if ($errors->total() > 0)
        <div class="card bg-base-100 shadow-sm">
            <div class="card-body">
                <h3 class="font-semibold">{{ __('Fehler (:n)', ['n' => $errors->total()]) }}</h3>
                <x-table>
                    <x-slot:head>
                        <tr>
                            <th>{{ __('Zeile') }}</th>
                            <th>{{ __('Feld') }}</th>
                            <th>{{ __('Code') }}</th>
                            <th>{{ __('Meldung') }}</th>
                        </tr>
                    </x-slot:head>
                    @foreach ($errors as $err)
                        <tr>
                            <td class="font-mono text-xs">{{ $err->row_number }}</td>
                            <td class="font-mono text-xs">{{ $err->field }}</td>
                            <td><span class="badge badge-xs badge-error">{{ $err->code->value }}</span></td>
                            <td class="text-sm">{{ $err->message }}</td>
                        </tr>
                    @endforeach
                </x-table>
                <div class="mt-3">{{ $errors->links() }}</div>
            </div>
        </div>
    @endif
</x-index-page>
@endsection
