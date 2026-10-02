{{--
  Created on   : Fri Oct 02 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : show.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Aufmaßblatt (MVP-1058/1059). Erwartet: $takeoff, $totals, $canEdit, $canTransition, $transferKinds, $presets --}}
@extends('layouts.app')
@section('title', $takeoff->title)
@section('nav-title', __('takeoff.title'))

@php
    $num = static fn ($v, int $d = 3): string => $v === null ? '—' : \CommonToolkit\Helper\Data\NumberHelper::toGermanFormat((float) $v, $d, trimTrailingZeros: true);
    $carrier = $takeoff->carrier();
    $backUrl = match (true) {
        $carrier instanceof \App\Models\Diary\DiaryEntry => route('diary.show', $carrier),
        $carrier instanceof \App\Models\Project\Project => route('projects.show', $carrier),
        $carrier instanceof \App\Models\Gaeb\BillOfQuantity => route('bill-of-quantities.show', $carrier),
        default => null,
    };
@endphp

@section('content')
<x-page-shell gap="4">
    <x-slot:toolbar>
        <x-page-toolbar :title="$takeoff->title" :back="$backUrl" :back-label="__('takeoff.back')">
            <div class="flex flex-wrap items-center gap-2 text-sm">
                <x-status-badge size="md" outline :tone="$takeoff->status->tone()">{{ $takeoff->status->label() }}</x-status-badge>
                @if ($takeoff->measured_on)<span class="text-muted">{{ $takeoff->measured_on->fdate() }}</span>@endif
            </div>
            <x-slot:actions>
                <x-icon-btn icon="picture_as_pdf" size="sm" :href="route('takeoffs.pdf', $takeoff)" show-label>{{ __('takeoff.action.pdf') }}</x-icon-btn>
                @if ($canEdit)
                    <x-icon-btn icon="edit" size="sm" data-entry-modal-trigger :href="route('takeoffs.edit', $takeoff)" show-label>{{ __('takeoff.action.edit') }}</x-icon-btn>
                @endif
                @if ($canTransition)
                    @foreach ($takeoff->status->allowedTransitions() as $target)
                        <x-action-form :action="route('takeoffs.transition', $takeoff)" :confirm="__('takeoff.confirm.' . $target->value)" confirm-icon="task_alt" confirm-tone="warning" :confirm-label="__('takeoff.transition.' . $target->value)">
                            <input type="hidden" name="status" value="{{ $target->value }}">
                            <x-button type="submit" size="sm" :tone="$target === \App\Enums\Takeoff\TakeoffStatus::Completed ? 'primary' : 'ghost'">{{ __('takeoff.transition.' . $target->value) }}</x-button>
                        </x-action-form>
                    @endforeach
                @endif
                @if ($transferKinds !== [])
                    <x-action-menu icon="output" tone="primary" :label="__('takeoff.transfer.action')">
                        @foreach ($transferKinds as $kind)
                            <x-action-form :action="route('takeoffs.transfer', $takeoff)" :confirm="__('takeoff.transfer.confirm.' . $kind->value)" :confirm-icon="$kind->icon()" confirm-tone="primary" :confirm-label="$kind->label()">
                                <input type="hidden" name="kind" value="{{ $kind->value }}">
                                <x-icon-btn :icon="$kind->icon()" size="sm" type="submit" show-label>{{ $kind->label() }}</x-icon-btn>
                            </x-action-form>
                        @endforeach
                    </x-action-menu>
                @endif
                @if ($canEdit)
                    <x-action-form :action="route('takeoffs.destroy', $takeoff)" method="DELETE" :confirm="__('takeoff.confirm.delete')" confirm-icon="delete" confirm-tone="error" :confirm-label="__('takeoff.action.delete')">
                        <x-button type="submit" size="sm" tone="error" placement="danger">{{ __('takeoff.action.delete') }}</x-button>
                    </x-action-form>
                @endif
            </x-slot:actions>
        </x-page-toolbar>
    </x-slot:toolbar>

    <x-card :title="__('takeoff.lines')" icon="straighten" padding="p-0">
        @if ($canEdit)
            <x-slot:actions>
                <x-action-menu icon="add" tone="primary" :label="__('takeoff.action.add_line')">
                    @if ($presets !== [])
                        <p class="wd-menu-heading">{{ __('takeoff.presets.label') }}</p>
                        @foreach ($presets as $preset)
                            <x-icon-btn icon="bookmark" size="sm" data-entry-modal-trigger :href="route('takeoffs.lines.create', [$takeoff, 'formula' => $preset['formula']->value, 'description' => $preset['label'], 'unit' => $preset['unit'], 'factor' => $preset['factor']])" show-label>{{ $preset['label'] }}</x-icon-btn>
                        @endforeach
                        <p class="wd-menu-heading">{{ __('takeoff.col.formula') }}</p>
                    @endif
                    @foreach (\App\Enums\Takeoff\TakeoffFormula::cases() as $formula)
                        <x-icon-btn icon="functions" size="sm" data-entry-modal-trigger :href="route('takeoffs.lines.create', [$takeoff, 'formula' => $formula->value])" show-label>{{ $formula->label() }}</x-icon-btn>
                    @endforeach
                </x-action-menu>
            </x-slot:actions>
        @endif
        <x-table bare>
            <x-slot:head>
                <tr>
                    <th>#</th>
                    <th>{{ __('takeoff.col.label') }}</th>
                    <th>{{ __('takeoff.col.formula') }}</th>
                    <th>{{ __('takeoff.col.values') }}</th>
                    <th class="text-right">{{ __('takeoff.col.factor') }}</th>
                    <th class="text-right">{{ __('takeoff.col.quantity') }}</th>
                    @if ($canEdit)<th class="text-right">{{ __('Aktionen') }}</th>@endif
                </tr>
            </x-slot:head>
            @forelse ($takeoff->lines as $i => $line)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $line->label ?: '—' }}@if ($line->boqItem)<div class="text-xs text-muted">{{ $line->boqItem->reference_no }} {{ \Illuminate\Support\Str::limit((string) $line->boqItem->short_text, 40) }}</div>@elseif ($line->article)<div class="text-xs text-muted">{{ $line->article->name }}</div>@elseif ($line->description)<div class="text-xs text-muted">{{ $line->description }}</div>@endif</td>
                    <td>{{ $line->formula->label() }}</td>
                    <td class="tabular-nums">{{ $line->formula->isExpression() ? ($line->values[0] ?? '') : implode('; ', array_map($num, (array) $line->values)) }}</td>
                    <td class="text-right tabular-nums {{ (float) $line->factor < 0 ? 'text-error' : '' }}">{{ $num($line->factor) }}</td>
                    <td class="text-right tabular-nums">{{ $num($line->quantity) }} {{ $line->unit }}</td>
                    @if ($canEdit)
                        <td class="text-right whitespace-nowrap">
                            <x-icon-btn icon="edit" size="xs" tone="ghost" data-entry-modal-trigger :href="route('takeoffs.lines.edit', [$takeoff, $line])" :title="__('Bearbeiten')" />
                            <x-action-form :action="route('takeoffs.lines.destroy', [$takeoff, $line])" method="DELETE" :confirm="__('takeoff.confirm.delete_line')" confirm-icon="delete" confirm-tone="error" :confirm-label="__('Entfernen')">
                                <x-icon-btn icon="delete" size="xs" tone="error" type="submit" :title="__('Entfernen')" />
                            </x-action-form>
                        </td>
                    @endif
                </tr>
            @empty
                <x-table.empty icon="straighten" :colspan="$canEdit ? 7 : 6" :title="__('takeoff.empty')" compact />
            @endforelse
        </x-table>
    </x-card>

    @if ($canEdit)
        {{-- MVP-1059: Schnellerfassung, offline als Befehl `takeoff.line` mit Foto in der Warteschlange. --}}
        <x-card :title="__('takeoff.quick.title')" icon="bolt">
            <form method="POST" action="{{ route('takeoffs.lines.store', $takeoff) }}" enctype="multipart/form-data"
                  data-offline-sync="takeoff.line" data-sync-payload-takeoff="{{ $takeoff->sqid }}"
                  class="grid grid-cols-2 items-end gap-2 md:grid-cols-4 xl:grid-cols-8">
                @csrf
                <x-select-field name="formula" id="quick-formula" :label="__('takeoff.col.formula')">
                    @foreach (\App\Enums\Takeoff\TakeoffFormula::cases() as $formula)
                        <option value="{{ $formula->value }}" @selected($formula === \App\Enums\Takeoff\TakeoffFormula::Rectangle)>{{ $formula->label() }}</option>
                    @endforeach
                </x-select-field>
                @for ($i = 0; $i < 3; $i++)
                    <x-input-field name="values[{{ $i }}]" id="quick-value-{{ $i }}" :label="__('takeoff.value.amount') . ' ' . ($i + 1)" :required="$i === 0" maxlength="500" />
                @endfor
                <x-input-field name="factor" id="quick-factor" inputmode="decimal" :label="__('takeoff.col.factor')" value="1" />
                <x-input-field name="label" id="quick-label" :label="__('takeoff.col.label')" maxlength="120" />
                <div class="fieldset">
                    <label class="fieldset-label" for="quick-photo">{{ __('takeoff.quick.photo') }}</label>
                    <input type="file" id="quick-photo" name="files[photo]" accept="image/*" capture="environment" class="file-input file-input-sm w-full">
                </div>
                <x-button type="submit" size="sm" tone="primary">{{ __('takeoff.quick.add') }}</x-button>
            </form>
            <p class="mt-2 text-xs text-muted">{{ __('takeoff.quick.hint') }}</p>
        </x-card>
    @endif

    @if ($totals !== [])
        <x-card :title="__('takeoff.totals')" icon="summarize" padding="p-0">
            <x-table bare>
                <x-slot:head>
                    <tr><th>{{ __('takeoff.col.target') }}</th><th class="text-right">{{ __('takeoff.col.quantity') }}</th></tr>
                </x-slot:head>
                @foreach ($totals as $total)
                    <tr><td>{{ $total['label'] }}</td><td class="text-right tabular-nums">{{ $num($total['quantity']) }} {{ $total['unit'] }}</td></tr>
                @endforeach
            </x-table>
        </x-card>
    @endif

    @if ($takeoff->transfers->isNotEmpty())
        <x-card :title="__('takeoff.transfer.targets')" icon="output">
            <ul class="space-y-1 text-sm">
                @foreach ($takeoff->transfers as $transfer)
                    <li class="flex items-center gap-2">
                        <x-icon :name="$transfer->kind->icon()" class="text-muted" />
                        <span>{{ $transfer->kind->label() }}</span>
                        @if ($transfer->target instanceof \App\Models\Sales\Quote)
                            <a class="link" href="{{ route('quotes.show', $transfer->target) }}">{{ $transfer->target->number }}</a>
                        @elseif ($transfer->target instanceof \App\Models\Invoicing\Invoice)
                            <a class="link" href="{{ route('invoices.show', $transfer->target) }}">{{ $transfer->target->number ?? __('Entwurf') }}</a>
                        @endif
                        <span class="text-muted">· {{ $transfer->created_at?->fdatetime() }}</span>
                    </li>
                @endforeach
            </ul>
        </x-card>
    @endif

    <x-card :title="__('takeoff.photos')" icon="photo_camera">
        <x-attachments-section :attachments="$takeoff->attachments" upload-type="takeoff" :upload-id="$takeoff->sqid" :can-upload="$canEdit" />
    </x-card>
    @if ($takeoff->note)
        <x-card :title="__('takeoff.field.note')"><p class="whitespace-pre-line text-sm">{{ $takeoff->note }}</p></x-card>
    @endif
</x-page-shell>
@endsection
