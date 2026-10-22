{{--
  Created on   : Mon Sep 21 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _tab_open.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Reiter „Offene Anfragen“. Variablen: $requests, $canManage --}}
@if ($requests->isEmpty())
    <x-empty-state framed icon="event_available"
        :title="__('Keine offenen Terminanfragen.')"
        :message="__('Neue Anfragen aus dem Kundenportal erscheinen hier zur Entscheidung.')" />
@else
    <x-table scroll="flex" :caption="__('Offene Anfragen')">
        <x-slot:head>
            <tr>
                <th>{{ __('Termin') }}</th>
                <th>{{ __('Kunde') }}</th>
                <th>{{ __('Leistung') }}</th>
                <th>{{ __('Quelle') }}</th>
                <th>{{ __('Eingegangen') }}</th>
                @if ($canManage)
                    <th class="text-right">{{ __('Aktionen') }}</th>
                @endif
            </tr>
        </x-slot:head>
        @foreach ($requests as $request)
            <tr class="hover">
                @include('appointments._request_cells', ['request' => $request])
                <td>
                    <x-status-badge tone="ghost" size="sm">{{ $request->sourceLabel() }}</x-status-badge>
                    @if ($request->is_reschedule)
                        <x-status-badge tone="info" size="sm" icon="update">{{ __('Umbuchung') }}</x-status-badge>
                    @endif
                    @if ($request->status === \App\Enums\Calendar\AppointmentRequestStatus::Proposed)
                        <x-status-badge tone="warning" size="sm" icon="schedule_send">{{ __('appointment.proposal.badge', ['date' => $request->proposed_start_at?->orgTz()->fdatetime() ?? '—']) }}</x-status-badge>
                    @endif
                </td>
                <td class="whitespace-nowrap tabular-nums text-muted">{{ $request->created_at?->fdatetime() }}</td>
                @if ($canManage)
                    <td class="text-right whitespace-nowrap">
                        <div class="flex justify-end gap-1">
                            @if ($request->status === \App\Enums\Calendar\AppointmentRequestStatus::Requested)
                                <x-action-form :action="route('appointments.confirm', $request)">
                                    <x-icon-btn icon="check" tone="success" size="sm" type="submit" show-label>{{ __('Bestätigen') }}</x-icon-btn>
                                </x-action-form>
                            @endif
                            <x-icon-btn icon="schedule_send" size="sm" show-label
                                        data-open-dialog="appointment-propose-{{ $request->id }}">{{ __('appointment.proposal.action') }}</x-icon-btn>
                            <x-icon-btn icon="close" size="sm" show-label
                                        data-open-dialog="appointment-decline-{{ $request->id }}">{{ __('Ablehnen') }}</x-icon-btn>
                        </div>
                    </td>
                @endif
            </tr>
        @endforeach
    </x-table>

    @if ($canManage)
        @foreach ($requests as $request)
            <x-modal :id="'appointment-decline-' . $request->id" :embedded="false" tone="error" icon="event_busy"
                :title="__('Anfrage ablehnen')"
                :eyebrow="($request->start_at?->orgTz()->fdate() ?? '—') . ' · ' . ($request->customer?->name ?? $request->invitee_name ?? '—')"
                :action="route('appointments.decline', $request)"
                :submit-label="__('Ablehnen')" submit-class="btn-error">
                <x-textarea-field name="reason" :id="'decline-reason-' . $request->id" rows="3" required maxlength="500"
                                  :label="__('Ablehnungsgrund (geht an den Kunden)')" />
            </x-modal>
            {{-- Ersatztermin (MVP-1287): gleiche Dauer, der Kunde entscheidet im Portal. --}}
            <x-modal :id="'appointment-propose-' . $request->id" :embedded="false" tone="warning" icon="schedule_send"
                :title="__('appointment.proposal.title')"
                :eyebrow="($request->start_at?->orgTz()->fdatetime() ?? '—') . ' · ' . ($request->customer?->name ?? $request->invitee_name ?? '—')"
                :action="route('appointments.propose', $request)"
                :submit-label="__('appointment.proposal.submit')">
                <x-input-field name="start" type="datetime-local" :id="'propose-start-' . $request->id" required
                               :label="__('appointment.proposal.start')"
                               :value="$request->proposed_start_at?->orgTz()->format('Y-m-d\\TH:i')" />
                <x-textarea-field name="note" :id="'propose-note-' . $request->id" rows="2" maxlength="500"
                                  :label="__('appointment.proposal.note')" />
            </x-modal>
        @endforeach
    @endif
@endif
