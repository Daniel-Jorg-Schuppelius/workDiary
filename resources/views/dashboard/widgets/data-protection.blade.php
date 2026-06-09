<x-card :title="__('Datenschutz')">
    <div class="space-y-2 text-sm">
        <a href="{{ route('dataprotection.requests.index') }}" class="flex items-center justify-between hover:underline">
            <span class="flex items-center gap-2"><x-icon name="contact_mail" /> {{ __('Offene Betroffenenanfragen') }}</span>
            <span class="badge {{ $overdueRequests > 0 ? 'badge-error' : 'badge-ghost' }}">{{ $openRequests }}</span>
        </a>
        @if ($overdueRequests > 0)
            <p class="text-xs text-error">{{ trans_choice(':count überfällige Frist|:count überfällige Fristen', $overdueRequests, ['count' => $overdueRequests]) }}</p>
        @endif

        <a href="{{ route('dataprotection.activities.index') }}" class="flex items-center justify-between hover:underline">
            <span class="flex items-center gap-2"><x-icon name="fact_check" /> {{ __('Überfällige VVT-Reviews') }}</span>
            <span class="badge {{ $overdueReviews > 0 ? 'badge-warning' : 'badge-ghost' }}">{{ $overdueReviews }}</span>
        </a>
    </div>
</x-card>
