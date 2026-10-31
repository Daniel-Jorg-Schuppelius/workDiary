{{--
  Created on   : Sat Oct 31 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _plan_section.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Serienvorlagen des Buchhaltungsprogramms (Feature 185, MVP-1411/1412; Teilstück
  seit Phase 165, MVP-1444): Vorlagen mit Lizenzpositionen, Zuordnungen je
  Position, Zuordnen nur bei freien Lizenzen, Ausführungsstand, Befunde.
  Erwartet $plan (PlanSection::build), $candidates (Abos für die Auswahl),
  $storeRoute (Ziel der Zuordnung), $canManage. Lizenzkonto und Arbeitsmappe.
--}}
@if ($plan['available'])
    <x-card :title="__('resale.plan.title')" padding="p-0" class="mt-4 mb-4">
        <p class="px-4 pt-3 text-xs text-muted">{{ __('resale.plan.subtitle') }}</p>
        @forelse ($plan['templates'] as $entry)
            @php $template = $entry['template']; @endphp
            {{-- Zebra je Vorlage (Pilot 2026-10-31): jede zweite Vorlage leicht hinterlegt, damit Kopf, Positionen und Bezüge als Block zusammengehören. --}}
            <div @class(['border-t border-base-300', 'bg-base-200/40' => $loop->even])>
                <div class="flex flex-wrap items-center justify-between gap-2 px-4 py-2 text-sm">
                    <span class="flex flex-wrap items-center gap-2">
                        <span class="font-medium">{{ $template->label() }}</span>
                        <x-status-badge size="xs" :tone="$template->isActive() ? 'success' : 'warning'" :label="__('resale.plan.status.' . $template->status)" />
                        @if ($template->interval !== '')<x-status-badge size="xs" outline :label="__('resale.plan.interval.' . $template->interval)" />@endif
                        {{-- Fehlgeschlagene Ausführung (MVP-1441): Meldung des Programms als Tooltip, dazu ob die Rechnung entstand und bezahlt ist. --}}
                        @if ($template->lastFailed)
                            <x-status-badge size="xs" tone="error" :label="__('resale.plan.failed')" :title="$template->lastError" />
                            @if (($entry['execution'] ?? null) !== null)
                                <span class="text-xs {{ $entry['execution']->state === \App\Services\Reselling\Plan\TemplateExecution::OPEN ? 'text-warning' : 'text-muted' }}" title="{{ $template->lastError }}">{{ $entry['execution']->label() }}</span>
                            @endif
                        @endif
                        <span class="text-xs text-muted">{{ $template->recipientName }}@if ($template->nextExecutionOn) · {{ __('resale.plan.next', ['date' => $template->nextExecutionOn->fdate()]) }}@endif · {{ $template->finalize ? __('resale.plan.finalize') : __('resale.plan.draft_only') }}</span>
                    </span>
                    @if ($template->permalink)
                        <x-external-link :url="$template->permalink" plain class="btn btn-ghost btn-xs" :title="__('resale.mirror.open_source', ['source' => __('resale.mirror.source_' . $template->sourceKey)])"><x-icon name="open_in_new" size="1rem" /></x-external-link>
                    @endif
                </div>
                <x-table bare>
                    <x-slot:head>
                        <tr>
                            <x-table.th>{{ __('resale.plan.col.line') }}</x-table.th>
                            <x-table.th class="text-right">{{ __('resale.account.col.licences') }}</x-table.th>
                            <x-table.th>{{ __('resale.plan.col.linked') }}</x-table.th>
                            <x-table.th class="text-right"></x-table.th>
                        </tr>
                    </x-slot:head>
                    @forelse ($entry['lines'] as $row)
                        @php $line = $row['line']; $licences = $line->licences($template->intervalMonths()); @endphp
                        <tr class="hover">
                            <td class="text-sm">{{ $line->label() }}<span class="block text-xs text-muted">{{ __('resale.link_ui.position', ['position' => $line->position]) }} · <x-resale.licence-months :value="$line->quantity" />{{ $line->unitName ? ' ' . $line->unitName : '' }} × {{ $line->unitNet->withScale(2)->format() }}</span></td>
                            <td class="text-right tabular-nums">{{ \App\View\Components\Resale\LicenceMonths::compact($licences) }}</td>
                            <td class="text-sm">
                                @forelse ($row['links'] as $link)
                                    {{-- Ein Block je Bezug (Pilot 2026-10-31): erste Zeile das Abo selbst (Produkt bzw. Domain) mit Herkunft und Aktionen,
                                         zweite Zeile Halter und Kennung — so ist auf einen Blick zu sehen, wozu die Position gehört. --}}
                                    <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5 py-0.5 border-l-2 border-base-300 pl-2 mb-1">
                                        <a href="{{ route('finance.resale.show', $link->subscription->sqid) }}" class="link link-hover font-medium">{{ $link->subscription->label }}</a>
                                        <span class="text-xs text-muted">×{{ $link->quantity }}</span>
                                        <x-status-badge size="xs" :tone="$link->origin->tone()" :label="$link->origin->label()" />
                                        @if ($link->note)<span class="text-xs text-muted" title="{{ __('resale.plan.from_invoice', ['voucher' => $link->note]) }}">{{ $link->note }}</span>@endif
                                        @if ($canManage)
                                            @if ($link->origin === \App\Enums\Reselling\LinkOrigin::Proposed)
                                                <form method="POST" action="{{ route('finance.resale.accounts.plan.store', $group->routeKey()) }}" class="inline">
                                                    @csrf
                                                    <input type="hidden" name="subscription_id" value="{{ $link->subscription->sqid }}">
                                                    <input type="hidden" name="plan_key" value="{{ $template->key }}">
                                                    <input type="hidden" name="line_position" value="{{ $line->position }}">
                                                    <input type="hidden" name="quantity" value="{{ $link->quantity }}">
                                                    <x-icon-btn icon="task_alt" size="xs" tone="success" type="submit" :title="__('resale.plan.confirm')" />
                                                </form>
                                            @endif
                                            <form method="POST" action="{{ route('finance.resale.accounts.plan.destroy', $link->sqid) }}" class="inline">
                                                @csrf
                                                @method('DELETE')
                                                <x-icon-btn icon="link_off" size="xs" tone="ghost" type="submit" :title="__('resale.plan.unlink')" />
                                            </form>
                                        @endif
                                        <span class="basis-full text-xs text-muted">{{ $link->subscription->holderLabel() }} · {{ $link->subscription->identityLabel() }}</span>
                                    </div>
                                @empty
                                    <span class="text-warning text-xs">{{ __('resale.plan.finding.plan_without_subscription_short') }}</span>
                                @endforelse
                            </td>
                            <td class="text-right align-top">
                                {{-- Zuordnen nur, solange Lizenzen der Position frei sind (Pilot 2026-10-31): eine voll zugeordnete
                                     Position bietet keine Auswahl mehr an, sonst wirkt sie unzugeordnet. --}}
                                @php $openLicences = max(0, (int) round($licences) - (int) array_sum(array_map(static fn($l): int => (int) $l->quantity, $row['links']))); @endphp
                                @if ($canManage && $openLicences > 0)
                                    <div class="flex items-center justify-end gap-1">
                                        <form method="POST" action="{{ $storeRoute }}" class="flex items-center gap-1">
                                            @csrf
                                            <input type="hidden" name="plan_key" value="{{ $template->key }}">
                                            <input type="hidden" name="line_position" value="{{ $line->position }}">
                                            <select name="subscription_id" class="select select-xs select-bordered w-44" aria-label="{{ __('resale.plan.assign') }}">
                                                @foreach ($candidates as $candidate)
                                                    <option value="{{ $candidate->sqid }}">{{ $candidate->label }} · {{ $candidate->holderLabel() }} · {{ $candidate->identityLabel() }}</option>
                                                @endforeach
                                            </select>
                                            <input type="number" name="quantity" min="1" value="{{ $openLicences }}" class="input input-xs input-bordered w-14 text-right" aria-label="{{ __('resale.account.col.licences') }}">
                                            <x-icon-btn icon="add_link" size="xs" tone="primary" type="submit" :title="__('resale.plan.assign')" />
                                        </form>
                                        <x-icon-btn icon="add" size="xs" tone="ghost" data-entry-modal-trigger :href="route('finance.resale.create', ['plan' => $template->key, 'position' => $line->position])" :title="__('resale.plan.create')" />
                                    </div>
                                @elseif ($row['links'] !== [])
                                    <span class="text-xs text-muted whitespace-nowrap"><x-icon name="check" size="0.9rem" /> {{ __('resale.plan.fully_assigned') }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <x-table.empty :colspan="4" :title="__('resale.plan.no_licence_lines')" compact />
                    @endforelse
                </x-table>
            </div>
        @empty
            <x-empty-state compact icon="event_repeat" :title="__('resale.plan.none')" />
        @endforelse
        @if ($plan['findings'] !== [])
            <ul class="border-t border-base-300 px-4 py-3 space-y-1 text-sm">
                @foreach ($plan['findings'] as $finding)
                    {{-- Befund: feste Abzeichen-Spalte, rechts das Abo (Produkt bzw. Domain) mit Halter und Kennung in einer Zeile,
                         darunter Vorlage und Stand; die Meldung des Programms gekürzt mit Tooltip (Pilot 2026-10-31). --}}
                    <li class="flex items-start gap-3 border-l-2 border-warning/60 pl-2 py-0.5">
                        <span class="w-44 shrink-0 pt-0.5"><x-status-badge size="xs" tone="warning" :label="__('resale.plan.finding.' . $finding['kind'] . '_short')" /></span>
                        <span class="min-w-0 flex-1">
                            @if ($finding['subscription'] !== null)<a href="{{ route('finance.resale.show', $finding['subscription']->sqid) }}" class="link link-hover font-medium">{{ $finding['subscription']->label }}</a> <span class="text-xs text-muted">· {{ $finding['subscription']->holderLabel() }} · {{ $finding['subscription']->identityLabel() }}</span>@endif
                            @if ($finding['template'] !== null)<span class="block text-xs text-muted">{{ __('resale.plan.col.line') }}: {{ $finding['template']->label() }}@if ($finding['line'] !== null) · {{ $finding['line']->label() }}@endif</span>@endif
                            <span class="block text-xs text-muted">{{ __('resale.plan.finding.' . $finding['kind'], $finding['params']) }}</span>
                            @if (($finding['params']['error'] ?? '') !== '')<span class="block text-xs text-muted/80 line-clamp-1" title="{{ $finding['params']['error'] }}">{{ $finding['params']['error'] }}</span>@endif
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-card>
@endif
