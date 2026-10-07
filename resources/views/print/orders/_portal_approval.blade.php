{{--
  Created on   : Wed Oct 07 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _portal_approval.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
  Druckfreigabe im Kundenportal (MVP-1076) — erwartet: $intake, $target (PrintOrder).
  Nennt Dateiversion, Prüfsumme und Parameter; die interne Produktionsfreigabe bleibt getrennt.
--}}
@php
    /** @var \App\Models\Print\PrintOrder $target */
    $request = $target->customer_approval_request;
    $parameters = (array) data_get($request, 'parameters', []);
@endphp
<x-card :title="__('print.intake.portal_title')">
    @if ($request === null)
        <p class="text-sm text-muted">{{ __('print.intake.portal_waiting') }}</p>
    @else
        <x-detail-grid class="grid-cols-1 sm:grid-cols-2">
            <x-detail-grid.row :label="__('print.field.file')">
                {{ data_get($request, 'file.original_name') }} · {{ __('print.intake.version', ['version' => data_get($request, 'file.version_no')]) }}
                @if ($target->customerApprovalPending() && $target->hasProductionFile())
                    <a class="link block text-sm" href="{{ route('customer.intakes.print-file', $intake) }}">{{ __('print.intake.download_file') }}</a>
                @endif
            </x-detail-grid.row>
            <x-detail-grid.row :label="__('print.intake.checksum')"><span class="font-mono text-xs break-all">{{ data_get($request, 'file.sha256') }}</span></x-detail-grid.row>
            @foreach (\App\Services\Print\PrintOrderService::CUSTOMER_PARAMETERS as $key)
                <x-detail-grid.row :label="__('print.snapshot.' . $key)">{{ $parameters[$key] ?? '—' }}</x-detail-grid.row>
            @endforeach
            @if (! empty($parameters['pages']))
                <x-detail-grid.row :label="__('print.snapshot.pages')">{{ $parameters['pages'] }}</x-detail-grid.row>
            @endif
            @if (! empty($parameters['finishing']))
                <x-detail-grid.row :label="__('print.snapshot.finishing')">{{ implode(', ', (array) $parameters['finishing']) }}</x-detail-grid.row>
            @endif
            <x-detail-grid.row :label="__('print.intake.customer_approval')">@include('print.orders._customer_approval_state', ['order' => $target])</x-detail-grid.row>
        </x-detail-grid>

        @if ($target->customerApprovalPending())
            <form method="POST" action="{{ route('customer.intakes.print-approval', $intake) }}" class="mt-4 space-y-3">
                @csrf
                <p class="text-sm">{{ __('print.intake.portal_confirm') }}</p>
                <div class="fieldset">
                    <label class="fieldset-label" for="print-decline-reason">{{ __('print.intake.decline_reason') }}</label>
                    <textarea id="print-decline-reason" name="reason" rows="2" maxlength="1000" class="textarea textarea-bordered w-full @error('reason') textarea-error @enderror">{{ old('reason') }}</textarea>
                    @error('reason')<p class="text-error text-sm">{{ $message }}</p>@enderror
                    @error('decision')<p class="text-error text-sm">{{ $message }}</p>@enderror
                </div>
                <div class="flex flex-wrap gap-2">
                    <x-button type="submit" name="decision" value="approve" tone="primary" icon="task_alt">{{ __('print.intake.approve') }}</x-button>
                    <x-button type="submit" name="decision" value="decline" tone="outline" icon="undo">{{ __('print.intake.decline') }}</x-button>
                </div>
            </form>
        @endif
    @endif
</x-card>
