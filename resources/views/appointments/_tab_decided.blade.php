{{--
  Created on   : Mon Sep 21 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _tab_decided.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Reiter „Entschieden“. Variablen: $decided (Paginator), $canManage --}}
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
                @if ($canManage)
                    <th class="text-right">{{ __('Aktionen') }}</th>
                @endif
            </tr>
        </x-slot:head>
        @foreach ($decided as $request)
            <tr class="hover">
                @include('appointments._request_cells', ['request' => $request])
                <td><x-status-badge :tone="$request->status->tone()" size="sm">{{ $request->status->label() }}</x-status-badge></td>
                <td>
                    <div class="whitespace-nowrap tabular-nums">{{ $request->decided_at?->fdatetime() ?? '—' }}</div>
                    @if ($request->decidedBy)
                        <div class="text-xs text-muted">{{ $request->decidedBy->name }}</div>
                    @endif
                </td>
                <td class="max-w-72">
                    @php($entryUrl = \App\Support\EntityUrl::byType(\App\Models\Diary\DiaryEntry::class, $request->diary_entry_id))
                    @if ($request->status === \App\Enums\Calendar\AppointmentRequestStatus::Declined && filled($request->decline_reason))
                        <span class="line-clamp-2" title="{{ $request->decline_reason }}">{{ $request->decline_reason }}</span>
                    @elseif ($entryUrl !== null)
                        <a href="{{ $entryUrl }}" class="link link-hover inline-flex items-center gap-1">
                            <x-icon name="open_in_new" size="1em" />{{ __('Eintrag öffnen') }}
                        </a>
                    @else
                        <span class="text-muted">—</span>
                    @endif
                </td>
                @if ($canManage)
                    <td class="text-right">
                        @if ($request->status === \App\Enums\Calendar\AppointmentRequestStatus::Confirmed && $request->start_at?->isFuture())
                            <x-icon-btn icon="event_repeat" size="sm" show-label
                                        data-open-dialog="appointment-reschedule-{{ $request->id }}">{{ __('appointment.reschedule.action') }}</x-icon-btn>
                        @endif
                    </td>
                @endif
            </tr>
        @endforeach
    </x-table>

    @if ($canManage)
        @foreach ($decided as $request)
            @if ($request->status === \App\Enums\Calendar\AppointmentRequestStatus::Confirmed && $request->start_at?->isFuture())
                {{-- Umbuchen (MVP-1287): Anfrage und Auftrag wandern mit, der Kunde bekommt die neue Zeit. --}}
                <x-modal :id="'appointment-reschedule-' . $request->id" :embedded="false" tone="primary" icon="event_repeat"
                    :title="__('appointment.reschedule.title')"
                    :eyebrow="($request->start_at?->orgTz()->fdatetime() ?? '—') . ' · ' . ($request->customer?->name ?? $request->invitee_name ?? '—')"
                    :action="route('appointments.reschedule', $request)"
                    :submit-label="__('appointment.reschedule.submit')">
                    <x-input-field name="start" type="datetime-local" :id="'reschedule-start-' . $request->id" required
                                   :label="__('appointment.reschedule.start')"
                                   :value="$request->start_at?->orgTz()->format('Y-m-d\\TH:i')" />
                </x-modal>
            @endif
        @endforeach
    @endif

    <x-pagination :paginator="$decided" standing />
@endif
