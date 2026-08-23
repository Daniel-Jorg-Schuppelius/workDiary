{{--
  Created on   : Sat Aug 22 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _closing_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Abschluss-Preflight (Feature 125, MVP-677): Was nach dem Schließen nicht
  mehr zu reparieren wäre, blockiert vorher.
--}}
<x-modal
    :title="__('accounting.closing.action.close')"
    :eyebrow="$period->starts_on->fdate() . ' – ' . $period->ends_on->fdate()"
    icon="lock"
    :action="route('finance.accounting.closing.close', $period)"
    method="POST"
    :submit-label="__('accounting.closing.action.close_submit')"
>
    <ul class="flex flex-col gap-2">
        @foreach ($report->checks as $check)
            <li class="flex items-start gap-2 text-sm">
                <x-status-badge :tone="$check->tone()">{{ __('accounting.closing.check.key.' . $check->key) }}</x-status-badge>
                <span class="min-w-0 flex-1">{{ $check->message }}</span>
            </li>
        @endforeach
    </ul>

    @unless ($report->isReady())
        <p class="text-xs text-error">{{ __('accounting.closing.blocked_hint') }}</p>
    @endunless
</x-modal>
