{{--
  Created on   : Tue Nov 10 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _progress_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
  Fortschritt einer LV-Position melden (MVP-1582). Ein gewählter Materialverbrauch
  oder ein gewähltes Protokoll bestimmt die Quelle und bleibt an der Meldung stehen.
  Erwartet: $item, $entries, $materialUsages, $protocols
--}}
@php
    $qty = static fn ($value): string => \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $value, 3, withThousandsSeparator: true, trimTrailingZeros: true);
    $canSeeProtocols = auth()->user()?->can('viewAny', \App\Models\Protocol\Protocol::class) ?? false;
@endphp
<x-modal
    :title="__('gaeb.progress.dialog.title')"
    :eyebrow="trim($item->reference_no . ' ' . ($item->short_text ?? ''))"
    icon="straighten"
    tone="primary"
    size="lg"
    :action="route('bill-of-quantities.items.progress', $item)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('gaeb.progress.record')">
    <input type="hidden" name="_dialog_url" value="{{ route('bill-of-quantities.items.progress.create', $item) }}?dialog=1">

    <x-form-group :legend="__('gaeb.progress.title')" icon="straighten" tone="primary" cols="2">
        <x-input-field name="quantity" id="boq-progress-quantity" type="number" step="0.001" required inputmode="decimal"
                       :label="__('gaeb.progress.quantity') . ($item->unit ? ' (' . $item->unit . ')' : '')"
                       :value="old('quantity')" :hint="__('gaeb.progress.dialog.quantity_hint')" />
        <x-select-field name="source" id="boq-progress-source" :label="__('gaeb.progress.dialog.source')">
            @foreach ([\App\Enums\Gaeb\BoqProgressSource::Manual, \App\Enums\Gaeb\BoqProgressSource::Measurement] as $source)
                <option value="{{ $source->value }}" @selected(old('source', 'manual') === $source->value)>{{ $source->label() }}</option>
            @endforeach
        </x-select-field>
        <x-select-field name="material_usage" id="boq-progress-material-usage" :label="__('gaeb.progress.source.material')"
                        :hint="__('gaeb.progress.dialog.material_usage_hint')">
            <option value="">—</option>
            @foreach ($materialUsages as $usage)
                <option value="{{ $usage->sqid }}" @selected(old('material_usage') === $usage->sqid)>
                    {{ $usage->material?->name ?? $usage->description ?? '—' }} · {{ $qty($usage->quantity?->getValue()->toFloat() ?? 0.0) }} {{ $usage->unit }} · {{ $usage->created_at?->fdate() }}
                </option>
            @endforeach
        </x-select-field>
        @if ($canSeeProtocols)
            <x-select-field name="protocol" id="boq-progress-protocol" :label="__('gaeb.progress.source.protocol')"
                            :hint="__('gaeb.progress.dialog.protocol_hint')">
                <option value="">—</option>
                @foreach ($protocols as $protocol)
                    <option value="{{ $protocol->sqid }}" @selected(old('protocol') === $protocol->sqid)>
                        {{ $protocol->title ?: $protocol->type->label() }} · {{ $protocol->occurred_at?->fdate() }}
                    </option>
                @endforeach
            </x-select-field>
        @endif
        <x-input-field name="note" id="boq-progress-note" :label="__('gaeb.progress.note')" maxlength="500" span="2" :value="old('note')" />
    </x-form-group>

    <x-form-group :legend="__('gaeb.progress.dialog.history')" icon="history" tone="ghost" cols="1">
        @if ($entries->isEmpty())
            <x-empty-state icon="history" :title="__('gaeb.progress.dialog.history_empty')" compact />
        @else
            <x-table :bare="true" size="sm">
                <x-slot:head>
                    <th>{{ __('gaeb.progress.dialog.captured_at') }}</th>
                    <th class="text-right">{{ __('gaeb.progress.quantity') }}</th>
                    <th>{{ __('gaeb.progress.dialog.source') }}</th>
                    <th>{{ __('gaeb.progress.dialog.reference') }}</th>
                </x-slot:head>
                @foreach ($entries as $entry)
                    <tr>
                        <td class="whitespace-nowrap">{{ $entry->captured_at->fdate() }}</td>
                        <td class="text-right tabular-nums">{{ $qty($entry->quantity) }}</td>
                        <td><x-status-badge size="xs">{{ $entry->source->label() }}</x-status-badge></td>
                        <td class="text-sm">
                            @if ($entry->protocol !== null && $canSeeProtocols)
                                <a class="link" href="{{ route('protocols.show', $entry->protocol) }}">{{ $entry->protocol->title ?: $entry->protocol->type->label() }}</a>
                            @elseif ($entry->materialUsage !== null)
                                {{ $entry->materialUsage->material?->name ?? $entry->materialUsage->description ?? '—' }}
                                · {{ $qty($entry->materialUsage->quantity?->getValue()->toFloat() ?? 0.0) }} {{ $entry->materialUsage->unit }}
                            @else
                                {{ $entry->note ?: '—' }}
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-table>
        @endif
    </x-form-group>
</x-modal>
