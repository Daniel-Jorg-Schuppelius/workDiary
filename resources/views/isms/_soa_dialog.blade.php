{{--
  Created on   : Thu Jun 11 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _soa_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Statement of Applicability als Read-Only-Dialog (Feature 044/046):
  SoA-Aussagen des gewählten Geltungsbereichs (scope={sqid}, Default =
  Default-Scope; optionaler Norm-Filter norm="norm|edition") je Anforderung
  mit gemappten Maßnahmen und (über die Maßnahmen) verknüpften Risiken.
  Die druckbare Standalone-Ansicht bleibt unter isms.soa?print=1
  erreichbar.
  Variablen: $scope, $statements, $normLabel, $normFilter, $generatedAt,
             $organizationName
--}}

<x-modal
    :title="__('isms.title.soa')"
    :eyebrow="__('isms.title.section')"
    icon="rule_folder"
    tone="info"
    size="wide">

    <p class="mb-3 text-sm text-base-content/70">
        {{ $organizationName }}
        @if ($scope !== null)
            · {{ __('isms.field.scope') }}: {{ $scope->name }}
        @endif
        @if ($normLabel !== null)
            · {{ __('isms.field.norm') }}: {{ $normLabel }}
        @endif
        · {{ __('isms.soa.generated_at') }}: {{ $generatedAt->format('d.m.Y H:i') }}
        · {{ __('isms.soa.statement_count', ['count' => $statements->count()]) }}
    </p>

    {{-- Kein eigener Scroll-Container: die .wd-modal-body des Dialogs ist
         der einzige Scrollbereich (sonst doppelte Scrollbalken). --}}
    <div class="rounded-box border border-base-300">
        <table class="table table-xs table-pin-rows">
            <thead>
                <tr>
                    <th class="w-16">{{ __('isms.field.ref_no') }}</th>
                    <th>{{ __('isms.field.title') }}</th>
                    <th class="w-20 text-center">{{ __('isms.field.applicable') }}</th>
                    <th class="w-1/4">{{ __('isms.field.justification') }}</th>
                    <th class="w-28">{{ __('isms.field.implementation_status') }}</th>
                    <th class="w-40">{{ __('isms.soa.controls_risks') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($statements as $statement)
                    @php($requirement = $statement->requirement)
                    <tr @class(['opacity-60' => ! $statement->applicable])>
                        <td class="font-mono text-xs" title="{{ $requirement->normLabel() }}">{{ $requirement->ref_no }}</td>
                        <td>{{ $requirement->title }}</td>
                        <td class="text-center">{{ $statement->applicable ? __('isms.soa.yes') : __('isms.soa.no') }}</td>
                        <td class="text-sm">{{ $statement->justification ?? '—' }}</td>
                        <td>
                            <x-status-badge :tone="$statement->implementation_status->tone()">
                                {{ $statement->implementation_status->label() }}
                            </x-status-badge>
                        </td>
                        <td class="text-xs">
                            @if ($requirement->controls->isEmpty())
                                <span class="text-base-content/50">—</span>
                            @else
                                @foreach ($requirement->controls as $control)
                                    <span class="block">
                                        {{ $control->title }}
                                        @if ($control->risks->isNotEmpty())
                                            <span class="font-mono text-base-content/60">({{ $control->risks->map(fn($r) => $r->displayNo())->implode(', ') }})</span>
                                        @endif
                                    </span>
                                @endforeach
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-base-content/50">{{ __('isms.empty_requirements') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <p class="mt-3 text-xs text-base-content/60">{{ __('isms.soa.disclaimer') }}</p>

    <x-slot:footerExtra>
        <x-icon-btn icon="print" tone="outline" size="sm"
                    :href="route('isms.soa', array_filter(['print' => 1, 'scope' => $scope?->sqid, 'norm' => $normFilter !== 'all' ? $normFilter : null]))"
                    target="_blank"
                    show-label>{{ __('isms.action.print') }}</x-icon-btn>
    </x-slot:footerExtra>
</x-modal>
