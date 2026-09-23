{{--
  Created on   : Mon Sep 21 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _tab_decided.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Reiter „Entschieden“. Variable: $decided (Paginator) --}}
@if ($decided->isEmpty())
    <x-empty-state framed icon="task_alt" :title="__('Noch nichts entschieden.')" />
@else
    <x-table scroll="flex" :caption="__('Entschieden')">
        <x-slot:head>
            <tr>
                <th>{{ __('Termin') }}</th>
                <th>{{ __('Kunde') }}</th>
                <th>{{ __('Leistung') }}</th>
                <th>{{ __('Status') }}</th>
                <th>{{ __('Entschieden') }}</th>
                <th>{{ __('Hinweis') }}</th>
            </tr>
        </x-slot:head>
        @foreach ($decided as $request)
            <tr class="hover">
                @include('appointments._request_cells', ['request' => $request])
                <td><x-status-badge :tone="$request->statusTone()" size="sm">{{ $request->statusLabel() }}</x-status-badge></td>
                <td>
                    <div class="whitespace-nowrap tabular-nums">{{ $request->decided_at?->fdatetime() ?? '—' }}</div>
                    @if ($request->decidedBy)
                        <div class="text-xs text-muted">{{ $request->decidedBy->name }}</div>
                    @endif
                </td>
                <td class="max-w-72">
                    @php($entryUrl = \App\Support\EntityUrl::byType(\App\Models\DiaryEntry::class, $request->diary_entry_id))
                    @if ($request->status === \App\Models\Calendar\AppointmentRequest::STATUS_DECLINED && filled($request->decline_reason))
                        <span class="line-clamp-2" title="{{ $request->decline_reason }}">{{ $request->decline_reason }}</span>
                    @elseif ($entryUrl !== null)
                        <a href="{{ $entryUrl }}" class="link link-hover inline-flex items-center gap-1">
                            <x-icon name="open_in_new" size="1em" />{{ __('Eintrag öffnen') }}
                        </a>
                    @else
                        <span class="text-muted">—</span>
                    @endif
                </td>
            </tr>
        @endforeach
    </x-table>

    <x-pagination :paginator="$decided" standing />
@endif
