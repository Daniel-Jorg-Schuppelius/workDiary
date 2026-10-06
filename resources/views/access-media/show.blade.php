{{--
  Created on   : Tue Aug 18 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')

@section('title', __('Zutrittsmedium …:suffix', ['suffix' => $medium->number_suffix]))
@section('nav-title', __('Zutrittsmedium'))

@php
    use App\Enums\Access\AccessMediumStatus;
    /** @var \App\Models\Access\AccessMedium $medium */
@endphp

@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar back-route="access-media.index" :back-label="__('Zur Liste')">
            <div class="flex min-w-0 items-center gap-2">
                <span class="truncate font-medium">{{ $medium->label ?: __('Medium') }} <span class="font-mono text-sm text-muted">…{{ $medium->number_suffix }}</span></span>
                <x-status-badge :tone="$medium->status->tone()" size="sm">{{ $medium->status->label() }}</x-status-badge>
            </div>
        </x-page-toolbar>
    </x-slot:toolbar>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <x-card :title="__('Stammdaten')">
                <x-detail-grid layout="split" :cols="2">
                    <x-detail-grid.row :label="__('Typ')">{{ $medium->type->label() }}</x-detail-grid.row>
                    <x-detail-grid.row :label="__('Objekt / Standort')">{{ $medium->site?->name ?? '—' }}</x-detail-grid.row>
                    <x-detail-grid.row :label="__('Anlage / System')">{{ $medium->system_name ?? '—' }}</x-detail-grid.row>
                    <x-detail-grid.row :label="__('Inhaber')">{{ $medium->holderDisplay() ?? '—' }}</x-detail-grid.row>
                    @if ($medium->blocked_at)
                        <x-detail-grid.row :label="__('Gesperrt am')">{{ $medium->blocked_at->fdatetime() }}</x-detail-grid.row>
                    @endif
                </x-detail-grid>
                @if ($medium->notes)
                    <p class="mt-3 whitespace-pre-line text-sm text-base-content/80">{{ $medium->notes }}</p>
                @endif
                @if ($medium->blockTask)
                    <div class="mt-3 rounded-lg border border-base-300 p-3 text-sm">
                        <span class="font-medium">{{ __('Sperr-Aufgabe') }}:</span> {{ $medium->blockTask->title }}
                        — {{ $medium->blockTask->status?->value === 'done' ? __('erledigt') : __('offen (fällig :due)', ['due' => $medium->blockTask->due_date?->fdate() ?? '—']) }}
                    </div>
                @endif
            </x-card>

            <x-card :title="__('Historie')">
                @if ($handovers->isEmpty())
                    <x-empty-state icon="swap_horiz" :title="__('Noch keine Übergaben.')" compact />
                @else
                    <x-table bare>
                        <x-slot:head>
                            <tr>
                                <th>{{ __('Zeitpunkt') }}</th>
                                <th>{{ __('Vorgang') }}</th>
                                <th>{{ __('Inhaber') }}</th>
                                <th>{{ __('Rückgabe erwartet') }}</th>
                                <th>{{ __('Zustand') }}</th>
                                <th>{{ __('Unterschrift') }}</th>
                            </tr>
                        </x-slot:head>
                        @foreach ($handovers as $handover)
                            <tr>
                                <td class="whitespace-nowrap text-sm">{{ $handover->occurred_at->fdatetime() }}</td>
                                <td class="text-sm">{{ $handover->direction === 'issue' ? __('Ausgabe') : __('Rückgabe') }}</td>
                                <td class="text-sm">{{ $handover->holderUser?->name ?? trim(($handover->holder_name ?? '') . ' ' . ($handover->holder_company ? '· ' . $handover->holder_company : '')) ?: '—' }}</td>
                                <td class="whitespace-nowrap text-sm">{{ $handover->expected_return_at?->fdate() ?? '—' }}</td>
                                <td class="text-sm text-base-content/70">{{ $handover->condition ?? '—' }}</td>
                                <td class="font-mono text-xs text-muted">{{ $handover->signature_token ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </x-table>
                @endif
            </x-card>
        </div>

        <div class="space-y-4">
            @if ($canManage)
                @if ($medium->status === AccessMediumStatus::InStock)
                    <x-card :title="__('Ausgeben')">
                        <form method="POST" action="{{ route('access-media.issue', $medium) }}" class="space-y-3">
                            @csrf
                            <x-select-field name="holder_user" :label="__('Mitarbeiter')">
                                <option value="">{{ __('— externe Person —') }}</option>
                                @foreach ($users as $user)
                                    <option value="{{ $user->sqid }}">{{ $user->name }}</option>
                                @endforeach
                            </x-select-field>
                            {{-- Q1-Prüfpunkt: externe Inhaber ohne Mitarbeiterkonto. --}}
                            <x-input-field name="holder_name" :label="__('Externe Person')" />
                            <x-input-field name="holder_company" :label="__('Firma')" />
                            <x-input-field type="date" name="expected_return_at" :label="__('Rückgabe erwartet')" />
                            <x-input-field name="signature_token" :label="__('Unterschrifts-Referenz')"
                                           :hint="__('Verweis auf eine erfasste Unterschrift (Muster Schlüsselübergabe) — optional.')" />
                            <x-button type="submit" class="w-full">{{ __('Ausgeben') }}</x-button>
                        </form>
                    </x-card>
                @elseif ($medium->status === AccessMediumStatus::Issued)
                    <x-card :title="__('Rücknahme')">
                        <form method="POST" action="{{ route('access-media.take-back', $medium) }}" class="space-y-3">
                            @csrf
                            <x-input-field name="condition" :label="__('Zustand bei Rückgabe')" />
                            <x-button type="submit" class="w-full">{{ __('Zurücknehmen') }}</x-button>
                        </form>
                    </x-card>
                @endif

                @unless (in_array($medium->status, [AccessMediumStatus::Lost, AccessMediumStatus::Blocked, AccessMediumStatus::Retired], true))
                    <x-card :title="__('Verlust')">
                        <form method="POST" action="{{ route('access-media.report-lost', $medium) }}" class="space-y-3">
                            @csrf
                            <x-input-field name="note" :label="__('Hinweis zur Verlustmeldung')" />
                            {{-- Die Sperr-Aufgabe ist der Kontrollpunkt: workDiary
                                 sperrt keine Anlage, der Nachweis der Aufgabe schon. --}}
                            <x-button type="submit" tone="error" class="w-full">{{ __('Verlust melden (erzeugt Sperr-Aufgabe)') }}</x-button>
                        </form>
                    </x-card>
                @endunless

                @if ($medium->status === AccessMediumStatus::Lost)
                    <x-card :title="__('Sperrung bestätigen')">
                        <p class="mb-2 text-sm text-base-content/70">{{ __('Erst wenn das Medium in der Anlage gesperrt wurde, wird der Status „Gesperrt" gesetzt — das ist der Erledigungsnachweis der Sperr-Aufgabe.') }}</p>
                        <x-action-form :action="route('access-media.confirm-blocked', $medium)">
                            <x-icon-btn icon="lock" tone="warning" size="sm" type="submit" show-label>{{ __('In Anlage gesperrt') }}</x-icon-btn>
                        </x-action-form>
                    </x-card>
                @endif

                @if (in_array($medium->status, [AccessMediumStatus::InStock, AccessMediumStatus::Blocked], true))
                    <x-card :title="__('Ausmustern')">
                        <x-action-form :action="route('access-media.retire', $medium)"
                                       :confirm="__('Medium endgültig ausmustern?')"
                                       :confirm-label="__('Ausmustern')">
                            <x-icon-btn icon="delete_forever" size="sm" type="submit" show-label>{{ __('Ausmustern') }}</x-icon-btn>
                        </x-action-form>
                    </x-card>
                @endif
            @endif
        </div>
    </div>
</x-page-shell>
@endsection
