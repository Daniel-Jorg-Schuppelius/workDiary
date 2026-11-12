{{--
  Created on   : Tue Jul 14 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _route_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog: Ordnerregel anlegen/bearbeiten (Feature 080, MVP-358) --}}
@php
    /** @var \App\Models\CloudIntake\CloudDocumentConnection $connection */
    /** @var \App\Models\CloudIntake\CloudDocumentRoute $route */
    /** @var array<string, array{label: string, options: list<array{value: string, label: string}>}> $targetOptions */
    /** @var \App\Services\CloudIntake\CloudRouteTestResult|null $testPreview */
    $isEdit = $route->exists;
@endphp
<x-modal
    :title="($isEdit ? __('cloud_intake.route.edit') : __('cloud_intake.route.create')) . ' — ' . $connection->name"
    icon="alt_route"
    tone="primary"
    :action="$isEdit ? route('admin.cloud-intake.routes.update', $route) : route('admin.cloud-intake.routes.store', $connection)"
    :method="$isEdit ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('cloud_intake.route.save')"
>
    <x-form-group :legend="__('cloud_intake.route.basics')" icon="alt_route" tone="primary" cols="2">
        <div class="fieldset md:col-span-2">
            <label class="fieldset-label" for="cir-pattern">{{ __('cloud_intake.route.pattern') }}</label>
            <input id="cir-pattern" type="text" name="path_pattern" required maxlength="512"
                   value="{{ old('path_pattern', $route->path_pattern) }}"
                   class="input input-bordered w-full font-mono" placeholder="Kunden/{customer_number}/Vertraege/**">
            <p class="text-xs text-muted">{{ __('cloud_intake.route.pattern_help') }}</p>
            @error('path_pattern')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>

        <div class="fieldset">
            <label class="fieldset-label" for="cir-target">{{ __('cloud_intake.route.target') }}</label>
            <select id="cir-target" name="target" class="select select-bordered w-full" required>
                @foreach (\App\Enums\CloudIntake\CloudIntakeRouteTarget::cases() as $target)
                    <option value="{{ $target->value }}" @selected(old('target', $route->target?->value ?? 'document') === $target->value)>{{ $target->label() }}</option>
                @endforeach
            </select>
            @error('target')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>

        <div class="fieldset">
            <label class="fieldset-label" for="cir-doctype">{{ __('cloud_intake.route.document_type') }}</label>
            <select id="cir-doctype" name="document_type" class="select select-bordered w-full">
                <option value="">—</option>
                @foreach (\App\Enums\Document\DocumentType::cases() as $type)
                    <option value="{{ $type->value }}" @selected(old('document_type', $route->document_type) === $type->value)>{{ $type->label() }}</option>
                @endforeach
            </select>
            @error('document_type')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>

        <div class="fieldset md:col-span-2">
            <label class="fieldset-label" for="cir-target-ref">{{ __('cloud_intake.route.target_ref') }}</label>
            <select id="cir-target-ref" name="target_ref" class="select select-bordered w-full">
                <option value="">{{ __('cloud_intake.route.target_ref_none') }}</option>
                @foreach ($targetOptions as $group)
                    <optgroup label="{{ $group['label'] }}">
                        @foreach ($group['options'] as $option)
                            <option value="{{ $option['value'] }}" @selected(old('target_ref', $targetValue) === $option['value'])>{{ $option['label'] }}</option>
                        @endforeach
                    </optgroup>
                @endforeach
            </select>
            <p class="text-xs text-muted">{{ __('cloud_intake.route.target_ref_help') }}</p>
            @error('target_ref')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>

        <div class="fieldset">
            <label class="fieldset-label" for="cir-priority">{{ __('cloud_intake.route.priority') }}</label>
            <input id="cir-priority" type="number" name="priority" min="1" max="9999" required
                   value="{{ old('priority', $route->priority ?? 100) }}"
                   class="input input-bordered w-full tabular-nums">
            @error('priority')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>

        <div class="fieldset">
            <label class="fieldset-label" for="cir-ext">{{ __('cloud_intake.route.extensions') }}</label>
            <input id="cir-ext" type="text" name="allowed_extensions" maxlength="300"
                   value="{{ old('allowed_extensions', is_array($route->allowed_extensions) ? implode(', ', $route->allowed_extensions) : '') }}"
                   class="input input-bordered w-full font-mono" placeholder="pdf, xml">
            <p class="text-xs text-muted">{{ __('cloud_intake.route.extensions_help') }}</p>
            @error('allowed_extensions')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>

        <div class="fieldset">
            <label class="fieldset-label" for="cir-maxsize">{{ __('cloud_intake.route.max_size') }}</label>
            <input id="cir-maxsize" type="number" name="max_file_size" min="1"
                   value="{{ old('max_file_size', $route->max_file_size) }}"
                   class="input input-bordered w-full tabular-nums" placeholder="52428800">
            @error('max_file_size')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>

        <div class="fieldset">
            <label class="fieldset-label">
                <input type="hidden" name="auto_version" value="0">
                <input type="checkbox" name="auto_version" value="1" class="checkbox checkbox-sm"
                       @checked(old('auto_version', $route->auto_version))>
                {{ __('cloud_intake.route.auto_version') }}
            </label>
            <p class="text-xs text-muted">{{ __('cloud_intake.route.auto_version_help') }}</p>
        </div>

        <div class="fieldset md:col-span-2">
            <label class="fieldset-label" for="cir-test-path">{{ __('cloud_intake.route.test_path') }}</label>
            <input id="cir-test-path" type="text" name="test_path" required maxlength="1024"
                   value="{{ old('test_path', $route->test_path) }}"
                   class="input input-bordered w-full font-mono" placeholder="Kunden/10023/Vertraege/Rahmenvertrag.pdf">
            <p class="text-xs text-muted">{{ __('cloud_intake.route.test_path_help') }}</p>
            @error('test_path')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
            @if ($testPreview !== null)
                <div role="status" class="mt-2 rounded-box border p-2 text-xs {{ $testPreview->ok ? 'border-success/40 bg-success/10' : 'border-warning/40 bg-warning/10' }}">
                    <p class="font-semibold">{{ __('cloud_intake.route.test_preview', ['at' => $route->tested_at?->ftime() ?? '—']) }}</p>
                    <ul class="mt-1 space-y-0.5">
                        @foreach ($testPreview->lines as $line)
                            <li>{{ $line }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        <div class="fieldset">
            <label class="fieldset-label">
                <input type="hidden" name="active" value="0">
                <input type="checkbox" name="active" value="1" class="checkbox checkbox-sm"
                       @checked(old('active', $route->active ?? true))>
                {{ __('cloud_intake.route.active') }}
            </label>
            <p class="text-xs text-muted">{{ __('cloud_intake.route.active_help') }}</p>
            @error('active')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>
    </x-form-group>

    @if ($isEdit)
        <x-slot:footerExtra>
            <x-action-form :action="route('admin.cloud-intake.routes.destroy', $route)"
                  method="DELETE"
                  :confirm="__('cloud_intake.route.delete_confirm')"
                  :confirm-label="__('cloud_intake.route.delete')">
                <x-icon-btn icon="delete" tone="error" size="sm" type="submit" show-label>{{ __('cloud_intake.route.delete') }}</x-icon-btn>
            </x-action-form>
        </x-slot:footerExtra>
    @endif
</x-modal>
