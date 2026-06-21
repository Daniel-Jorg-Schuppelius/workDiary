{{-- Dialog: Webhook-Endpunkt anlegen/bearbeiten (Feature 008) --}}
@php
    /** @var \App\Models\Integration\WebhookEndpoint $endpoint */
    /** @var list<\App\Enums\Integration\WebhookEvent> $events */
    $isEdit = $endpoint->exists;
    $selected = (array) old('events', $endpoint->events ?? []);
@endphp
<x-modal
    :title="$isEdit ? __('integration.webhook.title.edit') : __('integration.webhook.title.create')"
    :eyebrow="$isEdit ? $endpoint->label : null"
    icon="webhook"
    tone="primary"
    :action="$isEdit ? route('admin.webhooks.update', $endpoint) : route('admin.webhooks.store')"
    :method="$isEdit ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    :submit-label="$isEdit ? __('integration.webhook.action.save') : __('integration.webhook.action.create')"
>
    <x-form-group :legend="__('integration.webhook.field.basics')" icon="webhook" tone="primary" cols="2">
        <x-input-field name="label" :label="__('integration.webhook.field.label')" required maxlength="120"
                       :value="old('label', $endpoint->label)"
                       :placeholder="__('integration.webhook.field.label_placeholder')" />

        <div class="fieldset">
            <label class="fieldset-label" for="webhook-url">{{ __('integration.webhook.field.url') }}</label>
            <input id="webhook-url" type="url" name="url" required maxlength="2048"
                   value="{{ old('url', $endpoint->url) }}"
                   class="input input-bordered w-full font-mono"
                   placeholder="https://example.com/hooks/workdiary">
            <p class="text-xs text-base-content/60">{{ __('integration.webhook.field.url_help') }}</p>
            @error('url')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>
    </x-form-group>

    <x-form-group :legend="__('integration.webhook.field.events')" icon="bolt" tone="info"
                  :description="__('integration.webhook.field.events_help')">
        <div class="grid gap-2 sm:grid-cols-2">
            @foreach ($events as $event)
                <label class="label cursor-pointer justify-start gap-3 rounded border border-base-300 px-3 py-2">
                    <input type="checkbox" name="events[]" value="{{ $event->value }}" class="checkbox checkbox-primary checkbox-sm"
                           @checked(in_array($event->value, $selected, true))>
                    <span class="inline-flex items-center gap-2">
                        <x-icon :name="$event->icon()" class="text-sm text-primary" />
                        <span>
                            <span class="label-text block">{{ $event->label() }}</span>
                            <span class="block font-mono text-xs text-base-content/50">{{ $event->value }}</span>
                        </span>
                    </span>
                </label>
            @endforeach
        </div>
        @error('events')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
    </x-form-group>

    <x-form-group :legend="__('integration.webhook.field.security')" icon="key" tone="warning" cols="2">
        <div class="fieldset sm:col-span-2">
            <label class="label cursor-pointer justify-start gap-3">
                <input type="hidden" name="active" value="0">
                <input type="checkbox" name="active" value="1" class="toggle toggle-primary"
                       @checked(old('active', $endpoint->active ?? true))>
                <span class="label-text">{{ __('integration.webhook.field.endpoint_active') }}</span>
            </label>
            @error('active')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>

        @if ($isEdit)
            <div class="fieldset sm:col-span-2">
                <span class="fieldset-label">{{ __('integration.webhook.field.signing_secret') }}</span>
                <div class="flex items-center gap-2">
                    <code class="rounded bg-base-300 px-2 py-1 font-mono text-sm">{{ $endpoint->secretPreview() }}</code>
                </div>
                <p class="text-xs text-base-content/60">{{ __('integration.webhook.secret.rotate_help') }}</p>
            </div>
        @endif
    </x-form-group>

    @if ($isEdit)
        <x-slot:footerExtra>
            <div class="flex flex-wrap gap-2">
                <x-action-form :action="route('admin.webhooks.rotate-secret', $endpoint)"
                      :confirm="__('integration.webhook.secret.rotate_confirm')"
                      :confirm-label="__('integration.webhook.action.rotate_secret')">
                    <x-icon-btn icon="autorenew" tone="warning" size="sm" type="submit" show-label>{{ __('integration.webhook.action.rotate_secret') }}</x-icon-btn>
                </x-action-form>
                <x-action-form :action="route('admin.webhooks.destroy', $endpoint)"
                      method="DELETE"
                      :confirm="__('integration.webhook.action.delete_confirm')"
                      :confirm-label="__('integration.webhook.action.delete')">
                    <x-icon-btn icon="delete" tone="error" size="sm" type="submit" show-label>{{ __('integration.webhook.action.delete') }}</x-icon-btn>
                </x-action-form>
            </div>
        </x-slot:footerExtra>
    @endif
</x-modal>
