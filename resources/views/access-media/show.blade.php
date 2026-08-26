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
    /** @var \App\Models\AccessMedium $medium */
@endphp

@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar>
            <div class="flex min-w-0 items-center gap-2">
                <span class="truncate font-medium">{{ $medium->label ?: __('Medium') }} <span class="font-mono text-sm text-muted">…{{ $medium->number_suffix }}</span></span>
                <x-status-badge :tone="$medium->status->tone()" size="sm">{{ $medium->status->label() }}</x-status-badge>
            </div>
            <x-slot:actions>
                <x-icon-btn icon="arrow_back" size="sm" :href="route('access-media.index')" show-label>{{ __('Zur Liste') }}</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <x-card :title="__('Stammdaten')">
                <dl class="grid gap-x-8 gap-y-1 text-sm sm:grid-cols-2">
                    <div class="flex justify-between gap-4"><dt class="text-muted">{{ __('Typ') }}</dt><dd>{{ $medium->type->label() }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-muted">{{ __('Objekt / Standort') }}</dt><dd>{{ $medium->site?->name ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-muted">{{ __('Anlage / System') }}</dt><dd>{{ $medium->system_name ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-muted">{{ __('Inhaber') }}</dt><dd>{{ $medium->holderDisplay() ?? '—' }}</dd></div>
                    @if ($medium->blocked_at)
                        <div class="flex justify-between gap-4"><dt class="text-muted">{{ __('Gesperrt am') }}</dt><dd>{{ $medium->blocked_at->format('d.m.Y H:i') }}</dd></div>
                    @endif
                </dl>
                @if ($medium->notes)
                    <p class="mt-3 whitespace-pre-line text-sm text-base-content/80">{{ $medium->notes }}</p>
                @endif
                @if ($medium->blockTask)
                    <div class="mt-3 rounded-lg border border-base-300 p-3 text-sm">
                        <span class="font-medium">{{ __('Sperr-Aufgabe') }}:</span> {{ $medium->blockTask->title }}
                        — {{ $medium->blockTask->status?->value === 'done' ? __('erledigt') : __('offen (fällig :due)', ['due' => $medium->blockTask->due_date?->format('d.m.Y') ?? '—']) }}
                    </div>
                @endif
            </x-card>

            <x-card :title="__('Historie')">
                @if ($handovers->isEmpty())
                    <p class="text-sm text-muted">{{ __('Noch keine Übergaben.') }}</p>
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
                                <td class="whitespace-nowrap text-sm">{{ $handover->occurred_at->format('d.m.Y H:i') }}</td>
                                <td class="text-sm">{{ $handover->direction === 'issue' ? __('Ausgabe') : __('Rückgabe') }}</td>
                                <td class="text-sm">{{ $handover->holderUser?->name ?? trim(($handover->holder_name ?? '') . ' ' . ($handover->holder_company ? '· ' . $handover->holder_company : '')) ?: '—' }}</td>
                                <td class="whitespace-nowrap text-sm">{{ $handover->expected_return_at?->format('d.m.Y') ?? '—' }}</td>
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
                            <button type="submit" class="btn btn-primary btn-sm w-full">{{ __('Ausgeben') }}</button>
                        </form>
                    </x-card>
                @elseif ($medium->status === AccessMediumStatus::Issued)
                    <x-card :title="__('Rücknahme')">
                        <form method="POST" action="{{ route('access-media.take-back', $medium) }}" class="space-y-3">
                            @csrf
                            <x-input-field name="condition" :label="__('Zustand bei Rückgabe')" />
                            <button type="submit" class="btn btn-primary btn-sm w-full">{{ __('Zurücknehmen') }}</button>
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
                            <button type="submit" class="btn btn-error btn-sm w-full">{{ __('Verlust melden (erzeugt Sperr-Aufgabe)') }}</button>
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
