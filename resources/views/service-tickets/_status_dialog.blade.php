{{--
  Created on   : Tue Nov 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _status_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Statusdialog (MVP-1546, MVP-1547): Ziele aus allowedTransitions(); je Zielart
  Abschlusscode, Wartegrund mit Wiedervorlage oder Grund der Wiederöffnung.
  Erwartet: $ticket, $targets (list<ServiceTicketStatus>), $reopening, $orgUsers.
--}}
@php
    /** @var \App\Models\ServiceTicket\ServiceTicket $ticket */
    $initial = old('status', ($targets[0] ?? null)?->value ?? '');
    $pauseRules = (array) (is_array($ticket->sla_snapshot) && array_key_exists('pause_rules', $ticket->sla_snapshot)
        ? $ticket->sla_snapshot['pause_rules']
        : $ticket->slaContract?->pause_rules);
@endphp

<x-modal
    :title="__('Status ändern')"
    :eyebrow="$ticket->ticket_no"
    icon="swap_horiz"
    tone="primary"
    size="md"
    :action="route('service-tickets.transition', $ticket)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Übernehmen')">

    @if ($targets === [])
        <x-empty-state compact icon="block" :message="__('helpdesk.status.noTargets')" />
    @else
        <div class="space-y-3" x-data="reveal(@js($initial))">
            <p class="text-sm text-muted">{{ __('helpdesk.status.current', ['status' => $ticket->status->label()]) }}</p>

            <x-select-field name="status" id="ticket-status-target" :label="__('helpdesk.status.target')" required x-model="value">
                @foreach ($targets as $target)
                    <option value="{{ $target->value }}" @selected($initial === $target->value)>{{ $target->label() }}</option>
                @endforeach
            </x-select-field>

            <template x-if="isAny('done', 'closed')">
                <div class="space-y-3" data-status-closing>
                    <x-select-field name="close_code" id="ticket-close-code" :label="__('helpdesk.status.closeCode')" required>
                        <option value="">{{ __('— bitte wählen —') }}</option>
                        @foreach (\App\Enums\ServiceTicket\TicketCloseCode::cases() as $code)
                            <option value="{{ $code->value }}" @selected(old('close_code', $ticket->close_code?->value) === $code->value)>{{ $code->label() }}</option>
                        @endforeach
                    </x-select-field>
                    <x-textarea-field name="resolution_summary" id="ticket-resolution-summary" :label="__('helpdesk.status.resolutionSummary')"
                                      rows="4" maxlength="5000" :hint="__('helpdesk.status.resolutionSummaryHint')"
                                      :value="old('resolution_summary', $ticket->resolution_summary)" />
                </div>
            </template>

            <template x-if="isAny('waiting_customer', 'waiting_external', 'paused')">
                <div class="grid gap-3 sm:grid-cols-2" data-status-waiting>
                    <x-input-field name="wait_reason" id="ticket-wait-reason" :label="__('helpdesk.status.waitReason')" span="2"
                                   required minlength="3" maxlength="255" :value="old('wait_reason')" />
                    <x-input-field name="wait_until" id="ticket-wait-until" type="datetime-local" :label="__('helpdesk.status.waitUntil')"
                                   required :value="old('wait_until', now()->orgTz()->addDays(3)->format('Y-m-d\TH:i'))" />
                    <div class="fieldset">
                        <label class="fieldset-label" for="ticket-wait-owner">{{ __('helpdesk.status.waitOwner') }}</label>
                        <x-user-select name="wait_owner_id" id="ticket-wait-owner" :users="$orgUsers" value-key="sqid"
                                       :selected="old('wait_owner_id', auth()->user()?->sqid)" :placeholder="__('helpdesk.status.waitOwnerSelf')" />
                    </div>
                    <p class="text-xs text-muted sm:col-span-2">
                        {{ $pauseRules === [] ? __('helpdesk.status.pauseNone') : __('helpdesk.status.pauseSome', ['states' => implode(', ', array_map(static fn ($rule): string => \App\Enums\ServiceTicket\ServiceTicketStatus::tryFrom((string) $rule)?->label() ?? (string) $rule, $pauseRules))]) }}
                    </p>
                </div>
            </template>

            @if ($reopening)
                <template x-if="is('in_progress')">
                    <div data-status-reopening>
                        <x-textarea-field name="reopen_reason" id="ticket-reopen-reason" :label="__('helpdesk.status.reopenReason')"
                                          rows="3" required minlength="5" maxlength="500" :hint="__('helpdesk.status.reopenHint')"
                                          :value="old('reopen_reason')" />
                    </div>
                </template>
            @elseif ($ticket->status->isWaiting())
                <p class="text-xs text-muted">{{ __('helpdesk.status.resumeHint') }}</p>
            @endif

            @error('status')<p class="text-error text-xs">{{ $message }}</p>@enderror
        </div>
    @endif
</x-modal>
