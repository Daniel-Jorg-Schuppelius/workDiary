{{--
  Created on   : Sun Sep 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _proposal_link_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Öffentlicher Link für Investitionsvorschläge (MVP-936). Erwartet: $status, $token --}}
<x-modal :title="__('investment.proposal.link')" :eyebrow="__('Investitionen')" icon="link" tone="primary" :close-label="__('Schließen')">
    <p class="text-sm opacity-70">{{ __('investment.proposal.link_intro') }}</p>

    @if ($token)
        <div class="alert alert-warning mt-4 items-start">
            <x-icon name="key" />
            <div class="min-w-0">
                <div class="font-semibold">{{ __('investment.proposal.token_once') }}</div>
                <code class="mt-2 block select-all break-all text-xs">{{ route('investment-proposal.public', $token) }}</code>
            </div>
        </div>
    @endif

    <p class="mt-4 text-sm">
        @if (! $status['issued'])
            <span class="wd-badge badge-ghost">{{ __('investment.proposal.state_none') }}</span>
        @elseif ($status['enabled'])
            <span class="wd-badge badge-success">{{ __('investment.proposal.state_active') }}</span> <code>{{ $status['hint'] }}…</code>
        @else
            <span class="wd-badge badge-warning">{{ __('investment.proposal.state_paused') }}</span> <code>{{ $status['hint'] }}…</code>
        @endif
    </p>

    <x-slot:actions>
        @if ($status['issued'])
            <form method="POST" action="{{ route('investments.proposals.toggle') }}" class="contents">
                @csrf
                @method('PATCH')
                <input type="hidden" name="enabled" value="{{ $status['enabled'] ? 0 : 1 }}">
                <button type="submit" class="btn btn-sm">{{ $status['enabled'] ? __('investment.proposal.pause') : __('investment.proposal.resume') }}</button>
            </form>
            <form method="POST" action="{{ route('investments.proposals.revoke') }}" class="contents">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-sm btn-error btn-outline">{{ __('investment.proposal.revoke') }}</button>
            </form>
        @endif
        <form method="POST" action="{{ route('investments.proposals.rotate') }}" class="contents">
            @csrf
            <button type="submit" class="btn btn-sm btn-primary">{{ $status['issued'] ? __('investment.proposal.rotate') : __('investment.proposal.issue') }}</button>
        </form>
    </x-slot:actions>
</x-modal>
