@extends('layouts.app')
@section('title', __('Toggl-Workspace-Import'))
@section('nav-title', __('Toggl-Workspace-Import'))

@section('content')
<x-page-shell>
    <div class="space-y-4">
        <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
            <div class="mb-1 flex flex-wrap items-center justify-between gap-2">
                <h1 class="font-['Space_Grotesk'] text-lg font-semibold">{{ __('Vollständigen Workspace-Export importieren') }}</h1>
                <a href="{{ route('admin.toggl.index') }}" class="btn btn-ghost btn-sm">{{ __('Zurück zum Import') }}</a>
            </div>
            <p class="mb-4 text-sm text-base-content/60">
                {{ __('Einmaliger Import eines kompletten Toggl-Exports (Ordner mit clients.json, projects.json, workspace_users.json und den Jahres-CSVs je Workspace). Gib den Server-Pfad zum Export an; je gefundenem Workspace legst du dann fest, was passieren soll.') }}
            </p>

            @if (session('status'))
                <div class="alert alert-success mb-3 text-sm">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="alert alert-error mb-3 text-sm">{{ $errors->first() }}</div>
            @endif

            {{-- Schritt 1a: Server-Pfad --}}
            <form method="GET" action="{{ route('admin.toggl.import-export') }}" class="flex items-end gap-2">
                <label class="form-control flex-1">
                    <span class="label-text text-xs">{{ __('Pfad zum Export-Ordner (auf dem Server)') }}</span>
                    <input type="text" name="path" value="{{ $path }}" placeholder="/tmp/toggl"
                           class="input input-sm input-bordered w-full font-mono">
                </label>
                <button type="submit" class="btn btn-sm">{{ __('Ordner einlesen') }}</button>
            </form>

            <div class="divider my-2 text-xs text-base-content/50">{{ __('oder') }}</div>

            {{-- Schritt 1b: ZIP-Upload (Toggl liefert den Export als ZIP) --}}
            <form method="POST" action="{{ route('admin.toggl.import-export.upload') }}"
                  enctype="multipart/form-data" class="flex items-end gap-2">
                @csrf
                <label class="form-control flex-1">
                    <span class="label-text text-xs">{{ __('Toggl-Export als ZIP hochladen') }}</span>
                    <input type="file" name="archive" accept=".zip,application/zip" required
                           class="file-input file-input-sm file-input-bordered w-full">
                </label>
                <button type="submit" class="btn btn-sm">{{ __('Hochladen & einlesen') }}</button>
            </form>

            @if ($path !== '' && ! $pathValid)
                <div class="alert alert-warning mt-3 text-sm">{{ __('Pfad nicht gefunden oder kein Verzeichnis.') }}</div>
            @elseif ($pathValid && empty($workspaces))
                <div class="alert alert-warning mt-3 text-sm">{{ __('Keine Workspace-Ordner (mit projects.json) im angegebenen Pfad gefunden.') }}</div>
            @endif
        </div>

        {{-- Schritt 2: Konfiguration je Workspace --}}
        @if ($pathValid && ! empty($workspaces))
            <form method="POST" action="{{ route('admin.toggl.import-export.run') }}"
                  class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs space-y-4">
                @csrf
                <input type="hidden" name="path" value="{{ $path }}">

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
                            <input type="hidden" name="folders[{{ $i }}]" value="{{ $ws['folder'] }}">
                            <div class="md:col-span-5">
                                <div class="font-semibold">{{ $ws['folder'] }}</div>
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
                            <div class="md:col-span-4 space-y-1" x-show="mode === 'customer'" x-cloak x-data="{ cmode: 'new' }">
                                <span class="label-text text-xs">{{ __('Kunde') }}</span>
                                <select x-model="cmode" class="select select-sm select-bordered w-full">
                                    <option value="new">{{ __('Neuen Kunden anlegen') }}</option>
                                    <option value="existing">{{ __('Bestehenden Kunden wählen') }}</option>
                                </select>
                                <select name="customer_ids[{{ $i }}]" x-show="cmode === 'existing'" x-cloak
                                        class="select select-sm select-bordered w-full">
                                    <option value="">{{ __('– Kunde wählen –') }}</option>
                                    @foreach ($customers as $c)
                                        <option value="{{ $c['sqid'] }}">{{ $c['label'] }}</option>
                                    @endforeach
                                </select>
                                <input type="text" name="customer_names[{{ $i }}]" value="{{ $ws['folder'] }}"
                                       x-show="cmode === 'new'" placeholder="{{ __('Kundenname') }}"
                                       class="input input-sm input-bordered w-full">
                            </div>
                        </div>
                    @endforeach
                </div>

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

                    @if (! empty($togglUsers))
                        <div class="mt-3 rounded-box border border-base-300 p-3">
                            <p class="mb-2 text-xs text-base-content/60">
                                {{ __('Optional: einzelne Toggl-Benutzer fest einem bestehenden Benutzer zuordnen. Eine Auswahl hier hat Vorrang vor der obigen Regel (auch vor „Standard-Benutzer"). „Automatisch" = nach obiger Regel.') }}
                            </p>
                            <div class="space-y-1">
                                @foreach ($togglUsers as $tu)
                                    {{-- Feste Select-Breite + truncate: der gewählte Eintrag bleibt
                                         einzeilig im Kasten (Ellipsis), statt das Select aufzublähen. --}}
                                    <div class="flex items-center gap-2">
                                        <span class="min-w-0 flex-1 truncate text-sm" title="{{ $tu['email'] }}">
                                            {{ $tu['name'] }} <span class="text-base-content/50">({{ $tu['email'] }})</span>
                                        </span>
                                        <select name="user_map[{{ $tu['email'] }}]" class="select select-sm select-bordered w-64 max-w-full shrink-0 truncate">
                                            <option value="">{{ __('— automatisch —') }}</option>
                                            @foreach ($systemUsers as $su)
                                                <option value="{{ $su['sqid'] }}">{{ $su['label'] }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
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
                <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                    <h2 class="font-['Space_Grotesk'] text-base font-semibold">
                        {{ $summary['dry_run'] ? __('Vorschau (nicht gespeichert)') : __('Import-Ergebnis') }}
                    </h2>
                    <form method="POST" action="{{ route('admin.toggl.import-export.reset') }}">
                        @csrf
                        <button type="submit" class="btn btn-ghost btn-sm">{{ __('Vorschau zurücksetzen') }}</button>
                    </form>
                </div>
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
