{{--
  Created on   : Tue Oct 13 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : document-relations.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Zugehörige Belege (MVP-1159): lokal und im Buchhaltungsprogramm, nach Datum. --}}
<details class="collapse collapse-arrow border border-base-300 bg-base-100" open data-document-relations>
    <summary class="collapse-title text-sm font-medium">{{ __('billing.relations.title', ['count' => count($relations)]) }}</summary>
    <div class="collapse-content overflow-x-auto">
        <x-table>
            <x-slot:head>
                <tr>
                    <th>{{ __('billing.relations.column.document') }}</th>
                    <th>{{ __('billing.relations.column.number') }}</th>
                    <th>{{ __('billing.relations.column.date') }}</th>
                    <th>{{ __('billing.relations.column.state') }}</th>
                    <th>{{ __('billing.relations.column.system') }}</th>
                    <th>{{ __('billing.relations.column.relation') }}</th>
                </tr>
            </x-slot:head>
            @foreach ($relations as $relation)
                <tr>
                    <td>{{ $relation['node']->label }}</td>
                    <td class="font-mono text-xs">
                        @if ($relation['node']->url !== null)
                            <a class="link" href="{{ $relation['node']->url }}" @if ($relation['node']->dialog) data-entry-modal-trigger @endif>{{ $relation['node']->number ?? '—' }}</a>
                        @else
                            {{ $relation['node']->number ?? '—' }}
                        @endif
                    </td>
                    <td>{{ $relation['node']->date?->fdate() ?? '—' }}</td>
                    <td>{{ $relation['node']->state ?? '—' }}</td>
                    <td>{{ $relation['node']->system ?? __('numbering.source.local') }}</td>
                    <td class="text-xs">
                        {{ $relation['kind']->label() }}
                        @if ($relation['via'] !== null)
                            <span class="text-muted">{{ __('billing.relations.via', ['document' => $relation['via']->label, 'number' => $relation['via']->number ?? '—']) }}</span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-table>
    </div>
</details>
