{{--
  Created on   : Tue Jun 09 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : data-protection.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
<x-card :title="__('Datenschutz')">
    <div class="space-y-2 text-sm">
        <a href="{{ route('dataprotection.requests.index') }}" class="flex items-center justify-between hover:underline">
            <span class="flex items-center gap-2"><x-icon name="contact_mail" /> {{ __('Offene Betroffenenanfragen') }}</span>
            <x-status-badge :tone="$overdueRequests > 0 ? 'error' : 'ghost'" size="md">{{ $openRequests }}</x-status-badge>
        </a>
        @if ($overdueRequests > 0)
            <p class="text-xs text-error">{{ trans_choice(':count überfällige Frist|:count überfällige Fristen', $overdueRequests, ['count' => $overdueRequests]) }}</p>
        @endif

        <a href="{{ route('dataprotection.activities.index') }}" class="flex items-center justify-between hover:underline">
            <span class="flex items-center gap-2"><x-icon name="fact_check" /> {{ __('Überfällige VVT-Reviews') }}</span>
            <x-status-badge :tone="$overdueReviews > 0 ? 'warning' : 'ghost'" size="md">{{ $overdueReviews }}</x-status-badge>
        </a>
    </div>
</x-card>
