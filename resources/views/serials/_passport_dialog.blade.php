{{--
  Created on   : Sun Aug 31 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _passport_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Erwartet: $status (array), $token (?string, nur direkt nach der Ausstellung) --}}
<x-modal
    :title="__('inventory.serial.passport.title')"
    :eyebrow="__('inventory.serial.title')"
    icon="badge"
    tone="primary"
    :close-label="__('Schließen')">

    <p class="text-sm opacity-70">{{ __('inventory.serial.passport.intro') }}</p>

    @if ($token)
        {{-- Einmalige Anzeige: der Klartext ist nirgends gespeichert. --}}
        <div class="alert alert-warning mt-4 items-start">
            <x-icon name="key" />
            <div class="min-w-0">
                <div class="font-semibold">{{ __('inventory.serial.passport.token_once') }}</div>
                <code class="block mt-2 break-all select-all text-xs">{{ route('serials.public-passport', $token) }}</code>
            </div>
        </div>
    @endif

    <dl class="mt-4 grid grid-cols-[auto,1fr] gap-x-4 gap-y-2 text-sm">
        <dt class="opacity-70">{{ __('inventory.serial.passport.state') }}</dt>
        <dd>
            @if (! $status['issued'])
                <span class="wd-badge badge-ghost">{{ __('inventory.serial.passport.state_none') }}</span>
            @elseif ($status['enabled'])
                <span class="wd-badge badge-success">{{ __('inventory.serial.passport.state_active') }}</span>
            @else
                <span class="wd-badge badge-warning">{{ __('inventory.serial.passport.state_paused') }}</span>
            @endif
        </dd>

        @if ($status['issued'])
            <dt class="opacity-70">{{ __('inventory.serial.passport.hint') }}</dt>
            <dd><code>{{ $status['hint'] }}…</code></dd>

            @if ($status['issued_at'])
                <dt class="opacity-70">{{ __('inventory.serial.passport.issued_at') }}</dt>
                <dd>{{ \App\Support\Tz::parse($status['issued_at'])->timezone(\App\Support\Tz::current())->format('d.m.Y H:i') }}</dd>
            @endif
        @endif
    </dl>

    <x-slot:actions>
        @if ($status['issued'])
            <form method="POST" action="{{ route('serials.passport.toggle') }}" class="contents">
                @csrf
                @method('PATCH')
                <input type="hidden" name="enabled" value="{{ $status['enabled'] ? 0 : 1 }}">
                <button type="submit" class="btn btn-sm">
                    {{ $status['enabled'] ? __('inventory.serial.passport.action.pause') : __('inventory.serial.passport.action.resume') }}
                </button>
            </form>

            <form method="POST" action="{{ route('serials.passport.revoke') }}" class="contents">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-sm btn-error btn-outline"
                        data-confirm-dialog
                        data-confirm-message="{{ __('inventory.serial.passport.confirm.revoke') }}"
                        data-confirm-icon="link_off"
                        data-confirm-tone="error"
                        data-confirm-label="{{ __('inventory.serial.passport.action.revoke') }}">{{ __('inventory.serial.passport.action.revoke') }}</button>
            </form>
        @endif

        <form method="POST" action="{{ route('serials.passport.rotate') }}" class="contents">
            @csrf
            <button type="submit" class="btn btn-sm btn-primary"
                    @if ($status['issued'])
                        data-confirm-dialog
                        data-confirm-message="{{ __('inventory.serial.passport.confirm.rotate') }}"
                        data-confirm-icon="autorenew"
                        data-confirm-tone="warning"
                        data-confirm-label="{{ __('inventory.serial.passport.action.rotate') }}"
                    @endif>
                {{ $status['issued'] ? __('inventory.serial.passport.action.rotate') : __('inventory.serial.passport.action.issue') }}
            </button>
        </form>
    </x-slot:actions>
</x-modal>
