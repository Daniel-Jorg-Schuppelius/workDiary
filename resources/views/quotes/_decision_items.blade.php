{{-- Wählbare Positionen der Angebotsentscheidung, innen (MVP-170) und im Kundenportal dieselbe Regel: bepreiste Zeilen, Optionen und Alternativen abgewählt. --}}
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
