{{--
  Created on   : Tue Aug 18 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}

@extends('layouts.app')

@section('title', __('Lead: :name', ['name' => $lead->displayName()]))
@section('nav-title', __('Lead'))

@php
    use App\Enums\Sales\LeadStatus;
    /** @var \App\Models\Lead $lead */
@endphp

@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar>
            <div class="flex min-w-0 items-center gap-2">
                <span class="truncate font-medium">{{ $lead->displayName() }}</span>
                <x-status-badge :tone="$lead->status->tone()" size="sm">{{ $lead->status->label() }}</x-status-badge>
            </div>
            <x-slot:actions>
                @if ($canManage && ! $lead->status->isFinal() && ! $lead->anonymized_at)
                    <x-icon-btn icon="edit" size="sm" data-entry-modal-trigger :href="route('leads.edit', $lead)" show-label>{{ __('Bearbeiten') }}</x-icon-btn>
                @endif
                <x-icon-btn icon="arrow_back" size="sm" :href="route('leads.index')" show-label>{{ __('Zur Liste') }}</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <x-card :title="__('Stammdaten')">
                <dl class="grid gap-x-8 gap-y-1 text-sm sm:grid-cols-2">
                    <div class="flex justify-between gap-4"><dt class="text-base-content/60">{{ __('Firma') }}</dt><dd>{{ $lead->company ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-base-content/60">{{ __('Ansprechpartner') }}</dt><dd>{{ $lead->contact_name ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-base-content/60">{{ __('E-Mail') }}</dt><dd>{{ $lead->email ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-base-content/60">{{ __('Telefon') }}</dt><dd>{{ $lead->phone ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-base-content/60">{{ __('Quelle') }}</dt><dd>{{ $lead->source->label() }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-base-content/60">{{ __('Verantwortlich') }}</dt><dd>{{ $lead->responsible?->name ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-base-content/60">{{ __('Letzter Kontakt') }}</dt><dd>{{ $lead->last_contact_at?->format('d.m.Y H:i') ?? '—' }}</dd></div>
                    @if ($lead->customer)
                        <div class="flex justify-between gap-4"><dt class="text-base-content/60">{{ __('Kunde') }}</dt>
                            <dd><a class="link" href="{{ route('customers.show', $lead->customer) }}">{{ $lead->customer->name }}</a></dd></div>
                    @endif
                </dl>
                @if ($lead->interest)
                    <p class="mt-3 whitespace-pre-line text-sm text-base-content/80">{{ $lead->interest }}</p>
                @endif
                @if ($lead->status === LeadStatus::Discarded && $lead->discard_reason)
                    <p class="mt-3 text-sm text-error/80">{{ __('Verworfen: :reason', ['reason' => $lead->discard_reason]) }}</p>
                @endif
            </x-card>

            {{-- Qualifizierung über die vorhandenen Kommunikationsnotizen —
                 kein eigenes Follow-up-System (Feature-Leitplanke). --}}
            @include('communication-notes._panel', ['notable' => $lead, 'notableKind' => 'lead'])
        </div>

        <div class="space-y-4">
            @if ($canManage && ! $lead->anonymized_at)
                <x-card :title="__('Pipeline')">
                    <div class="flex flex-wrap gap-2">
                        @foreach ($lead->status->allowedTransitions() as $next)
                            @if ($next === LeadStatus::Discarded)
                                <x-action-form :action="route('leads.transition', $lead)"
                                               :confirm="__('Lead verwerfen? Er läuft danach in die Anonymisierungsfrist.')"
                                               :confirm-label="__('Verwerfen')" confirm-tone="error">
                                    <input type="hidden" name="status" value="{{ $next->value }}">
                                    <input type="hidden" name="reason" value="{{ __('Ohne Angabe') }}">
                                    <x-icon-btn icon="block" tone="error" size="sm" type="submit" show-label>{{ $next->label() }}</x-icon-btn>
                                </x-action-form>
                            @else
                                <x-action-form :action="route('leads.transition', $lead)">
                                    <input type="hidden" name="status" value="{{ $next->value }}">
                                    <x-icon-btn icon="arrow_forward" size="sm" type="submit" show-label>{{ $next->label() }}</x-icon-btn>
                                </x-action-form>
                            @endif
                        @endforeach
                    </div>
                </x-card>

                @unless ($lead->status->isFinal())
                    <x-card :title="__('Konvertieren')">
                        @if ($duplicates->isNotEmpty())
                            {{-- Dublettenprüfung VOR der Anlage: kein zweiter
                                 Kundenstamm durch die Hintertür. --}}
                            <p class="mb-2 text-sm text-warning">{{ __('Mögliche Bestandskunden gefunden — verbinden statt doppelt anlegen:') }}</p>
                            <ul class="mb-3 space-y-2 text-sm">
                                @foreach ($duplicates as $candidate)
                                    <li class="flex items-center justify-between gap-2">
                                        <span class="min-w-0 truncate">{{ $candidate->name }}</span>
                                        <x-action-form :action="route('leads.convert', $lead)"
                                                       :confirm="__('Lead mit :name verbinden?', ['name' => $candidate->name])"
                                                       :confirm-label="__('Verbinden')">
                                            <input type="hidden" name="customer" value="{{ $candidate->sqid }}">
                                            <x-icon-btn icon="link" size="sm" type="submit" :title="__('Mit Bestandskunde verbinden')" />
                                        </x-action-form>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                        <x-action-form :action="route('leads.convert', $lead)"
                                       :confirm="$duplicates->isNotEmpty()
                                           ? __('Trotz möglicher Dubletten einen NEUEN Kunden anlegen?')
                                           : __('Lead in einen neuen Kunden konvertieren?')"
                                       :confirm-label="__('Kunde anlegen')" confirm-icon="person_add">
                            <x-icon-btn icon="person_add" tone="primary" size="sm" type="submit" show-label>{{ __('Als neuen Kunden anlegen') }}</x-icon-btn>
                        </x-action-form>
                    </x-card>
                @endunless
            @endif
        </div>
    </div>
</x-page-shell>
@endsection
