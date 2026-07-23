@extends('layouts.app')
@section('title', __('Fernwartung – unzugeordnete Verbindungen'))
@section('nav-title', __('Fernwartung – Inbox'))

@section('content')
<x-index-page :subtitle="__('Diese AnyDesk-/TeamViewer-IDs tauchten in den Reports auf, sind aber keinem Gerät zugeordnet. Weise jede ID einem bestehenden Gerät zu oder lege ein neues an — die gespeicherten Sitzungen werden dann sofort als Zeiteinträge gebucht. Bei Mehrkundengeräten bleiben sie offen und werden im Reiter „Sitzungen zuordnen“ je Kunde gebucht; Sitzungen eigener Geräte ohne Kunden buchen auf das interne Wartungsprojekt.')">
    <x-slot:actions>
        <a href="{{ route('admin.imports.create', ['entity' => \App\Enums\Import\ImportEntity::RemoteSessions->value]) }}"
           class="btn btn-sm btn-primary">
            {{ __('Sitzungen importieren') }}
        </a>
    </x-slot:actions>

    <x-slot:note>
        <span class="material-symbols-outlined align-middle" aria-hidden="true">upload_file</span>
        {{ __('AnyDesk-Sitzungen werden im zentralen Import-Wizard eingelesen.') }}
    </x-slot:note>

    <x-filter-bar class="mb-3" :reset="route('admin.remote-support.pending.index')">
        <x-filter-field class="w-80 shrink-0">
            <input type="search" name="q" value="{{ $q }}"
                   placeholder="{{ __('Suche: Geräte-ID, Alias, Gerät oder Notiz …') }}"
                   aria-label="{{ __('Suche: Geräte-ID, Alias, Gerät oder Notiz …') }}"
                   class="input input-sm input-bordered w-full">
        </x-filter-field>
    </x-filter-bar>

    {{-- Kunden→Projekt-/Fremdkunden-Maps EINMAL pro Seite; die remoteAssign-
         Formulare lesen sie von hier statt sie je Karte zu duplizieren. --}}
    <div id="remote-assign-maps" hidden
         data-project-map="{{ json_encode($projectMap, JSON_UNESCAPED_UNICODE) }}"
         data-foreign-map="{{ json_encode($foreignMap, JSON_UNESCAPED_UNICODE) }}"></div>

    <div x-data="tabs('ids')" data-tab-persist="remote-support-pending-tab" data-tab-url-sync data-tab-allowed="ids,sessions">
        <div role="tablist" class="tabs tabs-box mb-3 w-fit">
            <a role="tab" href="#" class="tab gap-2" :class="tabClass('ids')" @click.prevent="setTab('ids')">
                {{ __('Unzugeordnete Geräte') }}
                @if ($groups->total() > 0)
                    <span class="badge badge-sm badge-neutral">{{ $groups->total() }}</span>
                @endif
            </a>
            <a role="tab" href="#" class="tab gap-2" :class="tabClass('sessions')" @click.prevent="setTab('sessions')">
                {{ __('Sitzungen zuordnen') }}
                @if ($sharedSessionCount > 0)
                    <span class="badge badge-sm badge-primary">{{ $sharedSessionCount }}</span>
                @endif
            </a>
        </div>

    <div x-show="isTab('ids')">
    <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">

        @if ($groups->total() === 0)
            <p class="rounded-box border border-base-300 p-6 text-center text-sm text-base-content/60">
                {{ $q !== '' ? __('Keine Treffer für die Suche.') : __('Keine offenen Verbindungen. Alles zugeordnet.') }}
            </p>
        @else
            @php
                // Geräte-Auswahl nach Kunde gruppieren: eigene/kundenlose Geräte
                // zuerst, danach Kunden alphabetisch.
                $customerNamesById = $customers->keyBy('id');
                $assetOptionGroups = $assets
                    ->groupBy(fn ($a): int => (int) ($a->customer_id ?? 0))
                    ->sortBy(function ($group, int $cid) use ($customerNamesById): string {
                        if ($cid === 0) {
                            return ' ';
                        }
                        $c = $customerNamesById[$cid] ?? null;

                        return mb_strtolower((string) ($c?->company ?: $c?->name ?: '~'));
                    });
            @endphp
            <div class="space-y-3">
                @foreach ($groups as $group)
                    @php
                        $sug = $suggestions[$group->provider . '|' . $group->remote_id] ?? null;
                        $sugData = $sug === null ? null : json_encode([
                            'shared' => $sug->kind === 'shared',
                            'customer' => $sug->customerSqid,
                            'foreign' => $sug->foreignSqid,
                            'asset' => $sug->assetSqid,
                            'matchcode' => $sug->matchcode,
                            'matchcodeScope' => $sug->matchcodeScope,
                        ], JSON_UNESCAPED_UNICODE);
                    @endphp
                    <div class="relative rounded-box border border-base-300 p-3"
                         @if ($sugData !== null) x-data="remoteSuggest" data-suggest="{{ $sugData }}" @endif>
                        {{-- Verwerfen: Icon oben rechts --}}
                        <form method="POST" action="{{ route('admin.remote-support.pending.dismiss') }}"
                              class="absolute right-2 top-2"
                              data-confirm-dialog
                              data-confirm-message="{{ __('Diese Verbindungen verwerfen? Sie werden nicht gebucht.') }}">
                            @csrf
                            <input type="hidden" name="provider" value="{{ $group->provider }}">
                            <input type="hidden" name="remote_id" value="{{ $group->remote_id }}">
                            <button type="submit" class="btn btn-ghost btn-sm btn-square text-base-content/50 hover:text-error"
                                    title="{{ __('Verwerfen') }}" aria-label="{{ __('Verwerfen') }}">
                                <span class="material-symbols-outlined" aria-hidden="true">delete</span>
                            </button>
                        </form>

                        <div class="mb-3 pr-10">
                            <div class="flex flex-wrap items-center gap-2">
                                <x-status-badge tone="neutral" size="md">{{ ucfirst($group->provider) }}</x-status-badge>
                                <span class="font-mono text-base font-semibold">{{ $group->remote_id }}</span>
                                @if ($group->alias)
                                    <span class="inline-flex items-center gap-1 text-sm font-medium text-base-content/80">
                                        <span class="material-symbols-outlined text-[1rem] align-middle" aria-hidden="true">badge</span>{{ $group->alias }}
                                    </span>
                                @endif
                            </div>
                            <div class="mt-1 text-sm text-base-content/60">
                                {{ trans_choice(':count Sitzung|:count Sitzungen', $group->count, ['count' => $group->count]) }},
                                {{ $group->minutes }} {{ __('Min.') }} ·
                                {{ \Illuminate\Support\Carbon::parse($group->first_seen)->isoFormat('L') }} – {{ \Illuminate\Support\Carbon::parse($group->last_seen)->isoFormat('L') }}
                            </div>
                            @if (! empty($group->notes))
                                <div class="mt-1.5 flex flex-wrap gap-1">
                                    @foreach ($group->notes as $note)
                                        <span class="inline-flex items-center gap-1 rounded-box bg-base-200 px-2 py-0.5 text-xs text-base-content/70">
                                            <span class="material-symbols-outlined text-[0.9rem] align-middle" aria-hidden="true">sticky_note_2</span>{{ $note }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        {{-- Zuweisungsvorschlag (Überlappung/Alias): befüllt nur vor, gebucht wird per Formular. --}}
                        @if ($sug !== null)
                            <div class="mb-3 rounded-box border border-primary/30 bg-primary/5 p-3">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="material-symbols-outlined text-[1.2rem] text-primary" aria-hidden="true">lightbulb</span>
                                    <span class="text-sm font-semibold">
                                        @if ($sug->kind === 'shared')
                                            {{ __('Vorschlag: Mehrkundengerät') }}
                                        @else
                                            {{ __('Vorschlag: :name', ['name' => $sug->customerName]) }}@if ($sug->foreignName !== null) <span class="font-normal">→ {{ $sug->foreignName }}</span>@endif
                                            @if ($sug->assetLabel !== null)
                                                <span class="font-normal text-base-content/70">· {{ __('Gerät „:name"', ['name' => $sug->assetLabel]) }}</span>
                                            @endif
                                        @endif
                                    </span>
                                    <button type="button" class="btn btn-xs btn-primary ml-auto" @click="apply()">
                                        <span class="material-symbols-outlined text-[1rem]" aria-hidden="true">magic_button</span>{{ __('Übernehmen') }}
                                    </button>
                                </div>
                                <ul class="mt-1.5 list-disc pl-6 text-xs text-base-content/70">
                                    @foreach ($sug->reasons as $reason)
                                        <li>{{ $reason }}</li>
                                    @endforeach
                                    @if ($sug->matchcode !== null)
                                        <li>{{ __('Beim Übernehmen wird das Kürzel „:code" am Kunden hinterlegt.', ['code' => $sug->matchcode]) }}</li>
                                    @endif
                                </ul>
                            </div>
                        @endif

                        {{-- Zuordnung: Tabs zwischen bestehendem und neuem Gerät --}}
                        @php $tabName = 'assign_'.\CommonToolkit\Helper\Data\CryptoHelper::hash($group->provider.'|'.$group->remote_id, \CommonToolkit\Enums\HashAlgorithm::MD5); @endphp
                        <div class="tabs tabs-lift tabs-sm">
                            <input type="radio" name="{{ $tabName }}" class="tab" aria-label="{{ __('Bestehendes Gerät') }}" checked />
                            <div class="tab-content rounded-box border-base-300 bg-base-100 p-4">
                                <form method="POST" action="{{ route('admin.remote-support.pending.assign-existing') }}">
                                    @csrf
                                    <input type="hidden" name="provider" value="{{ $group->provider }}">
                                    <input type="hidden" name="remote_id" value="{{ $group->remote_id }}">
                                    <input type="hidden" name="matchcode" value="">
                                    <input type="hidden" name="matchcode_scope" value="">
                                    <label class="flex max-w-xl flex-col gap-1">
                                        <span class="label-text text-xs font-medium text-base-content/70">{{ __('Gerät auswählen') }}</span>
                                        <select name="asset_id" required class="select select-sm select-bordered w-full">
                                            <option value="">{{ __('— Gerät wählen —') }}</option>
                                            @foreach ($assetOptionGroups as $cid => $customerAssets)
                                                @php $c = $cid !== 0 ? ($customerNamesById[$cid] ?? null) : null; @endphp
                                                <optgroup label="{{ $c ? ($c->company ?: $c->name) : __('Eigene Geräte / ohne festen Kunden') }}">
                                                    @foreach ($customerAssets as $asset)
                                                        <option value="{{ $asset->sqid }}">{{ $asset->name ?: $asset->asset_no }} ({{ $asset->asset_no }})</option>
                                                    @endforeach
                                                </optgroup>
                                            @endforeach
                                        </select>
                                    </label>
                                    <div class="mt-3 flex flex-wrap items-center justify-between gap-3 border-t border-base-300/60 pt-3">
                                        <label class="flex cursor-pointer items-center gap-2" title="{{ __('Sitzungen werden nicht automatisch gebucht, sondern im Reiter „Sitzungen zuordnen“ je Kunde gebucht.') }}">
                                            <input type="checkbox" name="shared_remote" value="1" class="checkbox checkbox-sm checkbox-primary">
                                            <span class="text-xs font-medium">{{ __('Mehrkundengerät') }}</span>
                                            <span class="material-symbols-outlined text-[1rem] text-base-content/40" aria-hidden="true">help</span>
                                        </label>
                                        <button type="submit" class="btn btn-sm btn-primary">
                                            <span class="material-symbols-outlined text-[1.1rem]" aria-hidden="true">link</span>{{ __('Zuordnen') }}
                                        </button>
                                    </div>
                                </form>
                            </div>

                            <input type="radio" name="{{ $tabName }}" class="tab" aria-label="{{ __('Neues Gerät') }}" />
                            <div class="tab-content rounded-box border-base-300 bg-base-100 p-4">
                                <form method="POST" action="{{ route('admin.remote-support.pending.assign-new') }}"
                                      x-data="remoteAssign">
                                    @csrf
                                    <input type="hidden" name="provider" value="{{ $group->provider }}">
                                    <input type="hidden" name="remote_id" value="{{ $group->remote_id }}">
                                    <input type="hidden" name="matchcode" value="">
                                    <input type="hidden" name="matchcode_scope" value="">
                                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                                        <label class="flex flex-col gap-1">
                                            <span class="label-text text-xs font-medium text-base-content/70">{{ __('Name') }}</span>
                                            <input type="text" name="name" required value="{{ $group->alias }}" placeholder="{{ __('z. B. PC Empfang') }}"
                                                   class="input input-sm input-bordered w-full">
                                        </label>
                                        <label class="flex flex-col gap-1">
                                            <span class="label-text text-xs font-medium text-base-content/70">{{ __('Kategorie') }}</span>
                                            <select name="category_code" required class="select select-sm select-bordered w-full">
                                                @foreach ($categories as $code => $label)
                                                    <option value="{{ $code }}" @selected($code === 'workstation')>{{ __($label) }}</option>
                                                @endforeach
                                            </select>
                                        </label>
                                        <label class="flex flex-col gap-1">
                                            <span class="label-text text-xs font-medium text-base-content/70">{{ __('Kunde') }}</span>
                                            <select name="customer_id" x-model="customer" @change="resetForeign" class="select select-sm select-bordered w-full">
                                                <option value="">{{ __('— kein fester Kunde (Firmenrechner) —') }}</option>
                                                @foreach ($customers as $customer)
                                                    <option value="{{ $customer->sqid }}">{{ $customer->company ?: $customer->name }}</option>
                                                @endforeach
                                            </select>
                                        </label>
                                        <label class="flex flex-col gap-1" x-show="hasForeignCustomers" x-cloak>
                                            <span class="label-text text-xs font-medium text-base-content/70">{{ __('Fremdkunde (Endkunde)') }}</span>
                                            <select name="foreign_customer_id" x-model="foreign" class="select select-sm select-bordered w-full">
                                                <option value="">{{ __('— direkt beim Kunden —') }}</option>
                                                <template x-for="f in foreignCustomers" :key="f.id">
                                                    <option :value="f.id" x-text="f.name"></option>
                                                </template>
                                            </select>
                                        </label>
                                    </div>
                                    <div class="mt-3 flex flex-wrap items-center justify-between gap-3 border-t border-base-300/60 pt-3">
                                        <label class="flex cursor-pointer items-center gap-2" title="{{ __('Sitzungen werden nicht automatisch gebucht, sondern im Reiter „Sitzungen zuordnen“ je Kunde gebucht.') }}">
                                            <input type="checkbox" name="shared_remote" value="1" class="checkbox checkbox-sm checkbox-primary">
                                            <span class="text-xs font-medium">{{ __('Mehrkundengerät') }}</span>
                                            <span class="material-symbols-outlined text-[1rem] text-base-content/40" aria-hidden="true">help</span>
                                        </label>
                                        <button type="submit" class="btn btn-sm btn-primary">
                                            <span class="material-symbols-outlined text-[1.1rem]" aria-hidden="true">add</span>{{ __('Anlegen & zuordnen') }}
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
    </div>

    <div x-show="isTab('sessions')" x-cloak>
        <div class="rounded-box border border-base-300 bg-base-100 p-4 shadow-xs">
            <div class="mb-3">
                <h2 class="font-['Space_Grotesk'] text-lg font-semibold">{{ __('Mehrkundengeräte – Sitzungen zuordnen') }}</h2>
                <p class="text-sm text-base-content/60">
                    {{ __('Diese Rechner werden für mehrere Kunden genutzt. Markiere die Sitzungen, wähle den Kunden (und optional ein Projekt) und buche sie gesammelt.') }}
                </p>
            </div>

            @if ($shared->total() === 0)
                <p class="rounded-box border border-base-300 p-6 text-center text-sm text-base-content/60">
                    {{ $q !== '' ? __('Keine Treffer für die Suche.') : __('Keine offenen Sitzungen zur Einzelzuordnung.') }}
                </p>
            @else
            <div class="space-y-4">
                @foreach ($shared as $device)
                    @php
                        $assetName = $device->asset->name ?: $device->asset->asset_no;
                        // Nur die neuesten Sitzungen rendern — 200+ Zeilen je Karte
                        // machten die Seite träge; nach Buchen/Verwerfen rücken ältere nach.
                        $visibleSessions = $device->sessions->take($sharedSessionLimit);
                    @endphp
                    <form method="POST" action="{{ route('admin.remote-support.pending.assign-shared') }}"
                          class="rounded-box border border-base-300 p-3"
                          x-data="remoteAssign">
                        @csrf

                        <div class="mb-2 flex flex-wrap items-center gap-2">
                            <span class="material-symbols-outlined align-middle text-base-content/70" aria-hidden="true">computer</span>
                            <span class="font-semibold">{{ $assetName }}</span>
                            <x-status-badge tone="neutral" size="sm">{{ $device->asset->asset_no }}</x-status-badge>
                            <span class="text-sm text-base-content/60">
                                {{ trans_choice(':count Sitzung|:count Sitzungen', $device->sessions->count(), ['count' => $device->sessions->count()]) }}
                            </span>
                            @if ($device->sessions->count() > $visibleSessions->count())
                                <span class="badge badge-sm badge-warning"
                                      title="{{ __('Nach dem Buchen oder Verwerfen rücken ältere Sitzungen nach.') }}">
                                    {{ __('nur die neuesten :count angezeigt', ['count' => $visibleSessions->count()]) }}
                                </span>
                            @endif
                            @if (($device->attempts ?? 0) > 0)
                                <span class="badge badge-sm badge-ghost text-base-content/60"
                                      title="{{ __('Verbindungsversuche ohne Dauer (0 Sekunden) — sie ziehen beim Buchen den Beginn der folgenden Sitzung vor.') }}">
                                    {{ trans_choice(':count Verbindungsversuch|:count Verbindungsversuche', (int) $device->attempts, ['count' => $device->attempts]) }}
                                </span>
                            @endif
                        </div>

                        <div class="overflow-x-auto" x-ref="list">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th class="w-8">
                                            <input type="checkbox" class="checkbox checkbox-sm"
                                                   x-model="allChecked" @change="toggleAll()"
                                                   aria-label="{{ __('Alle auswählen') }}">
                                        </th>
                                        <th>{{ __('Zeitraum') }}</th>
                                        <th class="text-right">{{ __('Min.') }}</th>
                                        <th>{{ __('Provider') }}</th>
                                        <th>{{ __('Notiz') }}</th>
                                        <th>{{ __('Vorschlag') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($visibleSessions as $session)
                                        @php $rowSug = $sessionSuggestions[$session->id] ?? null; @endphp
                                        <tr @if ($rowSug !== null) data-suggest-customer="{{ $rowSug->customerSqid }}" data-suggest-foreign="{{ $rowSug->foreignSqid ?? '' }}" @endif>
                                            <td>
                                                <input type="checkbox" name="pending_ids[]" value="{{ $session->sqid }}"
                                                       class="checkbox checkbox-sm"
                                                       aria-label="{{ __('Sitzung auswählen') }}">
                                            </td>
                                            <td class="whitespace-nowrap text-sm">
                                                {{ \Illuminate\Support\Carbon::parse($session->started_at)->isoFormat('L HH:mm') }}
                                                – {{ \Illuminate\Support\Carbon::parse($session->ended_at)->isoFormat('HH:mm') }}
                                            </td>
                                            <td class="text-right text-sm">{{ $session->minutes() }}</td>
                                            <td class="text-sm">{{ ucfirst($session->provider) }}</td>
                                            <td class="text-sm text-base-content/70">{{ $session->note }}</td>
                                            <td>
                                                @if ($rowSug !== null)
                                                    <button type="button"
                                                            class="badge badge-sm badge-outline badge-primary cursor-pointer"
                                                            data-suggest-customer="{{ $rowSug->customerSqid }}"
                                                            data-suggest-foreign="{{ $rowSug->foreignSqid ?? '' }}"
                                                            @click.prevent="applySuggestion($event)"
                                                            title="{{ __('Überlappt :minutes Min. mit erfassten Zeiten dieses Kunden. Klick wählt den Kunden und markiert alle passenden Sitzungen.', ['minutes' => $rowSug->minutes]) }}">
                                                        {{ $rowSug->customerName }}@if ($rowSug->foreignName !== null) → {{ $rowSug->foreignName }}@endif
                                                    </button>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-3 flex flex-wrap items-end gap-2">
                            <label class="flex w-48 flex-col gap-1">
                                <span class="label-text text-xs">{{ __('Kunde') }}</span>
                                <select name="customer_id" required x-model="customer" @change="resetForeign" class="select select-sm select-bordered w-full">
                                    <option value="">{{ __('— Kunde —') }}</option>
                                    @foreach ($customers as $customer)
                                        <option value="{{ $customer->sqid }}">{{ $customer->company ?: $customer->name }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="flex w-48 flex-col gap-1" x-show="hasForeignCustomers" x-cloak>
                                <span class="label-text text-xs">{{ __('Fremdkunde (Endkunde)') }}</span>
                                <select name="foreign_customer_id" x-model="foreign" class="select select-sm select-bordered w-full">
                                    <option value="">{{ __('— direkt beim Kunden —') }}</option>
                                    <template x-for="f in foreignCustomers" :key="f.id">
                                        <option :value="f.id" x-text="f.name"></option>
                                    </template>
                                </select>
                            </label>
                            <label class="flex w-48 flex-col gap-1">
                                <span class="label-text text-xs">{{ __('Projekt') }}</span>
                                <select name="project_id" class="select select-sm select-bordered w-full" :disabled="noCustomer">
                                    <option value="">{{ __('— Standardprojekt —') }}</option>
                                    <template x-for="p in projects" :key="p.id">
                                        <option :value="p.id" x-text="p.name"></option>
                                    </template>
                                </select>
                            </label>
                            <button type="submit" class="btn btn-sm btn-primary ml-auto">
                                <span class="material-symbols-outlined text-[1.1rem]" aria-hidden="true">schedule</span>{{ __('Markierte buchen') }}
                            </button>
                            <button type="submit" formaction="{{ route('admin.remote-support.pending.assign-internal') }}"
                                    class="btn btn-sm btn-ghost"
                                    formnovalidate
                                    title="{{ __('Bucht die markierten Sitzungen ohne Kunden auf das Projekt „Interne Wartung“.') }}">
                                <span class="material-symbols-outlined text-[1.1rem]" aria-hidden="true">home_repair_service</span>{{ __('Markierte intern buchen') }}
                            </button>
                            <button type="submit" formaction="{{ route('admin.remote-support.pending.dismiss-session') }}"
                                    class="btn btn-sm btn-ghost text-error"
                                    formnovalidate
                                    data-confirm-dialog
                                    data-confirm-message="{{ __('Markierte Sitzungen verwerfen? Sie werden nicht gebucht.') }}">
                                <span class="material-symbols-outlined text-[1.1rem]" aria-hidden="true">delete</span>{{ __('Markierte verwerfen') }}
                            </button>
                        </div>
                    </form>
                @endforeach
            </div>
            @endif
        </div>
    </div>

    {{-- Stehende Pagination-Panels, je Tab eines (Sichtbarkeit via tabs()/syncTabFooters). --}}
    <x-pagination :paginator="$groups" standing data-tab-footer="ids" :hidden="request('tab', 'ids') !== 'ids'" />
    <x-pagination :paginator="$shared" standing data-tab-footer="sessions" :hidden="request('tab', 'ids') !== 'sessions'" />
    </div>
</x-index-page>
@endsection
