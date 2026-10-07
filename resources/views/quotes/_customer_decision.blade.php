{{--
  Created on   : Wed Oct 07 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _customer_decision.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Angebot aus Kundensicht: Positionen, Summe, Bedingungen und Entscheidung —
  Token-Link (MVP-170) und Kundenportal-Eingang (MVP-1075) teilen dieses Markup.
  Variablen: $quote (Quote), $action (?string, null = keine Entscheidung möglich),
  $hidden (array<string,string> versteckte Felder), $withReason (bool)
--}}
    <x-table>
        <x-slot:head>
                <tr>
                    <th>#</th>
                    <th>{{ __('Beschreibung') }}</th>
                    <th class="text-right">{{ __('Menge') }}</th>
                    <th class="text-right">{{ __('Einzelpreis') }}</th>
                    <th>{{ __('Art') }}</th>
                </tr>
        </x-slot:head>
        <x-slot:foot>
                <tr><td colspan="3" class="text-right font-bold">{{ __('Gesamt (netto zzgl. USt.)') }}</td><td class="text-right font-bold" colspan="2">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat(($quote->subtotal?->toFloat() ?? 0.0), 2, withThousandsSeparator: true) }} EUR</td></tr>
        </x-slot:foot>
                @foreach (\App\Services\Billing\DocumentOutline::rows($quote->items, fn ($line): bool => $line->countsInTotal()) as $outlineRow)
                    @if ($outlineRow['type'] === 'subtotal')
                        <tr><td></td><td colspan="4" class="text-right text-sm">{{ __('invoicing.line_kind.subtotal', ['number' => $outlineRow['number'], 'title' => $outlineRow['title']->description]) }}: <span class="font-semibold">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($outlineRow['amount']->toFloat(), 2, withThousandsSeparator: true) }} EUR</span></td></tr>
                        @continue
                    @endif
                    @php
                        $item = $outlineRow['line'];
                        $kind = $item->lineKind();
                    @endphp
                    <tr>
                        <td>{{ $outlineRow['number'] }}</td>
                        @if (! $kind->isPriced())
                            <td colspan="4" class="{{ $kind === \App\Enums\Billing\DocumentLineKind::Title ? 'font-semibold' : 'italic whitespace-pre-line' }}">{{ $item->description }}</td>
                        @else
                            <td>{{ $item->description }}</td>
                            <td class="text-right">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $item->quantity, 2, withThousandsSeparator: true) }} {{ $item->unit }}</td>
                            <td class="text-right">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat(($item->unit_price?->toFloat() ?? 0.0), 2, withThousandsSeparator: true) }} EUR</td>
                            <td>{{ $kind === \App\Enums\Billing\DocumentLineKind::Alternative ? $kind->label() : ($item->optional ? __('Option') : __('Pflicht')) }}</td>
                        @endif
                    </tr>
                @endforeach
    </x-table>

    @if ($quote->terms)
        <div class="rounded-box bg-base-100 p-4 shadow">
            <p class="whitespace-pre-line text-sm">{{ $quote->terms }}</p>
        </div>
    @endif

    @if ($quote->status->isDecided())
        <div role="status" class="alert alert-info">
            {{ __('Zu diesem Angebot liegt bereits eine Entscheidung vor (:status, :date).', [
                'status' => $quote->status->label(),
                'date' => optional($quote->decided_at)->fdatetime() ?? '—',
            ]) }}
        </div>
    @elseif ($action !== null && $quote->status === \App\Enums\Sales\QuoteStatus::Sent && ! $quote->isExpired())
        <form method="POST" action="{{ $action }}" class="rounded-box bg-base-100 p-4 shadow space-y-3">
            @csrf
            @foreach ($hidden as $hiddenName => $hiddenValue)
                <input type="hidden" name="{{ $hiddenName }}" value="{{ $hiddenValue }}">
            @endforeach
            <h2 class="text-sm font-semibold">{{ __('Ihre Entscheidung') }}</h2>
            <div class="space-y-1">
                @foreach (\App\Services\Billing\DocumentOutline::rows($quote->items) as $outlineRow)
                    @if ($outlineRow['type'] === 'line' && $outlineRow['line']->lineKind()->isPriced())
                        @php
                            $item = $outlineRow['line'];
                            $isChoice = $item->optional || $item->lineKind() === \App\Enums\Billing\DocumentLineKind::Alternative;
                        @endphp
                        <label class="label cursor-pointer justify-start gap-2">
                            <input type="checkbox" name="item_ids[]" value="{{ $item->sqid }}" class="checkbox checkbox-sm" @checked(! $isChoice)>
                            <span class="label-text">{{ $outlineRow['number'] }}. {{ $item->description }} @if ($isChoice)<span class="text-xs text-muted">({{ $item->lineKind() === \App\Enums\Billing\DocumentLineKind::Alternative ? $item->lineKind()->label() : __('Option') }})</span>@endif</span>
                        </label>
                    @endif
                @endforeach
            </div>
            @if ($withReason)
                <label class="form-control">
                    <span class="label-text">{{ __('customer_intake.quote.reject_reason') }}</span>
                    <textarea name="reason" rows="2" maxlength="1000" class="textarea textarea-bordered w-full">{{ old('reason') }}</textarea>
                </label>
            @endif
            <div class="flex flex-wrap gap-2">
                <x-button type="submit" name="decision" value="accept">{{ __('Angebot annehmen') }}</x-button>
                <x-button type="submit" tone="outline" name="decision" value="reject">{{ __('Angebot ablehnen') }}</x-button>
            </div>
            <p class="text-xs text-muted">{{ __('Ihre Auswahl wird mit Zeitstempel dokumentiert. Abgewählte Positionen gelten als nicht beauftragt.') }}</p>
        </form>
    @elseif ($quote->isExpired())
        <div role="alert" class="alert alert-warning">{{ __('Die Bindefrist dieses Angebots ist abgelaufen — bitte kontaktieren Sie uns für eine neue Version.') }}</div>
    @endif
