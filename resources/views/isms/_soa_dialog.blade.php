{{--
  Created on   : Thu Jun 11 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _soa_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Statement of Applicability als Read-Only-Dialog (Feature 044). Die
  druckbare Standalone-Ansicht bleibt unter isms.soa?print=1 erreichbar.
--}}

<x-modal
    :title="__('isms.title.soa')"
    :eyebrow="__('isms.title.menu')"
    icon="rule_folder"
    tone="info"
    size="wide">

    <p class="mb-3 text-sm text-base-content/70">
        {{ $organizationName }}
        · {{ __('isms.soa.generated_at') }}: {{ $generatedAt->format('d.m.Y H:i') }}
        · {{ __('isms.soa.control_count', ['count' => $controls->count()]) }}
    </p>

    {{-- Kein eigener Scroll-Container: die .wd-modal-body des Dialogs ist
         der einzige Scrollbereich (sonst doppelte Scrollbalken). --}}
    <div class="rounded-box border border-base-300">
        <table class="table table-xs table-pin-rows">
            <thead>
                <tr>
                    <th class="w-16">{{ __('isms.field.code') }}</th>
                    <th>{{ __('isms.field.title') }}</th>
                    <th class="w-20 text-center">{{ __('isms.field.applicable') }}</th>
                    <th class="w-1/4">{{ __('isms.field.justification') }}</th>
                    <th class="w-28">{{ __('isms.field.implementation_status') }}</th>
                    <th class="w-32">{{ __('isms.field.risks') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($controls as $control)
                    <tr @class(['opacity-60' => ! $control->applicable])>
                        <td class="font-mono text-xs">{{ $control->code }}</td>
                        <td>
                            {{ $control->title }}
                            @if ($control->owner !== null)
                                <span class="text-base-content/50"> · {{ $control->owner->name }}</span>
                            @endif
                        </td>
                        <td class="text-center">{{ $control->applicable ? __('isms.soa.yes') : __('isms.soa.no') }}</td>
                        <td class="text-sm">{{ $control->justification ?? '—' }}</td>
                        <td>
                            <x-status-badge :tone="$control->implementation_status->tone()">
                                {{ $control->implementation_status->label() }}
                            </x-status-badge>
                        </td>
                        <td class="font-mono text-xs">
                            @if ($control->risks->isEmpty())
                                <span class="text-base-content/50">—</span>
                            @else
                                {{ $control->risks->map(fn($r) => $r->displayNo())->implode(', ') }}
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-base-content/50">{{ __('isms.empty_controls') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <p class="mt-3 text-xs text-base-content/60">{{ __('isms.soa.disclaimer') }}</p>

    <x-slot:footerExtra>
        <x-icon-btn icon="print" tone="outline" size="sm"
                    :href="route('isms.soa', ['print' => 1])"
                    target="_blank"
                    show-label>{{ __('isms.action.print') }}</x-icon-btn>
    </x-slot:footerExtra>
</x-modal>
