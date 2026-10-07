{{--
  Created on   : Wed Oct 07 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _intake_origin.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
  Herkunft aus dem Kundeneingang am Druckauftrag (MVP-1076) — erwartet: $order, $intake, $intakeFiles.
  Produktionsdatei ausdrücklich festlegen, Kundenfreigabe anfordern.
--}}
@php
    $snapshot = (array) data_get($order->customer_approval_request, 'parameters', []);
    $values = $intake->form?->values;
    $default = fn (string $key, ?string $fromForm = null) => old($key, $snapshot[$key] ?? ($fromForm !== null ? $values?->get($fromForm) : null));
@endphp
<x-card :title="__('print.intake.origin_title')" icon="move_to_inbox">
    <p class="text-sm">
        @can('view', $intake)
            <a class="link font-mono" href="{{ route('customer-intakes.show', $intake) }}">{{ $intake->number }}</a>
        @else
            <span class="font-mono">{{ $intake->number }}</span>
        @endcan
        — {{ $intake->subject }}
        @if ($intake->quote !== null)
            · {{ __('print.intake.quote', ['number' => $intake->quote->number, 'version' => $intake->quote->version]) }}
        @endif
    </p>

    <x-table :bare="true" size="sm" class="mt-3">
        <x-slot:head>
            <tr>
                <th>{{ __('print.intake.intake_files') }}</th>
                <th></th>
            </tr>
        </x-slot:head>
        @forelse ($intakeFiles as $file)
            <tr>
                <td class="max-w-72 truncate" title="{{ $file->original_name }}">{{ $file->original_name }}<br><span class="text-xs text-muted">{{ $file->created_at?->fdatetime() }}</span></td>
                <td class="text-right whitespace-nowrap">
                    <x-icon-btn icon="download" size="xs" :href="route('customer-intakes.files.download', [$intake, $file])" :label="__('Herunterladen')" />
                    @can('update', $order)
                        @if (! $order->status->isFinal())
                            <x-action-form :action="route('print-orders.intake-file', [$order, $file])" class="inline" :confirm="__('print.intake.bind_confirm')">
                                <x-icon-btn icon="push_pin" size="xs" type="submit" :label="__('print.intake.bind_file')" />
                            </x-action-form>
                        @endif
                    @endcan
                </td>
            </tr>
        @empty
            <x-table.empty icon="attach_file" :colspan="2" :title="__('customer_intake.files.empty')" compact />
        @endforelse
    </x-table>

    <div class="mt-4">
        <h3 class="mb-1 text-sm font-semibold">{{ __('print.intake.customer_approval') }}</h3>
        @include('print.orders._customer_approval_state', ['order' => $order])
    </div>

    @can('update', $order)
        @if ($order->status === \App\Enums\Print\PrintOrderStatus::DataCheck && $order->hasProductionFile())
            <form method="POST" action="{{ route('print-orders.customer-approval', $order) }}" class="mt-3 grid grid-cols-2 gap-2 text-sm">
                @csrf
                <input type="text" name="final_format" value="{{ $default('final_format') }}" placeholder="{{ __('print.snapshot.final_format') }} *" aria-label="{{ __('print.snapshot.final_format') }}" class="input input-sm input-bordered" required>
                <input type="number" name="quantity" value="{{ $default('quantity', 'quantity') }}" min="1" step="any" placeholder="{{ __('print.snapshot.quantity') }} *" aria-label="{{ __('print.snapshot.quantity') }}" class="input input-sm input-bordered" required>
                <input type="text" name="color_mode" value="{{ $default('color_mode') }}" placeholder="{{ __('print.snapshot.color_mode') }} *" aria-label="{{ __('print.snapshot.color_mode') }}" class="input input-sm input-bordered" required>
                <input type="text" name="material" value="{{ $default('material', 'material') }}" placeholder="{{ __('print.snapshot.material') }} *" aria-label="{{ __('print.snapshot.material') }}" class="input input-sm input-bordered" required>
                <input type="number" name="pages" value="{{ old('pages', $snapshot['pages'] ?? null) }}" min="1" placeholder="{{ __('print.snapshot.pages') }}" aria-label="{{ __('print.snapshot.pages') }}" class="input input-sm input-bordered">
                <input type="text" name="finishing" value="{{ old('finishing', implode(', ', (array) ($snapshot['finishing'] ?? []))) }}" placeholder="{{ __('print.snapshot.finishing') }}" aria-label="{{ __('print.snapshot.finishing') }}" class="input input-sm input-bordered">
                <button type="submit" class="btn btn-sm col-span-2">{{ __('print.intake.request_approval') }}</button>
            </form>
        @endif
    @endcan
</x-card>
