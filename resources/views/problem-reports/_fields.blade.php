{{--
  Created on   : Thu Jul 16 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _fields.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Formularfelder „Problem melden" (Feature 041, MVP-053) — geteilt vom
     Modal-Dialog (_form_dialog) und der standalone Vollseite (create). Der
     Melder sieht VOR dem Absenden, welcher Kontext übertragen wird (DoD 041). --}}
@php
    /** @var array<string, mixed> $context */
    /** @var string $diagnosticsMode */
    /** @var array<string, mixed>|null $diagnosticsPreview */
@endphp
<x-form-group :legend="__('problemreport.section.what')" icon="description" tone="warning" cols="1">
    <div class="fieldset">
        <span class="fieldset-label">{{ __('problemreport.field.summary') }}</span>
        <input type="text" name="summary" maxlength="200" required class="input input-bordered w-full"
               value="{{ old('summary') }}">
        @error('summary')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
    </div>
    <div class="fieldset">
        <span class="fieldset-label">{{ __('problemreport.field.description') }}</span>
        <textarea name="description" rows="4" maxlength="5000" required
                  class="textarea textarea-bordered w-full">{{ old('description') }}</textarea>
        @error('description')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
    </div>
    <div class="grid gap-3 md:grid-cols-2">
        <div class="fieldset">
            <span class="fieldset-label">{{ __('problemreport.field.expected') }}</span>
            <textarea name="expected_behavior" rows="2" maxlength="2000"
                      class="textarea textarea-bordered w-full">{{ old('expected_behavior') }}</textarea>
        </div>
        <div class="fieldset">
            <span class="fieldset-label">{{ __('problemreport.field.actual') }}</span>
            <textarea name="actual_behavior" rows="2" maxlength="2000"
                      class="textarea textarea-bordered w-full">{{ old('actual_behavior') }}</textarea>
        </div>
    </div>
    <div class="grid gap-3 md:grid-cols-2">
        <div class="fieldset">
            <span class="fieldset-label">{{ __('problemreport.field.severity') }}</span>
            <select name="severity" class="select select-bordered w-full">
                @foreach (\App\Enums\Support\ProblemReportSeverity::cases() as $severity)
                    <option value="{{ $severity->value }}" @selected(old('severity', 'normal') === $severity->value)>{{ $severity->label() }}</option>
                @endforeach
            </select>
        </div>
        <div class="fieldset">
            <span class="fieldset-label">{{ __('problemreport.field.screenshots') }}</span>
            <input type="file" name="screenshots[]" multiple accept=".png,.jpg,.jpeg,.webp,.pdf"
                   class="file-input file-input-bordered w-full">
            @error('screenshots.*')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>
    </div>
    <label class="label cursor-pointer justify-start gap-3">
        <input type="hidden" name="contact_ok" value="0">
        <input type="checkbox" name="contact_ok" value="1" class="checkbox checkbox-sm" @checked(old('contact_ok'))>
        <span class="label-text text-sm">{{ __('problemreport.field.contact_ok') }}</span>
    </label>
</x-form-group>

<x-form-group :legend="__('problemreport.section.context')" icon="visibility" tone="info" cols="1">
    <input type="hidden" name="context_route" value="{{ $context['route'] }}">
    <input type="hidden" name="context_url" value="{{ $context['url'] }}">
    <input type="hidden" name="context_topic" value="{{ $context['help_topic'] }}">
    @if ($context['error_code'] !== null)
        <input type="hidden" name="context_error_code" value="{{ $context['error_code'] }}">
    @endif
    @if (($context['request_id'] ?? null) !== null)
        <input type="hidden" name="context_request_id" value="{{ $context['request_id'] }}">
    @endif
    <p class="text-xs text-base-content/70">{{ __('problemreport.hint.context') }}</p>
    <ul class="text-xs font-mono text-base-content/60 space-y-0.5">
        @if ($context['route'])<li>{{ __('problemreport.context.route') }}: {{ $context['route'] }}</li>@endif
        @if ($context['help_topic'])<li>{{ __('problemreport.context.topic') }}: {{ $context['help_topic'] }}</li>@endif
        <li>{{ __('problemreport.context.version') }}: {{ config('app.version') }}</li>
        <li>{{ __('errors.request_id') }}: {{ app()->bound(\App\Http\Middleware\AssignRequestId::CONTAINER_KEY) ? app(\App\Http\Middleware\AssignRequestId::CONTAINER_KEY) : '—' }}</li>
    </ul>

    @if ($diagnosticsMode !== \App\Services\Support\ProblemReportService::DIAG_MODE_NEVER && $diagnosticsPreview !== null)
        @if ($diagnosticsMode === \App\Services\Support\ProblemReportService::DIAG_MODE_ASK)
            <label class="label cursor-pointer justify-start gap-3">
                <input type="hidden" name="include_diagnostics" value="0">
                <input type="checkbox" name="include_diagnostics" value="1" class="checkbox checkbox-sm" @checked(old('include_diagnostics'))>
                <span class="label-text text-sm">{{ __('problemreport.field.include_diagnostics') }}</span>
            </label>
        @else
            <p class="text-xs text-base-content/70">{{ __('problemreport.hint.diagnostics_always') }}</p>
        @endif
        <details class="rounded-lg border border-base-300 bg-base-200/50 p-2">
            <summary class="cursor-pointer text-xs font-medium">{{ __('problemreport.hint.diagnostics_preview') }}</summary>
            <pre class="mt-2 max-h-48 overflow-auto text-[11px] leading-tight">{{ json_encode($diagnosticsPreview, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
        </details>
    @endif
</x-form-group>
