{{--
    Eine Zeile des Kostengruppen-Pivots (MVP-648). Die Einrückung zeigt die
    Ebene; sichtbar ist die Zeile nur, solange jeder Vorfahr offen steht.
--}}
@php($hasChildren = ! empty($node['children']))
<tr @if ($ancestors !== []) x-show="visible({{ collect($ancestors)->map(fn ($c) => "'" . $c . "'")->implode(', ') }})" x-cloak @endif>
    <td>
        <span class="inline-flex items-center gap-1" style="padding-left: {{ ($node['level'] - 1) * 1.25 }}rem">
            @if ($hasChildren)
                <button type="button" class="btn btn-ghost btn-xs px-1"
                        x-on:click="toggle('{{ $node['code'] }}')"
                        x-bind:aria-expanded="isOpen('{{ $node['code'] }}')"
                        aria-label="{{ __('Ebene auf-/zuklappen') }}">
                    <span class="material-symbols-outlined text-base" aria-hidden="true"
                          x-text="caret('{{ $node['code'] }}')">chevron_right</span>
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
