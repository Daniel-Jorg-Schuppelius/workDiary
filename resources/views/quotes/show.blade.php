@extends('layouts.app')

@section('title', __('Angebot :nr', ['nr' => $quote->number]))
@section('nav-title', $quote->number)

@section('content')
<x-page-shell>
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-error">{{ session('error') }}</div>
    @endif

    @if (session('acceptance_url'))
        <div class="alert alert-info">
            <span class="material-symbols-outlined" aria-hidden="true">link</span>
            <div class="min-w-0">
                <div class="font-bold">{{ __('Annahme-Link für den Kunden (wird nur EINMAL angezeigt):') }}</div>
                <div class="break-all font-mono text-xs">{{ session('acceptance_url') }}</div>
                <div class="text-xs">{{ __('Nur der Hash ist gespeichert — Link jetzt kopieren und mit dem Angebot versenden.') }}</div>
            </div>
        </div>
    @endif

    @if ($quote->isExpired())
        <div class="alert alert-warning text-sm">
            <span class="material-symbols-outlined" aria-hidden="true">timer_off</span>
            {{ __('Die Bindefrist (:date) ist abgelaufen.', ['date' => optional($quote->valid_until)->fdate()]) }}
        </div>
    @endif

    <x-page-toolbar :title="__('Angebot') . ' ' . $quote->number . ' · V' . $quote->version" :badge="__('values.' . $quote->status)" badge-tone="outline">
        <div class="text-sm text-base-content/70">{{ $quote->customer->name }}</div>
        @if ($quote->valid_until)
            <div class="text-sm text-base-content/70">{{ __('Bindefrist: :date', ['date' => $quote->valid_until->fdate()]) }}</div>
        @endif
        <x-slot:actions>
            @can('update', $quote)
                <x-icon-btn icon="add" tone="primary" size="sm"
                            data-entry-modal-trigger
                            :href="route('quotes.items.create', $quote)"
                            show-label>{{ __('Position hinzufügen') }}</x-icon-btn>
            @endcan
            @can('approve', $quote)
                <x-action-form :action="route('quotes.approve', $quote)">
                    <x-icon-btn icon="verified" tone="info" size="sm" type="submit" show-label>{{ __('Freigeben') }}</x-icon-btn>
                </x-action-form>
            @endcan
            @can('send', $quote)
                <x-action-form :action="route('quotes.send', $quote)"
                      :confirm="__('Angebot als versendet markieren? Danach sind Änderungen nur noch als neue Version möglich.')"
                      confirm-icon="send"
                      confirm-tone="primary"
                      :confirm-label="__('Versenden')">
                    <x-icon-btn icon="send" tone="primary" size="sm" type="submit" show-label>{{ __('Versenden') }}</x-icon-btn>
                </x-action-form>
            @endcan
            @if (in_array($quote->status, ['sent', 'rejected', 'expired'], true))
                @can('decide', $quote)
                    <x-action-form :action="route('quotes.new-version', $quote)">
                        <x-icon-btn icon="difference" tone="info" size="sm" type="submit" show-label>{{ __('Neue Version') }}</x-icon-btn>
                    </x-action-form>
                @endcan
            @endif
            @can('convert', $quote)
                <x-action-form :action="route('quotes.convert', $quote)"
                      :confirm="__('Angenommene Positionen in eine Entwurfsrechnung überführen?')"
                      confirm-icon="receipt_long"
                      confirm-tone="primary"
                      :confirm-label="__('Überführen')">
                    <x-icon-btn icon="receipt_long" tone="primary" size="sm" type="submit" show-label>{{ __('In Rechnung überführen') }}</x-icon-btn>
                </x-action-form>
            @endcan
            @can('delete', $quote)
                <x-action-form :action="route('quotes.destroy', $quote)" method="DELETE"
                      :confirm="__('Angebots-Entwurf wirklich löschen?')"
                      confirm-icon="delete"
                      confirm-tone="error"
                      :confirm-label="__('Löschen')">
                    <x-icon-btn icon="delete" tone="error" size="sm" type="submit" show-label>{{ __('Löschen') }}</x-icon-btn>
                </x-action-form>
            @endcan
        </x-slot:actions>
    </x-page-toolbar>

    @if ($previousVersion !== null || $newerVersions->isNotEmpty() || $invoices->isNotEmpty())
        <div class="flex flex-wrap gap-4 text-sm text-base-content/70">
            @if ($previousVersion !== null)
                <span>{{ __('Vorherige Version:') }} <a class="link" href="{{ route('quotes.show', $previousVersion) }}">V{{ $previousVersion->version }}</a></span>
            @endif
            @foreach ($newerVersions as $newer)
                <span>{{ __('Neuere Version:') }} <a class="link" href="{{ route('quotes.show', $newer) }}">V{{ $newer->version }} ({{ __('values.' . $newer->status) }})</a></span>
            @endforeach
            @foreach ($invoices as $inv)
                <span>{{ __('Rechnung:') }} <a class="link" href="{{ route('invoices.show', $inv) }}">{{ $inv->number }}</a></span>
            @endforeach
        </div>
    @endif

    {{-- KI-Leistungstexte (Feature 084, MVP-405): Vorschläge nur im Entwurf. --}}
    @php
        $aiViewData = app(\App\Services\Ai\Suggestions\SuggestionViewData::class);
        $aiDraft = $quote->status === 'draft' && auth()->user()?->can('update', $quote);
        $aiSuggestEnabled = $aiDraft && $aiViewData->capabilityUsable(\App\Services\Ai\Suggestions\ItemTextSuggestionService::CAPABILITY_QUOTE_ITEM);
        $aiSuggestions = $aiSuggestEnabled
            ? $aiViewData->openSuggestionsFor((new \App\Models\QuoteItem)->getMorphClass(), $quote->items)
            : collect();
        $aiColspan = 7 + ($quote->decided_at !== null ? 1 : 0);
    @endphp
    @include('ai._learn_prompt')

    <x-table>
        <x-slot:head>
            <tr>
                <th>#</th>
                <th>{{ __('Beschreibung') }}</th>
                <th class="text-right">{{ __('Menge') }}</th>
                <th class="text-right">{{ __('Einzelpreis') }}</th>
                <th class="text-right">{{ __('USt %') }}</th>
                <th>{{ __('Art') }}</th>
                @if ($quote->decided_at !== null)<th>{{ __('Annahme') }}</th>@endif
                @can('update', $quote)<th class="text-right">{{ __('Aktionen') }}</th>@endcan
            </tr>
        </x-slot:head>
        <x-slot:foot>
            <tr><td colspan="4" class="text-right">{{ __('Zwischensumme') }}</td><td class="text-right" colspan="3">{{ number_format((float) $quote->subtotal, 2, ',', '.') }} EUR</td></tr>
            <tr><td colspan="4" class="text-right">{{ __('USt.') }}</td><td class="text-right" colspan="3">{{ number_format((float) $quote->tax_amount, 2, ',', '.') }} EUR</td></tr>
            <tr><td colspan="4" class="text-right font-bold">{{ __('Gesamt') }}</td><td class="text-right font-bold" colspan="3">{{ number_format((float) $quote->total, 2, ',', '.') }} EUR</td></tr>
        </x-slot:foot>
        @forelse ($quote->items as $item)
            <tr>
                <td>{{ $item->position }}</td>
                <td>{{ $item->description }}</td>
                <td class="text-right">{{ number_format((float) $item->quantity, 2, ',', '.') }} {{ $item->unit }}</td>
                <td class="text-right">{{ number_format((float) $item->unit_price, 2, ',', '.') }} EUR</td>
                <td class="text-right">{{ $item->tax_rate !== null ? rtrim(rtrim((string) $item->tax_rate, '0'), '.') : '—' }}</td>
                <td>{{ $item->optional ? __('Option') : __('Pflicht') }}</td>
                @if ($quote->decided_at !== null)
                    <td>{{ $item->accepted === null ? '—' : ($item->accepted ? __('angenommen') : __('nicht angenommen')) }}</td>
                @endif
                @can('update', $quote)
                    <td class="text-right whitespace-nowrap">
                        @if ($aiSuggestEnabled)
                            <x-action-form :action="route('ai.suggestions.quote-item', [$quote, $item])">
                                <x-icon-btn icon="auto_awesome" size="xs" tone="info" type="submit" :title="__('ai.suggestion.suggest')" />
                            </x-action-form>
                        @endif
                        <x-icon-btn icon="edit" size="xs" tone="ghost"
                                    data-entry-modal-trigger
                                    :href="route('quotes.items.edit', [$quote, $item])"
                                    :title="__('Bearbeiten')" />
                        <x-action-form :action="route('quotes.items.destroy', [$quote, $item])" method="DELETE"
                              :confirm="__('Position wirklich entfernen?')"
                              confirm-icon="delete"
                              confirm-tone="error"
                              :confirm-label="__('Entfernen')">
                            <x-icon-btn icon="delete" size="xs" tone="error" type="submit" :title="__('Entfernen')" />
                        </x-action-form>
                    </td>
                @endcan
            </tr>
            @if ($aiDraft && ($aiSuggestions[$item->id] ?? null) !== null)
                <tr data-ai-suggestion-row>
                    <td colspan="{{ $aiColspan }}">
                        <x-ai-suggestion
                            :original="$aiSuggestions[$item->id]->original"
                            :suggestion="$aiSuggestions[$item->id]->suggestion"
                            :provider="$aiSuggestions[$item->id]->provider"
                            :fallback="$aiSuggestions[$item->id]->fallback_used"
                            :cached="$aiSuggestions[$item->id]->from_cache"
                            :accept-action="route('ai.suggestions.accept', $aiSuggestions[$item->id])"
                            :reject-action="route('ai.suggestions.reject', $aiSuggestions[$item->id])"
                            field-name="text"
                        />
                    </td>
                </tr>
            @endif
        @empty
            <x-table.empty icon='<span class="material-symbols-outlined" aria-hidden="true">request_quote</span>' :colspan="6" :title="__('Keine Positionen.')" compact />
        @endforelse
    </x-table>

    @if ($quote->terms)
        <x-card :title="__('Bedingungen / Leistungsumfang')">
            <p class="whitespace-pre-line text-sm">{{ $quote->terms }}</p>
        </x-card>
    @endif

    {{-- Interne Entscheidung dokumentieren (MVP-170): Annahme/Teilannahme/Ablehnung ohne Portal --}}
    @if ($quote->status === 'sent')
        @can('decide', $quote)
            <x-card :title="__('Entscheidung dokumentieren (telefonisch/schriftlich erhalten)')">
                <form method="POST" action="{{ route('quotes.decide', $quote) }}" class="space-y-3">
                    @csrf
                    <div class="space-y-1">
                        @foreach ($quote->items as $item)
                            <label class="label cursor-pointer justify-start gap-2">
                                <input type="checkbox" name="item_ids[]" value="{{ $item->id }}" class="checkbox checkbox-sm" @checked(! $item->optional)>
                                <span class="label-text">{{ $item->position }}. {{ $item->description }} @if ($item->optional)<span class="text-xs text-base-content/60">({{ __('Option') }})</span>@endif</span>
                            </label>
                        @endforeach
                    </div>
                    <input name="reason" maxlength="1000" class="input input-sm input-bordered w-full" placeholder="{{ __('Grund (bei Ablehnung)') }}">
                    <div class="flex gap-2">
                        <button type="submit" name="decision" value="accept" class="btn btn-primary btn-sm">{{ __('Annahme dokumentieren') }}</button>
                        <button type="submit" name="decision" value="reject" class="btn btn-outline btn-sm">{{ __('Ablehnung dokumentieren') }}</button>
                    </div>
                    <p class="text-xs text-base-content/60">{{ __('Die Auswahl der Positionen bestimmt Voll- oder Teilannahme; der Stand wird eingefroren (kein Rückfluss).') }}</p>
                </form>
            </x-card>
        @endcan
    @endif

    @if ($quote->decided_at !== null)
        <div class="text-sm text-base-content/70">{{ __('Entschieden am :date.', ['date' => $quote->decided_at->fdatetime()]) }}</div>
    @endif
</x-page-shell>
@endsection
