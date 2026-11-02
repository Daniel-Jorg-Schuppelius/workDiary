{{--
  Created on   : Mon Nov 02 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _period_actions.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Aktionen einer Periode (bestätigen, Bezug, verzichten, wieder öffnen) mit
  ihren Statusbedingungen — eine Stelle für Periodenzeile und Abgleichstafel
  (Konsolidierungs-Audit 2026-11-02, kon-10). Erwartet $period, $upcoming,
  $canManage; $dense = engerer Abstand in der Tafel.
--}}
@php $dense = $dense ?? false; @endphp
@if ($canManage && ! $upcoming)
    <div @class(['flex justify-end', 'gap-0.5' => $dense, 'gap-1' => ! $dense])>
        @if ($period->isProposedOnly())
            <form method="POST" action="{{ route('finance.resale.periods.confirm', $period->sqid) }}">
                @csrf
                <x-icon-btn icon="task_alt" size="xs" tone="success" type="submit" :title="__('resale.link.action.confirm')" />
            </form>
        @endif
        @if ($period->status !== \App\Enums\Reselling\PeriodStatus::Waived)
            <x-icon-btn icon="add_link" size="xs" tone="ghost" data-entry-modal-trigger :href="route('finance.resale.periods.link.create', $period->sqid)" :title="__('resale.link.action.link')" />
        @endif
        @if (! $period->status->isDecided() || $period->status === \App\Enums\Reselling\PeriodStatus::Partial)
            <x-icon-btn icon="do_not_disturb_on" size="xs" tone="ghost" data-entry-modal-trigger :href="route('finance.resale.periods.waive.create', $period->sqid)" :title="__('resale.link.action.waive')" />
        @endif
        @if ($period->status->isDecided())
            <form method="POST" action="{{ route('finance.resale.periods.reopen', $period->sqid) }}">
                @csrf
                <x-icon-btn icon="undo" size="xs" tone="ghost" type="submit" :title="__('resale.link.action.reopen')" />
            </form>
        @endif
    </div>
@endif
