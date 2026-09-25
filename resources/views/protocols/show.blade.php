{{--
  Created on   : Tue Jul 07 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Protokoll-Detailseite: Stammdaten, Positionen (Erfassung über Dialoge,
  MVP-883), Signaturen, Wetter-Nachweis (Rang 10), Anhänge, Verlauf und das
  Externe-Beteiligte-Panel (Feature 033).
--}}

@extends('layouts.app')
@section('title', $protocol->title)
@section('nav-title', __('Protokoll'))

@section('content')
@php
    /** @var \App\Models\Protocol\Protocol $protocol */
    $subject = $protocol->subject;
    $subjectLabel = $subject?->title ?? $subject?->name;
@endphp
<x-page-shell>
    <x-slot:toolbar>
        <x-page-toolbar>
            <x-slot:title>{{ $protocol->title }}</x-slot:title>
            {{-- Der Glossarbegriff ist Markup und gehört deshalb in den Standard-Slot,
                 nicht in :subtitle — den setzt die Toolbar zusätzlich als title-Attribut. --}}
            <span class="text-xs">
                <x-term :glossary="$protocol->type === \App\Enums\Protocol\ProtocolType::Acceptance ? 'abnahme' : null">{{ $protocol->type->label() }}</x-term> · {{ \App\Support\EntityType::label($protocol->subject_type) }}@if ($subjectLabel !== null): {{ $subjectLabel }}@endif
            </span>
            <x-slot:actions>
                <x-status-badge size="sm">{{ $protocol->status->label() }}</x-status-badge>
                @can('requestReview', $protocol)
                    <x-action-form :action="route('protocols.transition', [$protocol, 'requestReview'])">
                        <x-icon-btn icon="rate_review" size="sm" type="submit" show-label>{{ __('protocol.action.requestReview') }}</x-icon-btn>
                    </x-action-form>
                @endcan
                @can('returnToDraft', $protocol)
                    <x-icon-btn icon="undo" size="sm" data-entry-modal-trigger :href="route('protocols.transition-form', [$protocol, 'returnToDraft'])" show-label>{{ __('protocol.action.returnToDraft') }}</x-icon-btn>
                @endcan
                @can('sign', $protocol)
                    <x-icon-btn icon="draw" tone="primary" size="sm" data-entry-modal-trigger :href="route('protocols.transition-form', [$protocol, 'sign'])" show-label>{{ __('protocol.action.sign') }}</x-icon-btn>
                    @can(\App\Enums\User\Permission::ProtocolSignatureRequest->value)
                        <x-icon-btn icon="link" size="sm" data-entry-modal-trigger :href="route('protocols.signature-tokens.create', $protocol)" show-label>{{ __('protocol.dialog.token_title') }}</x-icon-btn>
                    @endcan
                @endcan
                @can('supersede', $protocol)
                    <x-icon-btn icon="history_edu" size="sm" data-entry-modal-trigger :href="route('protocols.transition-form', [$protocol, 'supersede'])" show-label>{{ __('protocol.action.supersede') }}</x-icon-btn>
                @endcan
                @can('archive', $protocol)
                    <x-action-form :action="route('protocols.transition', [$protocol, 'archive'])" :confirm="__('protocol.dialog.archive_confirm')">
                        <x-icon-btn icon="archive" size="sm" type="submit" show-label>{{ __('protocol.action.archive') }}</x-icon-btn>
                    </x-action-form>
                @endcan
                @can(\App\Enums\User\Permission::ProtocolTemplateManage->value)
                    <x-icon-btn icon="library_add" size="sm" data-entry-modal-trigger :href="route('protocols.as-template.form', $protocol)" show-label>{{ __('protocol.template.save_title') }}</x-icon-btn>
                @endcan
                <x-icon-btn icon="picture_as_pdf" tone="outline" size="sm" :href="route('protocols.pdf', $protocol)" show-label>{{ __('PDF') }}</x-icon-btn>
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    @if (session('protocol.signature.token_url'))
        <div class="alert alert-info">
            <x-icon name="link" />
            <div class="min-w-0">
                <p class="text-sm">{{ __('protocol.dialog.token_url_hint') }}</p>
                <code class="block break-all text-xs">{{ session('protocol.signature.token_url') }}</code>
            </div>
        </div>
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
                    <p class="text-sm text-muted">{{ __('Kein Wetter-Snapshot vorhanden.') }}</p>
                    @can('update', $protocol)
                        <x-action-form :action="route('protocols.weather', $protocol)" method="POST">
                            <x-icon-btn icon="cloud_download" tone="outline" size="sm" type="submit" show-label>{{ __('Wetter abrufen') }}</x-icon-btn>
                        </x-action-form>
                    @endcan
                </div>
            @endif
        </x-card>
    </div>

    @php
        // KI-Welle 1 (Feature 143, MVP-711): Vorschläge nur in bearbeitbaren
        // Protokollen und nur, wenn die Capability für diese Org nutzbar ist.
        $aiViewData = app(\App\Services\Ai\Contracts\SuggestionView::class);
        $aiEditable = (bool) (auth()->user()?->can('update', $protocol)) && $protocol->status->isEditable();
        $aiTextUsable = $aiEditable && $aiViewData->capabilityUsable(\App\Services\Ai\Suggestions\ProtocolTextSuggestionService::CAPABILITY_TEXT);
        $aiClassifyUsable = $aiEditable && $aiViewData->capabilityUsable(\App\Services\Ai\Suggestions\ProtocolTextSuggestionService::CAPABILITY_CLASSIFY);
        $aiItems = $protocol->items->flatMap(fn ($i) => collect([$i])->merge($i->children));
        $aiMorph = (new \App\Models\Protocol\ProtocolItem)->getMorphClass();
        $aiTextSuggestions = $aiTextUsable ? $aiViewData->openSuggestionsFor($aiMorph, $aiItems, \App\Services\Ai\Suggestions\ProtocolTextSuggestionService::CAPABILITY_TEXT) : collect();
        $aiClassifySuggestions = $aiClassifyUsable ? $aiViewData->openSuggestionsFor($aiMorph, $aiItems, \App\Services\Ai\Suggestions\ProtocolTextSuggestionService::CAPABILITY_CLASSIFY) : collect();
        // Erfassung (MVP-883): Ausfüllen/Entfernen nur im bearbeitbaren Entwurf.
        $canEditItems = $aiEditable;
        $aiActions = $aiTextUsable || $aiClassifyUsable || $canEditItems;
        $aiColumns = $aiActions ? 6 : 5;
        // Erfasster Wert je Punkt über den Feldschema-Adapter (wie im PDF, MVP-867).
        $itemFields = app(\App\Services\Protocol\Fields\ProtocolItemFields::class);
    @endphp
    <x-card :title="__('Positionen')" icon="checklist" :count="$protocol->items->count()">
        @if ($canEditItems)
            <x-slot:actions>
                <x-icon-btn icon="playlist_add" tone="primary" size="sm" data-entry-modal-trigger :href="route('protocols.items.create', $protocol)" show-label>{{ __('protocol.action.addItem') }}</x-icon-btn>
            </x-slot:actions>
        @endif
        @if ($protocol->items->isEmpty())
            <x-empty-state icon="checklist" :title="__('Keine Positionen erfasst.')" compact />
        @else
            <x-table bare>
                <x-slot:head>
                    <tr>
                        <th>{{ __('Position') }}</th>
                        <th>{{ __('Ergebnis') }}</th>
                        <th>{{ __('Wert') }}</th>
                        <th>{{ __('Notiz') }}</th>
                        <th>{{ __('Gemessen') }}</th>
                        @if ($aiActions)
                            <th class="text-right">{{ __('Aktionen') }}</th>
                        @endif
                    </tr>
                </x-slot:head>
                @foreach ($protocol->items as $item)
                    <tr>
                        <td class="font-medium">{{ $item->label }}</td>
                        <td>{{ $item->result?->label() ?? '—' }}</td>
                        <td class="text-sm"><x-field-display :field="$itemFields->definition($item)" :values="$itemFields->values($item)" /></td>
                        <td class="text-sm text-base-content/70">{{ $item->note ?? '—' }}</td>
                        <td class="text-sm tabular-nums">{{ $item->measured_at?->fdatetime() ?? '—' }}</td>
                        @if ($aiActions)
                            <td class="text-right whitespace-nowrap">
                                <div class="flex justify-end gap-1">
                                    @if ($canEditItems)
                                        <x-icon-btn icon="edit_note" size="xs" data-entry-modal-trigger :href="route('protocols.items.fill-form', $item)" :title="__('protocol.action.fillItem')" />
                                        <x-action-form :action="route('protocols.items.destroy', $item)" method="DELETE" :confirm="__('protocol.dialog.remove_confirm', ['label' => $item->label])">
                                            <x-icon-btn icon="delete" size="xs" tone="error" type="submit" :title="__('protocol.action.removeItem')" />
                                        </x-action-form>
                                    @endif
                                    @if ($aiTextUsable)
                                        <x-action-form :action="route('ai.suggestions.protocol-item', $item)">
                                            <x-icon-btn icon="auto_awesome" size="xs" tone="info" type="submit" :title="__('ai.suggestion.suggest_protocol_item')" />
                                        </x-action-form>
                                    @endif
                                    @if ($aiClassifyUsable)
                                        <x-action-form :action="route('ai.suggestions.protocol-item-classify', $item)">
                                            <x-icon-btn icon="label" size="xs" tone="info" type="submit" :title="__('ai.suggestion.classify_protocol_item')" />
                                        </x-action-form>
                                    @endif
                                </div>
                            </td>
                        @endif
                    </tr>
                    @if ($aiActions)
                        @include('protocols._item_ai_rows', ['item' => $item])
                    @endif
                    @php($canManagePhotos = auth()->user()?->can('update', $protocol) && $protocol->status->isEditable())
                    @if ($item->photos->isNotEmpty() || $canManagePhotos)
                        {{-- Vollaudit 2026-07 (H7): Foto-Strip je Punkt (MVP-023 §3). --}}
                        <tr>
                            <td colspan="{{ $aiColumns }}" class="bg-base-200/40">
                                <x-photo-strip :item="$item" :can-manage="(bool) $canManagePhotos" />
                            </td>
                        </tr>
                    @endif
                    @foreach ($item->children as $child)
                        <tr>
                            <td class="pl-8 text-sm">{{ $child->label }}</td>
                            <td>{{ $child->result?->label() ?? '—' }}</td>
                            <td class="text-sm"><x-field-display :field="$itemFields->definition($child)" :values="$itemFields->values($child)" /></td>
                            <td class="text-sm text-base-content/70">{{ $child->note ?? '—' }}</td>
                            <td class="text-sm tabular-nums">{{ $child->measured_at?->fdatetime() ?? '—' }}</td>
                            @if ($aiActions)
                                <td class="text-right whitespace-nowrap">
                                    <div class="flex justify-end gap-1">
                                        @if ($canEditItems)
                                            <x-icon-btn icon="edit_note" size="xs" data-entry-modal-trigger :href="route('protocols.items.fill-form', $child)" :title="__('protocol.action.fillItem')" />
                                            <x-action-form :action="route('protocols.items.destroy', $child)" method="DELETE" :confirm="__('protocol.dialog.remove_confirm', ['label' => $child->label])">
                                                <x-icon-btn icon="delete" size="xs" tone="error" type="submit" :title="__('protocol.action.removeItem')" />
                                            </x-action-form>
                                        @endif
                                        @if ($aiTextUsable)
                                            <x-action-form :action="route('ai.suggestions.protocol-item', $child)">
                                                <x-icon-btn icon="auto_awesome" size="xs" tone="info" type="submit" :title="__('ai.suggestion.suggest_protocol_item')" />
                                            </x-action-form>
                                        @endif
                                        @if ($aiClassifyUsable)
                                            <x-action-form :action="route('ai.suggestions.protocol-item-classify', $child)">
                                                <x-icon-btn icon="label" size="xs" tone="info" type="submit" :title="__('ai.suggestion.classify_protocol_item')" />
                                            </x-action-form>
                                        @endif
                                    </div>
                                </td>
                            @endif
                        </tr>
                        @if ($aiActions)
                            @include('protocols._item_ai_rows', ['item' => $child])
                        @endif
                    @endforeach
                @endforeach
            </x-table>
        @endif
    </x-card>

    <div class="grid gap-4 lg:grid-cols-2">
        <x-card :title="__('Signaturen')" icon="draw" :count="$protocol->signatures->count()">
            @if ($protocol->signatures->isEmpty())
                <x-empty-state icon="draw" :title="__('Noch keine Signaturen.')" compact />
            @else
                <ul class="divide-y divide-base-300 text-sm">
                    @foreach ($protocol->signatures as $signature)
                        <li class="flex items-center justify-between gap-2 py-2">
                            <div class="min-w-0">
                                <span class="font-medium">{{ $signature->signer_name }}</span>
                                <span class="text-muted">· {{ $signature->role }}</span>
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
                    <p class="mb-1 text-xs font-semibold uppercase tracking-wider text-muted">{{ __('protocol.signature.tokenList') }}</p>
                    <ul class="divide-y divide-base-200 text-sm">
                        @foreach ($protocol->signatureTokens as $token)
                            <li class="flex items-center justify-between gap-2 py-1.5">
                                <div class="min-w-0">
                                    <span>{{ $token->signer_name ?? $token->signer_email ?? __('protocol.signature.externalLink') }}</span>
                                    <span class="text-muted">· {{ $token->expires_at->fdatetime() }}</span>
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

    <x-card :title="__('Verlauf')" icon="history" :count="$protocol->journal->count()">
        <x-journal :entries="$protocol->journal" :empty-text="__('Keine Ereignisse.')" />
    </x-card>
</x-page-shell>
@endsection
