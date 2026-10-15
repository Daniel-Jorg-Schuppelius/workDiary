{{--
  Created on   : Thu Oct 15 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _inquiries.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Nachfragen des Kunden zu dieser Rechnung aus E-Mails (Feature 166, MVP-1202). --}}
@php
    /** @var \App\Models\Invoicing\Invoice $invoice */
    $inquiries = $invoice->inquiries()->with(['customer:id,name', 'communicationNote:id'])->limit(50)->get();
    $openInquiries = $inquiries->filter(fn (\App\Models\Invoicing\InvoiceInquiry $i): bool => $i->isOpen())->count();
@endphp
@if ($inquiries->isNotEmpty())
    <details class="collapse collapse-arrow border border-base-300 bg-base-100" @if ($openInquiries > 0) open @endif>
        <summary class="collapse-title text-sm font-medium">
            {{ __('invoicing.inquiries.section', ['count' => $inquiries->count()]) }}
            @if ($openInquiries > 0)
                <x-status-badge tone="warning" size="xs">{{ trans_choice('invoicing.inquiries.open_count', $openInquiries, ['count' => $openInquiries]) }}</x-status-badge>
            @endif
        </summary>
        <div class="collapse-content overflow-x-auto">
            <x-table>
                <x-slot:head>
                    <tr>
                        <th>{{ __('invoicing.inquiries.column.received') }}</th>
                        <th>{{ __('invoicing.inquiries.column.sender') }}</th>
                        <th>{{ __('Betreff') }}</th>
                        <th>{{ __('invoicing.inquiries.column.match') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th></th>
                    </tr>
                </x-slot:head>
                @foreach ($inquiries as $inquiry)
                    <tr>
                        <td class="whitespace-nowrap">{{ $inquiry->received_at->fdatetime() }}</td>
                        <td class="max-w-xs truncate" title="{{ $inquiry->from_email }}">{{ $inquiry->from_name ?: $inquiry->from_email }}</td>
                        <td class="max-w-sm truncate">{{ $inquiry->subject ?? __('mail.inbox.no_subject') }}</td>
                        <td>{{ $inquiry->match_kind->label() }}</td>
                        <td><x-status-badge :tone="$inquiry->status->tone()" size="xs">{{ $inquiry->status->label() }}</x-status-badge></td>
                        <td class="text-right whitespace-nowrap">
                            @if ($inquiry->communicationNote !== null)
                                <x-icon-btn icon="chat" tone="ghost" size="sm" :href="route('communication-notes.show', $inquiry->communicationNote)" :label="__('invoicing.inquiries.action.note')" />
                            @endif
                            @can('update', $inquiry)
                                @if ($inquiry->isOpen())
                                    <x-action-form :action="route('invoices.inquiries.answer', $inquiry)">
                                        <x-icon-btn icon="task_alt" tone="success" size="sm" type="submit" :label="__('invoicing.inquiries.action.answer')" />
                                    </x-action-form>
                                @else
                                    <x-action-form :action="route('invoices.inquiries.reopen', $inquiry)">
                                        <x-icon-btn icon="undo" tone="ghost" size="sm" type="submit" :label="__('invoicing.inquiries.action.reopen')" />
                                    </x-action-form>
                                @endif
                            @endcan
                        </td>
                    </tr>
                @endforeach
            </x-table>
        </div>
    </details>
@endif
