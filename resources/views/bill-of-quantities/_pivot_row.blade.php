{{--
    Eine Zeile des Kostengruppen-Pivots (MVP-648). Die Einrückung zeigt die
    Ebene; sichtbar ist die Zeile nur, solange jeder Vorfahr offen steht.

    Die Alpine-Ausdrücke bekommen ausschließlich Zahlen: Katalogcodes stammen
    aus fremden GAEB-Dateien, und ein Code mit Apostroph brach vorher aus dem
    JS-String aus (Sicherheitsaudit 2026-09-17, xss-4).
--}}
@php
    $hasChildren = ! empty($node['children']);
    $nodeId = (int) ($ids[$node['code']] ?? -1);
    $ancestorIds = collect($ancestors)->map(fn ($code) => (int) ($ids[$code] ?? -1))->implode(', ');
@endphp
<tr @if ($ancestors !== []) x-show="visible({{ $ancestorIds }})" x-cloak @endif>
    <td>
        <span class="inline-flex items-center gap-1" style="padding-left: {{ ($node['level'] - 1) * 1.25 }}rem">
            @if ($hasChildren)
                <button type="button" class="btn btn-ghost btn-xs px-1"
                        x-on:click="toggle({{ $nodeId }})"
                        x-bind:aria-expanded="isOpen({{ $nodeId }})"
                        aria-label="{{ __('Ebene auf-/zuklappen') }}">
                    <x-icon name="chevron_right" class="text-base" x-text="caret({{ $nodeId }})" />
                </button>
            @else
                <span class="inline-block w-6" aria-hidden="true"></span>
            @endif
            <a class="link" href="{{ route('bill-of-quantities.catalog-assignment', [$bill, 'code' => $node['code']]) }}">
                <span class="font-mono">{{ $node['code'] }}</span> {{ $node['label'] }}
            </a>
        </span>
    </td>
    <td class="text-right tabular-nums @if ($node['level'] === 1) font-medium @endif">{{ $money($node['amount']) }}</td>
    <td class="text-right tabular-nums text-base-content/70">{{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat($node['share'], 1) }} %</td>
</tr>
