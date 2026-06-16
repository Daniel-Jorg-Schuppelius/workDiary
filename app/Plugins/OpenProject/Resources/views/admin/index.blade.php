@extends('layouts.app')
@section('title', __('OpenProject'))
@section('nav-title', __('OpenProject'))

@section('content')
<x-page-shell>
    <div class="space-y-4">
        {{-- Sync-Aktionen --}}
        <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
            <div class="mb-1 flex flex-wrap items-center justify-between gap-2">
                <h1 class="font-['Space_Grotesk'] text-lg font-semibold">{{ __('OpenProject synchronisieren') }}</h1>
                <div class="flex gap-2">
                    <a href="{{ route('admin.openproject.push') }}" class="btn btn-ghost btn-sm">{{ __('Zeiten zurückbuchen') }}</a>
                    <a href="{{ route('admin.openproject.mappings.index') }}" class="btn btn-ghost btn-sm">{{ __('Zuordnungen verwalten') }}</a>
                </div>
            </div>
            <p class="mb-4 text-sm text-base-content/60">
                {{ __('Projekte und Work Packages werden mit workDiary abgeglichen, anschließend die Zeiteinträge importiert. Zuordenbare Einträge werden direkt im Projekt gebucht, der Rest landet unten in der Inbox.') }}
            </p>

            @if (session('status'))
                <div class="alert alert-success mb-3 text-sm">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="alert alert-error mb-3 text-sm">{{ $errors->first() }}</div>
            @endif

            <div class="grid gap-3 md:grid-cols-2">
                <form method="POST" action="{{ route('admin.openproject.sync') }}"
                      class="flex items-center justify-between gap-2 rounded-box bg-base-200/50 p-3">
                    @csrf
                    <div>
                        <div class="text-sm font-semibold">{{ __('Struktur + Zeiten synchronisieren') }}</div>
                        <div class="text-xs text-base-content/60">{{ __('Nutzt die hinterlegten Zugangsdaten und das Zeitfenster.') }}</div>
                    </div>
                    <button type="submit" class="btn btn-sm btn-primary">{{ __('Jetzt synchronisieren') }}</button>
                </form>

                <form method="POST" action="{{ route('admin.openproject.sync-structure') }}"
                      class="flex items-center justify-between gap-2 rounded-box bg-base-200/50 p-3">
                    @csrf
                    <div>
                        <div class="text-sm font-semibold">{{ __('Nur Struktur abgleichen') }}</div>
                        <div class="text-xs text-base-content/60">{{ __('Projekte, Work Packages und Benutzer neu zuordnen.') }}</div>
                    </div>
                    <button type="submit" class="btn btn-sm">{{ __('Struktur abgleichen') }}</button>
                </form>
            </div>
        </div>

        {{-- Inbox: unzugeordnete Zeiteinträge --}}
        <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
            <div class="mb-3">
                <h2 class="font-['Space_Grotesk'] text-base font-semibold">{{ __('Unzugeordnete Zeiteinträge') }}</h2>
                <p class="text-sm text-base-content/60">
                    {{ __('Diese OpenProject-Projekte ließen sich keinem workDiary-Projekt zuordnen. Ordne jede Gruppe einem bestehenden Projekt zu oder lege es direkt neu an — die Einträge werden dann gebucht und künftige Importe matchen automatisch.') }}
                </p>
            </div>

            @if ($groups->isEmpty())
                <p class="rounded-box border border-base-300 p-6 text-center text-sm text-base-content/60">
                    {{ __('Keine offenen Einträge. Alles zugeordnet.') }}
                </p>
            @else
                <div class="space-y-3">
                    @foreach ($groups as $group)
                        <div class="rounded-box border border-base-300 p-3" x-data="{ newProject: {{ count($projects) > 0 ? 'false' : 'true' }} }">
                            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                                <div>
                                    <x-status-badge tone="neutral" size="md">{{ $group->project_name ?: __('(ohne Projekt)') }}</x-status-badge>
                                    <span class="ml-2 text-sm text-base-content/60">
                                        {{ trans_choice(':count Eintrag|:count Einträge', $group->count, ['count' => $group->count]) }},
                                        {{ $group->minutes }} {{ __('Min.') }} ·
                                        {{ \Illuminate\Support\Carbon::parse($group->first_seen)->isoFormat('L') }} – {{ \Illuminate\Support\Carbon::parse($group->last_seen)->isoFormat('L') }}
                                    </span>
                                </div>
                                <form method="POST" action="{{ route('admin.openproject.pending.dismiss') }}"
                                      data-confirm-dialog
                                      data-confirm-message="{{ __('Diese Einträge verwerfen? Sie werden nicht gebucht.') }}">
                                    @csrf
                                    <input type="hidden" name="project_external_id" value="{{ $group->project_external_id }}">
                                    <button type="submit" class="btn btn-ghost btn-sm text-error">{{ __('Verwerfen') }}</button>
                                </form>
                            </div>

                            <form method="POST" action="{{ route('admin.openproject.pending.assign') }}"
                                  class="grid gap-3 rounded-box bg-base-200/50 p-3 md:grid-cols-2">
                                @csrf
                                <input type="hidden" name="project_external_id" value="{{ $group->project_external_id }}">
                                <input type="hidden" name="project_mode" :value="newProject ? 'new' : 'existing'">

                                <div class="space-y-1">
                                    <div class="flex items-center justify-between">
                                        <span class="label-text text-xs font-semibold">{{ __('workDiary-Projekt') }}</span>
                                        <label class="label cursor-pointer gap-1 py-0">
                                            <input type="checkbox" class="toggle toggle-xs" x-model="newProject" @disabled(count($projects) === 0)>
                                            <span class="label-text text-xs">{{ __('neu anlegen') }}</span>
                                        </label>
                                    </div>
                                    <select name="project_id" :disabled="newProject" x-show="!newProject"
                                            class="select select-sm select-bordered w-full">
                                        <option value="">{{ __('— Projekt wählen —') }}</option>
                                        @foreach ($projects as $project)
                                            <option value="{{ $project['sqid'] }}">{{ $project['name'] }}</option>
                                        @endforeach
                                    </select>
                                    <input type="text" name="new_project_name" :disabled="!newProject" x-show="newProject"
                                           value="{{ $group->project_name }}" placeholder="{{ __('Name des neuen Projekts') }}"
                                           class="input input-sm input-bordered w-full" x-cloak>
                                </div>

                                <div class="flex items-end justify-end">
                                    <button type="submit" class="btn btn-sm btn-primary">{{ __('Zuordnen & buchen') }}</button>
                                </div>
                            </form>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-page-shell>
@endsection
