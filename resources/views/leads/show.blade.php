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
    /** @var \App\Models\Sales\Lead $lead */
@endphp

@section('content')
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar back-route="leads.index" :back-label="__('Zur Liste')">
            <div class="flex min-w-0 items-center gap-2">
                <span class="truncate font-medium">{{ $lead->displayName() }}</span>
                <x-status-badge :tone="$lead->status->tone()" size="sm">{{ $lead->status->label() }}</x-status-badge>
            </div>
            <x-slot:actions>
                @if ($canManage && ! $lead->status->isFinal() && ! $lead->anonymized_at)
                    <x-icon-btn icon="edit" size="sm" data-entry-modal-trigger :href="route('leads.edit', $lead)" show-label>{{ __('Bearbeiten') }}</x-icon-btn>
                @endif
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <x-card :title="__('Stammdaten')">
                <x-detail-grid layout="split" :cols="2">
                    <x-detail-grid.row :label="__('Firma')">{{ $lead->company ?? '—' }}</x-detail-grid.row>
                    <x-detail-grid.row :label="__('Ansprechpartner')">{{ $lead->contact_name ?? '—' }}</x-detail-grid.row>
                    <x-detail-grid.row :label="__('E-Mail')">{{ $lead->email ?? '—' }}</x-detail-grid.row>
                    <x-detail-grid.row :label="__('Telefon')">{{ $lead->phone ?? '—' }}</x-detail-grid.row>
                    <x-detail-grid.row :label="__('Adresse')" full class="text-right">{{ implode(', ', $lead->postalAddressLines()) ?: '—' }}</x-detail-grid.row>
                    <x-detail-grid.row :label="__('Quelle')">{{ $lead->source->label() }}</x-detail-grid.row>
                    <x-detail-grid.row :label="__('Verantwortlich')">{{ $lead->responsible?->name ?? '—' }}</x-detail-grid.row>
                    <x-detail-grid.row :label="__('Letzter Kontakt')">{{ $lead->last_contact_at?->fdatetime() ?? '—' }}</x-detail-grid.row>
                    @if ($lead->customer)
                        <x-detail-grid.row :label="__('Kunde')"><a class="link" href="{{ route('customers.show', $lead->customer) }}">{{ $lead->customer->name }}</a></x-detail-grid.row>
                    @endif
                </x-detail-grid>
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
