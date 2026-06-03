@extends('layouts.app')
@section('title', __('Toggl-Workspace-Import (API)'))
@section('nav-title', __('Toggl-Workspace-Import (API)'))

@section('content')
<x-page-shell>
    <div class="space-y-4">
        <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
            <div class="mb-1 flex flex-wrap items-center justify-between gap-2">
                <h1 class="font-['Space_Grotesk'] text-lg font-semibold">{{ __('Workspaces direkt aus der Toggl-API importieren') }}</h1>
                <a href="{{ route('admin.toggl.index') }}" class="btn btn-ghost btn-sm">{{ __('Zurück zum Import') }}</a>
            </div>
            <p class="mb-4 text-sm text-base-content/60">
                {{ __('Importiert die Workspaces des hinterlegten API-Tokens ohne Datei-Export. Stammdaten kommen aus der Track-API (v9), die Zeiteinträge aller Benutzer aus der Reports-API (v3). Je gefundenem Workspace legst du fest, was passieren soll.') }}
            </p>

            @if (session('status'))
                <div class="alert alert-success mb-3 text-sm">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="alert alert-error mb-3 text-sm">{{ $errors->first() }}</div>
            @endif

            @unless ($tokenSet)
                <div class="alert alert-warning text-sm">{{ __('Kein Toggl API-Token hinterlegt. Bitte zuerst in den Plugin-Einstellungen hinterlegen.') }}</div>
            @elseif (empty($workspaces))
                <div class="alert alert-warning text-sm">{{ __('Keine Workspaces für dieses Token gefunden (oder die API ist nicht erreichbar).') }}</div>
            @endunless
        </div>

        {{-- Konfiguration je Workspace --}}
        @if ($tokenSet && ! empty($workspaces))
            <form method="POST" action="{{ route('admin.toggl.import-api.run') }}"
                  class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs space-y-4">
                @csrf

                <div>
                    <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('Was soll mit jedem Workspace passieren?') }}</h2>
                    <p class="text-sm text-base-content/60">
                        {{ __('„Eigener Workspace" = Toggl-Clients werden zu Kunden, Projekte zu Projekten. „Als ein Kunde" = der ganze Workspace wird zu genau einem Kunden; jeder interne Toggl-Client (Endkunde der Firma) wird als Fremdkunde angelegt, die Projekte verweisen darauf — so bleibt die Endkunden-Trennung erhalten. Bestehende Kunden/Fremdkunden/Projekte werden per Name wiederverwendet (keine Duplikate).') }}
                    </p>
                </div>

                <div class="space-y-2">
                    @foreach ($workspaces as $i => $ws)
                        <div class="grid items-end gap-2 rounded-box border border-base-300 p-3 md:grid-cols-12"
                             x-data="{ mode: 'skip' }">
                            <input type="hidden" name="workspace_ids[{{ $i }}]" value="{{ $ws['id'] }}">
                            <input type="hidden" name="workspace_names[{{ $i }}]" value="{{ $ws['name'] }}">
                            <div class="md:col-span-5">
                                <div class="font-semibold">{{ $ws['name'] }}</div>
                                <div class="text-xs text-base-content/60">
                                    {{ $ws['clients'] }} {{ __('Clients') }} · {{ $ws['projects'] }} {{ __('Projekte') }} · {{ $ws['users'] }} {{ __('Benutzer') }}
                                </div>
                            </div>
                            <label class="form-control md:col-span-3">
                                <span class="label-text text-xs">{{ __('Modus') }}</span>
                                <select name="modes[{{ $i }}]" x-model="mode" class="select select-sm select-bordered">
                                    <option value="skip">{{ __('Überspringen') }}</option>
                                    <option value="own">{{ __('Eigener Workspace') }}</option>
                                    <option value="customer">{{ __('Als ein Kunde (Endkunden als Fremdkunden)') }}</option>
                                </select>
                            </label>
                            <label class="form-control md:col-span-4" x-show="mode === 'customer'" x-cloak>
                                <span class="label-text text-xs">{{ __('Kundenname') }}</span>
                                <input type="text" name="customer_names[{{ $i }}]" value="{{ $ws['name'] }}"
                                       class="input input-sm input-bordered">
                            </label>
                        </div>
                    @endforeach
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <h2 class="mb-1 font-['Space_Grotesk'] text-base font-semibold">{{ __('Benutzer-Zuordnung') }}</h2>
                        <div class="flex flex-col gap-1">
                            <label class="flex items-center gap-2 text-sm">
                                <input type="radio" name="user_mode" value="per_email" class="radio radio-sm" checked>
                                {{ __('Pro E-Mail einen Benutzer anlegen/zuordnen (erhält „wer hat was gemacht")') }}
                            </label>
                            <label class="flex items-center gap-2 text-sm">
                                <input type="radio" name="user_mode" value="single" class="radio radio-sm">
                                {{ __('Alles auf einen Standard-Benutzer (Org-Owner) buchen') }}
                            </label>
                        </div>
                    </div>

                    <div>
                        <h2 class="mb-1 font-['Space_Grotesk'] text-base font-semibold">{{ __('Zeitraum (optional)') }}</h2>
                        <p class="mb-2 text-xs text-base-content/60">{{ __('Leer lassen, um die vollständige Historie zu importieren.') }}</p>
                        <div class="flex flex-wrap gap-2">
                            <label class="form-control">
                                <span class="label-text text-xs">{{ __('Von') }}</span>
                                <input type="date" name="date_from" value="{{ old('date_from') }}" class="input input-sm input-bordered">
                            </label>
                            <label class="form-control">
                                <span class="label-text text-xs">{{ __('Bis') }}</span>
                                <input type="date" name="date_to" value="{{ old('date_to') }}" class="input input-sm input-bordered">
                            </label>
                        </div>
                    </div>
                </div>

                <div class="flex flex-wrap justify-end gap-2 border-t border-base-300 pt-3">
                    <button type="submit" name="action" value="preview" class="btn btn-sm">{{ __('Vorschau (nichts speichern)') }}</button>
                    <button type="submit" name="action" value="import" class="btn btn-sm btn-primary"
                            data-confirm-dialog
                            data-confirm-message="{{ __('Import jetzt ausführen? Es werden Kunden, Projekte, Benutzer und Zeiteinträge angelegt.') }}">
                        {{ __('Importieren') }}
                    </button>
                </div>
            </form>
        @endif

        {{-- Ergebnis / Vorschau --}}
        @if (! empty($summary))
            <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
                <h2 class="mb-2 font-['Space_Grotesk'] text-base font-semibold">
                    {{ $summary['dry_run'] ? __('Vorschau (nicht gespeichert)') : __('Import-Ergebnis') }}
                </h2>
                <div class="overflow-x-auto">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>{{ __('Workspace') }}</th>
                                <th>{{ __('Modus') }}</th>
                                <th class="text-right">{{ __('Kunden (neu/wiederv.)') }}</th>
                                <th class="text-right">{{ __('Fremdkunden (neu/wiederv.)') }}</th>
                                <th class="text-right">{{ __('Projekte (neu/wiederv.)') }}</th>
                                <th class="text-right">{{ __('Benutzer neu') }}</th>
                                <th class="text-right">{{ __('Zeiten (gebucht/übersprungen)') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($summary['workspaces'] as $w)
                                <tr>
                                    <td>{{ $w['workspace'] }}@isset($w['customer'])<span class="text-base-content/50"> → {{ $w['customer'] }}</span>@endisset</td>
                                    <td>{{ $w['mode'] }}</td>
                                    <td class="text-right">{{ $w['customers_created'] }} / {{ $w['customers_reused'] }}</td>
                                    <td class="text-right">{{ $w['foreign_customers_created'] }} / {{ $w['foreign_customers_reused'] }}</td>
                                    <td class="text-right">{{ $w['projects_created'] }} / {{ $w['projects_reused'] }}</td>
                                    <td class="text-right">{{ $w['users_created'] }}</td>
                                    <td class="text-right">{{ $w['entries_created'] }} / {{ $w['entries_skipped'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="font-semibold">
                                <td colspan="2">{{ __('Summe') }}</td>
                                <td class="text-right">{{ $summary['totals']['customers_created'] }} / {{ $summary['totals']['customers_reused'] }}</td>
                                <td class="text-right">{{ $summary['totals']['foreign_customers_created'] }} / {{ $summary['totals']['foreign_customers_reused'] }}</td>
                                <td class="text-right">{{ $summary['totals']['projects_created'] }} / {{ $summary['totals']['projects_reused'] }}</td>
                                <td class="text-right">{{ $summary['totals']['users_created'] }}</td>
                                <td class="text-right">{{ $summary['totals']['entries_created'] }} / {{ $summary['totals']['entries_skipped'] }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        @endif
    </div>
</x-page-shell>
@endsection
