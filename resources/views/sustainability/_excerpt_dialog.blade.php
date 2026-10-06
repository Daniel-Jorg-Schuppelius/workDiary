{{--
  Created on   : Sun Sep 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _excerpt_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Nachhaltigkeitsauszug freigeben (MVP-930). Erwartet: $status, $publication, $snapshots, $token, $canManageLink --}}
<x-modal :title="__('sustainability.excerpt.title')" :eyebrow="__('sustainability.site.back')" icon="eco" tone="primary" :close-label="__('Schließen')">
    <p class="text-sm opacity-70">{{ __('sustainability.excerpt.intro') }}</p>

    <form method="POST" action="{{ route('sustainability.excerpt.publish') }}" class="mt-4 flex flex-col gap-2">
        @csrf
        @method('PUT')
        <x-select-field name="snapshot_id" :label="__('sustainability.excerpt.snapshot')">
            <option value="">{{ __('sustainability.excerpt.none') }}</option>
            @foreach ($snapshots as $snapshot)
                <option value="{{ $snapshot->sqid }}" @selected($publication['snapshot_id'] === $snapshot->id)>{{ $snapshot->period_start->fdate() }} – {{ $snapshot->period_end->fdate() }} ({{ \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat(((float) ($snapshot->data['co2e_total_kg'] ?? 0)) / 1000, 1, withThousandsSeparator: true) }} t CO₂e)</option>
            @endforeach
        </x-select-field>
        <x-checkbox-field name="targets" :label="__('sustainability.excerpt.with_targets')" :checked="$publication['targets']" />
        <x-textarea-field name="statement" rows="3" :label="__('sustainability.excerpt.statement')" :hint="__('sustainability.claim.hint')">{{ old('statement', $publication['statement']) }}</x-textarea-field>
        <div class="flex justify-end"><x-button type="submit" size="sm">{{ __('sustainability.excerpt.publish') }}</x-button></div>
    </form>

    @if ($token)
        <div role="alert" class="alert alert-warning mt-4 items-start">
            <x-icon name="key" />
            <div class="min-w-0">
                <div class="font-semibold">{{ __('sustainability.excerpt.token_once') }}</div>
                <code class="mt-2 block select-all break-all text-xs">{{ route('sustainability-excerpt.public', $token) }}</code>
            </div>
        </div>
    @endif

    <p class="mt-4 text-sm">
        {{ __('sustainability.excerpt.link') }}:
        @if (! $status['issued'])
            <span class="wd-badge badge-ghost">{{ __('sustainability.excerpt.state_none') }}</span>
        @elseif ($status['enabled'])
            <span class="wd-badge badge-success">{{ __('sustainability.excerpt.state_active') }}</span> <code>{{ $status['hint'] }}…</code>
        @else
            <span class="wd-badge badge-warning">{{ __('sustainability.excerpt.state_paused') }}</span> <code>{{ $status['hint'] }}…</code>
        @endif
    </p>

    @if ($canManageLink)
        <x-slot:actions>
            @if ($status['issued'])
                <form method="POST" action="{{ route('sustainability.excerpt.toggle') }}" class="contents">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="enabled" value="{{ $status['enabled'] ? 0 : 1 }}">
                    <x-button type="submit" tone="plain">{{ $status['enabled'] ? __('sustainability.excerpt.pause') : __('sustainability.excerpt.resume') }}</x-button>
                </form>
                <form method="POST" action="{{ route('sustainability.excerpt.revoke') }}" class="contents">
                    @csrf
                    @method('DELETE')
                    <x-button type="submit" tone="error" class="btn-outline">{{ __('sustainability.excerpt.revoke') }}</x-button>
                </form>
            @endif
            <form method="POST" action="{{ route('sustainability.excerpt.rotate') }}" class="contents">
                @csrf
                <x-button type="submit">{{ $status['issued'] ? __('sustainability.excerpt.rotate') : __('sustainability.excerpt.issue') }}</x-button>
            </form>
        </x-slot:actions>
    @endif
</x-modal>
