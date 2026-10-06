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
        <div role="alert" class="alert alert-warning mt-4 items-start">
            <x-icon name="key" />
            <div class="min-w-0">
                <div class="font-semibold">{{ __('inventory.serial.passport.token_once') }}</div>
                <code class="block mt-2 break-all select-all text-xs">{{ route('serials.public-passport', $token) }}</code>
            </div>
        </div>
    @endif

    <x-detail-grid class="mt-4">
        <x-detail-grid.row :label="__('inventory.serial.passport.state')">
            @if (! $status['issued'])
                <span class="wd-badge badge-ghost">{{ __('inventory.serial.passport.state_none') }}</span>
            @elseif ($status['enabled'])
                <span class="wd-badge badge-success">{{ __('inventory.serial.passport.state_active') }}</span>
            @else
                <span class="wd-badge badge-warning">{{ __('inventory.serial.passport.state_paused') }}</span>
            @endif
        </x-detail-grid.row>

        @if ($status['issued'])
            <x-detail-grid.row :label="__('inventory.serial.passport.hint')"><code>{{ $status['hint'] }}…</code></x-detail-grid.row>

            @if ($status['issued_at'])
                <x-detail-grid.row :label="__('inventory.serial.passport.issued_at')">{{ \App\Support\Tz::parse($status['issued_at'])->timezone(\App\Support\Tz::current())->format('d.m.Y H:i') }}</x-detail-grid.row>
            @endif
        @endif
    </x-detail-grid>

    <x-slot:actions>
        @if ($status['issued'])
            <form method="POST" action="{{ route('serials.passport.toggle') }}" class="contents">
                @csrf
                @method('PATCH')
                <input type="hidden" name="enabled" value="{{ $status['enabled'] ? 0 : 1 }}">
                <x-button type="submit" tone="plain">
                    {{ $status['enabled'] ? __('inventory.serial.passport.action.pause') : __('inventory.serial.passport.action.resume') }}
                </x-button>
            </form>

            <form method="POST" action="{{ route('serials.passport.revoke') }}" class="contents">
                @csrf
                @method('DELETE')
                <x-button type="submit" tone="error" class="btn-outline" data-confirm-dialog
                        data-confirm-message="{{ __('inventory.serial.passport.confirm.revoke') }}" data-confirm-icon="link_off"
                        data-confirm-tone="error" data-confirm-label="{{ __('inventory.serial.passport.action.revoke') }}">{{ __('inventory.serial.passport.action.revoke') }}</x-button>
            </form>
        @endif

        <form method="POST" action="{{ route('serials.passport.rotate') }}" class="contents">
            @csrf
            @if ($status['issued'])
                <x-button type="submit" data-confirm-dialog
                          data-confirm-message="{{ __('inventory.serial.passport.confirm.rotate') }}"
                          data-confirm-icon="autorenew"
                          data-confirm-tone="warning"
                          data-confirm-label="{{ __('inventory.serial.passport.action.rotate') }}">
                    {{ __('inventory.serial.passport.action.rotate') }}
                </x-button>
            @else
                <x-button type="submit">{{ __('inventory.serial.passport.action.issue') }}</x-button>
            @endif
        </form>
    </x-slot:actions>
</x-modal>
