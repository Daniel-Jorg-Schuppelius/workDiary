{{--
  Created on   : Sun Oct 04 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _protocols_table.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Tabelle der Defektprotokolle eines Drilldowns ($protocols, paginiert).
--}}
<x-card>
    @if ($protocols->isEmpty())
        <x-empty-state icon="fact_check" :title="__('Keine Defektprotokolle für diesen Drilldown gefunden.')" />
    @else
        <x-table bare table-sort="client">
            <x-slot:head>
                <tr>
                    <x-table.th sort type="string">{{ __('Titel') }}</x-table.th>
                    <x-table.th sort type="string">{{ __('Status') }}</x-table.th>
                    <x-table.th sort type="date">{{ __('Zeitpunkt') }}</x-table.th>
                    <x-table.th sort type="string">{{ __('Erstellt von') }}</x-table.th>
                    <x-table.th sort type="string">{{ __('Auftrag') }}</x-table.th>
                </tr>
            </x-slot:head>
            @foreach ($protocols as $protocol)
                <tr>
                    <td class="font-medium">{{ $protocol->title }}</td>
                    <td><x-status-badge tone="ghost" outline>{{ $protocol->status->label() }}</x-status-badge></td>
                    <td>{{ $protocol->occurred_at?->fdatetime() ?? '—' }}</td>
                    <td>{{ $protocol->creator?->name ?? '—' }}</td>
                    <td>
                        <x-order-link :entry="$protocol->subject" link-class="link link-hover">{{ $protocol->subject?->title ?? ($protocol->subject ? '#' . $protocol->subject->sqid : '—') }}</x-order-link>
                    </td>
                </tr>
            @endforeach
        </x-table>

        <x-pagination :paginator="$protocols" standing />
    @endif
</x-card>
