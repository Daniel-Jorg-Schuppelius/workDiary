{{--
  Created on   : Thu Aug 13 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : index.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Zeitkonten-Pflege (MVP-526): Kontenstamm, Bebuchungsregeln,
  Sonderbuchungen und Sofort-Lauf.
--}}

@extends('layouts.app')
@section('title', __('Zeitkonten (Verwaltung)'))
@section('nav-title', __('Zeitkonten'))

@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar :subtitle="__('Konfigurierbare Zusatzkonten mit Journal — Gleitzeit und Urlaub bleiben eigene Konten.')">
            <x-slot:actions>
                <form method="POST" action="{{ route('admin.time-accounts.post') }}" class="inline-flex items-center gap-1">
                    @csrf
                    <input type="number" name="days" min="1" max="400" value="40"
                           class="input input-sm input-bordered w-20" aria-label="{{ __('Tage') }}">
                    <button type="submit" class="btn btn-sm btn-outline">{{ __('Jetzt bebuchen') }}</button>
                </form>
                <x-icon-btn icon="add" tone="primary" size="sm"
                            data-entry-modal-trigger
                            :href="route('admin.time-accounts.create')"
                            show-label>{{ __('Konto anlegen') }}</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    @if ($accounts->isEmpty())
        <x-empty-state framed
            icon='<span class="material-symbols-outlined" aria-hidden="true">account_balance</span>'
            :title="__('Keine Zeitkonten')"
            :message="__('Legen Sie ein Konto an, z. B. einen Nachtdienst-Zähler oder ein Freizeitkonto.')" />
    @else
        @foreach ($accounts as $account)
            <x-card>
                <div class="flex flex-wrap items-center gap-2 mb-3">
                    <h3 class="font-semibold text-lg">{{ $account->name }}</h3>
                    <span class="badge badge-ghost badge-sm font-mono">{{ $account->code }}</span>
                    <x-status-badge :tone="$account->is_active ? 'success' : 'ghost'" size="sm">
                        {{ $account->is_active ? __('aktiv') : __('inaktiv') }}
                    </x-status-badge>
                    <span class="text-sm text-base-content/60">
                        {{ $account->unit->label() }} · {{ $account->carryover_policy->label() }}
                        @if ($account->cap_amount !== null)
                            ({{ $account->unit->format((float) $account->cap_amount) }})
                        @endif
                        @if ($account->show_on_terminal)
                            · {{ __('Terminal-Anzeige') }}
                        @endif
                    </span>
                    <form method="POST" action="{{ route('admin.time-accounts.toggle', $account) }}" class="ml-auto">
                        @csrf
                        <button type="submit" class="btn btn-xs btn-ghost">
                            {{ $account->is_active ? __('Deaktivieren') : __('Aktivieren') }}
                        </button>
                    </form>
                </div>

                {{-- Bebuchungsregeln --}}
                <h4 class="font-medium mb-2">{{ __('Bebuchungsregeln') }}</h4>
                @if ($account->rules->isNotEmpty())
                    <x-table bare>
                        <x-slot:head>
                            <tr>
                                <x-table.th>{{ __('Quelle') }}</x-table.th>
                                <x-table.th>{{ __('Match') }}</x-table.th>
                                <x-table.th align="right">{{ __('Faktor') }}</x-table.th>
                                <x-table.th></x-table.th>
                            </tr>
                        </x-slot:head>
                        @foreach ($account->rules as $rule)
                            <tr>
                                <td>{{ $rule->source_type->label() }}</td>
                                <td class="font-mono text-sm">{{ $rule->match_value ?? '—' }}</td>
                                <td class="text-right tabular-nums">{{ rtrim(rtrim(\CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $rule->factor, 4, withThousandsSeparator: true), '0'), ',') }}</td>
                                <td class="text-right">
                                    <form method="POST" action="{{ route('admin.time-accounts.rules.destroy', [$account, $rule]) }}">
                                        @csrf
                                        @method('DELETE')
                                        <x-icon-btn icon="delete" size="sm" tone="ghost" type="submit"
                                                    :aria-label="__('Entfernen')" />
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </x-table>
                @endif
                <form method="POST" action="{{ route('admin.time-accounts.rules.store', $account) }}"
                      class="mt-2 flex flex-wrap items-end gap-2">
                    @csrf
                    <div class="fieldset">
                        <label class="fieldset-label">{{ __('Quelle') }}</label>
                        <select name="source_type" class="select select-sm select-bordered w-64" required>
                            @foreach ($sources as $source)
                                <option value="{{ $source->value }}">{{ $source->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="fieldset">
                        <label class="fieldset-label">{{ __('Match (Lohnart-Muster / Art / Schichttyp-Sqid)') }}</label>
                        <input type="text" name="match_value" class="input input-sm input-bordered w-64"
                               placeholder="z. B. surcharge.night* | vacation | sick">
                    </div>
                    <div class="fieldset">
                        <label class="fieldset-label">{{ __('Faktor') }}</label>
                        <input type="number" step="0.0001" name="factor" value="1"
                               class="input input-sm input-bordered w-28" required>
                    </div>
                    <button type="submit" class="btn btn-sm btn-outline">{{ __('Regel hinzufügen') }}</button>
                </form>

                {{-- Sonderbuchung --}}
                <div class="divider my-2"></div>
                <h4 class="font-medium mb-2">{{ __('Sonderbuchung') }}</h4>
                <form method="POST" action="{{ route('admin.time-accounts.manual', $account) }}"
                      class="flex flex-wrap items-end gap-2">
                    @csrf
                    <div class="fieldset">
                        <label class="fieldset-label">{{ __('Mitarbeiter:in') }}</label>
                        <select name="user_id" class="select select-sm select-bordered w-52" required>
                            @foreach ($members as $member)
                                <option value="{{ $member->sqid }}">{{ $member->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="fieldset">
                        <label class="fieldset-label">{{ __('Datum') }}</label>
                        <input type="date" name="booking_date" class="input input-sm input-bordered" required
                               value="{{ now()->toDateString() }}">
                    </div>
                    <div class="fieldset">
                        <label class="fieldset-label">{{ __('Menge (:unit, ± möglich)', ['unit' => $account->unit->label()]) }}</label>
                        <input type="number" step="0.01" name="quantity" class="input input-sm input-bordered w-32" required>
                    </div>
                    <div class="fieldset grow">
                        <label class="fieldset-label">{{ __('Begründung (Pflicht)') }}</label>
                        <input type="text" name="note" minlength="5" maxlength="500"
                               class="input input-sm input-bordered w-full" required>
                    </div>
                    <button type="submit" class="btn btn-sm btn-warning">{{ __('Buchen') }}</button>
                </form>
            </x-card>
        @endforeach
    @endif
</x-page-shell>
@endsection
