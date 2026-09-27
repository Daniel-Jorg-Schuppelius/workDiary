{{--
  Created on   : Sun Sep 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _status_page_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Erwartet: $status (array), $token (?string, nur direkt nach der Ausstellung) --}}
<x-modal
    :title="__('crisis.status_page.title')"
    :eyebrow="__('Krisenmanagement')"
    icon="campaign"
    tone="primary"
    :close-label="__('Schließen')">

    <p class="text-sm opacity-70">{{ __('crisis.status_page.intro') }}</p>

    @if ($token)
        {{-- Einmalige Anzeige: der Klartext ist nirgends gespeichert. --}}
        <div class="alert alert-warning mt-4 items-start">
            <x-icon name="key" />
            <div class="min-w-0">
                <div class="font-semibold">{{ __('crisis.status_page.token_once') }}</div>
                <code class="block mt-2 break-all select-all text-xs">{{ route('crisis-status.public', $token) }}</code>
            </div>
        </div>
    @endif

    <dl class="mt-4 grid grid-cols-[auto,1fr] gap-x-4 gap-y-2 text-sm">
        <dt class="opacity-70">{{ __('crisis.status_page.state') }}</dt>
        <dd>
            @if (! $status['issued'])
                <span class="wd-badge badge-ghost">{{ __('crisis.status_page.state_none') }}</span>
            @elseif ($status['enabled'])
                <span class="wd-badge badge-success">{{ __('crisis.status_page.state_active') }}</span>
            @else
                <span class="wd-badge badge-warning">{{ __('crisis.status_page.state_paused') }}</span>
            @endif
        </dd>

        @if ($status['issued'])
            <dt class="opacity-70">{{ __('crisis.status_page.hint') }}</dt>
            <dd><code>{{ $status['hint'] }}…</code></dd>

            @if ($status['issued_at'])
                <dt class="opacity-70">{{ __('crisis.status_page.issued_at') }}</dt>
                <dd>{{ \App\Support\Tz::parse($status['issued_at'])->timezone(\App\Support\Tz::current())->format('d.m.Y H:i') }}</dd>
            @endif
        @endif
    </dl>

    <x-slot:actions>
        @if ($status['issued'])
            <form method="POST" action="{{ route('crisis.status-page.toggle') }}" class="contents">
                @csrf
                @method('PATCH')
                <input type="hidden" name="enabled" value="{{ $status['enabled'] ? 0 : 1 }}">
                <button type="submit" class="btn btn-sm">
                    {{ $status['enabled'] ? __('crisis.status_page.action.pause') : __('crisis.status_page.action.resume') }}
                </button>
            </form>

            <form method="POST" action="{{ route('crisis.status-page.revoke') }}" class="contents">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-sm btn-error btn-outline"
                        data-confirm-dialog
                        data-confirm-message="{{ __('crisis.status_page.confirm.revoke') }}"
                        data-confirm-icon="link_off"
                        data-confirm-tone="error"
                        data-confirm-label="{{ __('crisis.status_page.action.revoke') }}">{{ __('crisis.status_page.action.revoke') }}</button>
            </form>
        @endif

        <form method="POST" action="{{ route('crisis.status-page.rotate') }}" class="contents">
            @csrf
            <button type="submit" class="btn btn-sm btn-primary"
                    @if ($status['issued'])
                        data-confirm-dialog
                        data-confirm-message="{{ __('crisis.status_page.confirm.rotate') }}"
                        data-confirm-icon="autorenew"
                        data-confirm-tone="warning"
                        data-confirm-label="{{ __('crisis.status_page.action.rotate') }}"
                    @endif>
                {{ $status['issued'] ? __('crisis.status_page.action.rotate') : __('crisis.status_page.action.issue') }}
            </button>
        </form>
    </x-slot:actions>
</x-modal>
