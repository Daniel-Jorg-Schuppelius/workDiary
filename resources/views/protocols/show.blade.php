{{--
  Created on   : Tue Jul 07 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Protokoll-Detailseite (Rang 28): Read-only-Trägerseite — Stammdaten,
  Positionen, Signaturen, Wetter-Nachweis (Rang 10), Anhänge, Verlauf und
  das Externe-Beteiligte-Panel (Feature 033).
--}}

@extends('layouts.app')
@section('title', $protocol->title)
@section('nav-title', __('Protokoll'))

@section('content')
@php
    /** @var \App\Models\Protocol $protocol */
    $subject = $protocol->subject;
    $subjectLabel = $subject?->title ?? $subject?->name;
@endphp
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar>
            <x-slot:title>{{ $protocol->title }}</x-slot:title>
            <x-slot:subtitle>
                <x-term :glossary="$protocol->type === \App\Enums\Protocol\ProtocolType::Acceptance ? 'abnahme' : null">{{ $protocol->type->label() }}</x-term> · {{ \App\Support\EntityType::label($protocol->subject_type) }}@if ($subjectLabel !== null): {{ $subjectLabel }}@endif
            </x-slot:subtitle>
            <x-slot:actions>
                <x-status-badge size="sm">{{ $protocol->status->label() }}</x-status-badge>
                <x-icon-btn icon="picture_as_pdf" tone="outline" size="sm" :href="route('protocols.pdf', $protocol)" show-label>{{ __('PDF') }}</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    @if (session('status'))
        <div class="alert alert-success text-sm">{{ session('status') }}</div>
    @endif
    <div class="grid gap-4 lg:grid-cols-2">
        <x-card :title="__('Stammdaten')" icon="badge">
            <x-detail-grid>
                <x-detail-grid.row :label="__('Typ')" :value="$protocol->type->label()" />
                <x-detail-grid.row :label="__('Status')" :value="$protocol->status->label()" />
                <x-detail-grid.row :label="__('Sichtbarkeit')" :value="$protocol->visibility->label()" />
                <x-detail-grid.row :label="__('Zeitpunkt')" :value="$protocol->occurred_at?->fdatetime()" />
                <x-detail-grid.row :label="__('Revision')" :value="(string) $protocol->revision" />
                <x-detail-grid.row :label="__('Erstellt von')" :value="$protocol->creator?->name" />
                @if ($protocol->description)
                    <x-detail-grid.row :label="__('Beschreibung')" :value="$protocol->description" />
                @endif
            </x-detail-grid>
        </x-card>

        {{-- Wetter-Nachweis (Rang 10/MVP-131): Snapshot mit Quelle + Abrufzeit als Beweiswert. --}}
        <x-card :title="__('Wetter')" icon="partly_cloudy_day">
            @if ($protocol->weatherSnapshot)
                @php $weather = $protocol->weatherSnapshot; @endphp
                <x-detail-grid>
                    <x-detail-grid.row :label="__('Temperatur (min/max)')" :value="$weather->temp_min . ' / ' . $weather->temp_max . ' °C'" />
                    <x-detail-grid.row :label="__('Niederschlag')" :value="$weather->precipitation_mm . ' mm'" />
                    <x-detail-grid.row :label="__('Windspitze')" :value="$weather->wind_gust_kmh . ' km/h'" />
                    {{-- Provider-Label statt roher Kennung; beim DWD zugleich der CC-BY-Quellenvermerk (A7/MVP-131). --}}
                    <x-detail-grid.row :label="__('Quelle')" :value="\App\Support\Trans::or('weather.providers.' . $weather->provider, $weather->provider) . ' · ' . $weather->fetched_at?->fdatetime()" />
                </x-detail-grid>
            @else
                <div class="flex items-center justify-between gap-2">
                    <p class="text-sm text-base-content/60">{{ __('Kein Wetter-Snapshot vorhanden.') }}</p>
                    @can('update', $protocol)
                        <x-action-form :action="route('protocols.weather', $protocol)" method="POST">
                            <x-icon-btn icon="cloud_download" tone="outline" size="sm" type="submit" show-label>{{ __('Wetter abrufen') }}</x-icon-btn>
                        </x-action-form>
                    @endcan
                </div>
            @endif
        </x-card>
    </div>

    <x-card :title="__('Positionen')" icon="checklist" :count="$protocol->items->count()">
        @if ($protocol->items->isEmpty())
            <x-empty-state icon='<span class="material-symbols-outlined" aria-hidden="true">checklist</span>' :title="__('Keine Positionen erfasst.')" compact />
        @else
            <x-table bare>
                <x-slot:head>
                    <tr>
                        <th>{{ __('Position') }}</th>
                        <th>{{ __('Ergebnis') }}</th>
                        <th>{{ __('Notiz') }}</th>
                        <th>{{ __('Gemessen') }}</th>
                    </tr>
                </x-slot:head>
                @foreach ($protocol->items as $item)
                    <tr>
                        <td class="font-medium">{{ $item->label }}</td>
                        <td>{{ $item->result?->label() ?? '—' }}</td>
                        <td class="text-sm text-base-content/70">{{ $item->note ?? '—' }}</td>
                        <td class="text-sm tabular-nums">{{ $item->measured_at?->fdatetime() ?? '—' }}</td>
                    </tr>
                    @php($canManagePhotos = auth()->user()?->can('update', $protocol) && $protocol->status->isEditable())
                    @if ($item->photos->isNotEmpty() || $canManagePhotos)
                        {{-- Vollaudit 2026-07 (H7): Foto-Strip je Punkt (MVP-023 §3). --}}
                        <tr>
                            <td colspan="4" class="bg-base-200/40">
                                <x-photo-strip :item="$item" :can-manage="(bool) $canManagePhotos" />
                            </td>
                        </tr>
                    @endif
                    @foreach ($item->children as $child)
                        <tr>
                            <td class="pl-8 text-sm">{{ $child->label }}</td>
                            <td>{{ $child->result?->label() ?? '—' }}</td>
                            <td class="text-sm text-base-content/70">{{ $child->note ?? '—' }}</td>
                            <td class="text-sm tabular-nums">{{ $child->measured_at?->fdatetime() ?? '—' }}</td>
                        </tr>
                    @endforeach
                @endforeach
            </x-table>
        @endif
    </x-card>

    <div class="grid gap-4 lg:grid-cols-2">
        <x-card :title="__('Signaturen')" icon="draw" :count="$protocol->signatures->count()">
            @if ($protocol->signatures->isEmpty())
                <x-empty-state icon='<span class="material-symbols-outlined" aria-hidden="true">draw</span>' :title="__('Noch keine Signaturen.')" compact />
            @else
                <ul class="divide-y divide-base-300 text-sm">
                    @foreach ($protocol->signatures as $signature)
                        <li class="flex items-center justify-between gap-2 py-2">
                            <div class="min-w-0">
                                <span class="font-medium">{{ $signature->signer_name }}</span>
                                <span class="text-base-content/60">· {{ $signature->role }}</span>
                            </div>
                            <span class="tabular-nums text-base-content/70">{{ $signature->signed_at?->fdatetime() }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif

            {{-- Externe Signatur-Links (Feature 012 MVP; Vollaudit 2026-07, M6):
                 offen/eingelöst/abgelaufen mit Widerruf für offene Links. --}}
            @if ($protocol->signatureTokens->isNotEmpty())
                <div class="mt-3 border-t border-base-300 pt-2">
                    <p class="mb-1 text-xs font-semibold uppercase tracking-wider text-base-content/50">{{ __('protocol.signature.tokenList') }}</p>
                    <ul class="divide-y divide-base-200 text-sm">
                        @foreach ($protocol->signatureTokens as $token)
                            <li class="flex items-center justify-between gap-2 py-1.5">
                                <div class="min-w-0">
                                    <span>{{ $token->signer_name ?? $token->signer_email ?? __('protocol.signature.externalLink') }}</span>
                                    <span class="text-base-content/60">· {{ $token->expires_at->fdatetime() }}</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    @if ($token->used_at !== null)
                                        <x-status-badge tone="success" size="xs">{{ __('protocol.signature.tokenUsed') }}</x-status-badge>
                                    @elseif (! $token->expires_at->isFuture())
                                        <x-status-badge tone="neutral" size="xs">{{ __('protocol.signature.tokenExpired') }}</x-status-badge>
                                    @else
                                        <x-status-badge tone="info" size="xs">{{ __('protocol.signature.tokenOpen') }}</x-status-badge>
                                        @can(\App\Enums\User\Permission::ProtocolSignatureRequest->value)
                                            <form method="POST" action="{{ route('protocols.signature-tokens.destroy', [$protocol, $token]) }}">
                                                @csrf @method('DELETE')
                                                <x-icon-btn icon="link_off" tone="ghost" size="xs" type="submit" :label="__('protocol.signature.revoke')" />
                                            </form>
                                        @endcan
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </x-card>

        <x-attachments-section :attachments="$protocol->attachments" />
    </div>

    {{-- Externe Beteiligte (Feature 033, Rang 28): Einladen/Widerrufen je Protokoll. --}}
    @include('external-participants._panel', ['subject' => $protocol, 'externalType' => 'protocol'])

    {{-- Vollaudit 2026-07 (M12): Kommunikationsnotizen am Protokoll (Spec §5). --}}
    @include('communication-notes._panel', ['notable' => $protocol, 'notableKind' => 'protocol'])

    <x-card :title="__('Verlauf')" icon="history" :count="$protocol->events->count()">
        @if ($protocol->events->isEmpty())
            <x-empty-state icon='<span class="material-symbols-outlined" aria-hidden="true">history</span>' :title="__('Keine Ereignisse.')" compact />
        @else
            <ul class="divide-y divide-base-300 text-sm">
                @foreach ($protocol->events as $event)
                    <li class="flex items-center justify-between gap-2 py-2">
                        <div class="min-w-0">
                            <span class="font-medium">{{ \App\Support\Trans::or('protocol.event.' . $event->event, $event->event) }}</span>
                            @if ($event->actor)
                                <span class="text-base-content/60">· {{ $event->actor->name }}</span>
                            @endif
                        </div>
                        <span class="tabular-nums text-base-content/70">{{ $event->created_at?->fdatetime() }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-card>
</x-page-shell>
@endsection
