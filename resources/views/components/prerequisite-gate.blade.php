{{-- <x-prerequisite-gate :result="..."> (Feature 067, MVP-181):
     rendert den Inhalt nur bei erfüllten Voraussetzungen; sonst einen
     geführten Blocked-State mit Setup-CTA (falls der Betrachter die
     Zielaktion darf) bzw. Rollen-Hinweis. Nicht-blockierende Zustände
     (missing_optional) zeigen den Hinweis ÜBER dem Inhalt. --}}
@props([
    'result',
    'icon' => 'settings_alert',
])
@php /** @var \App\Prerequisites\PrerequisiteResult $result */ @endphp
@if ($result->state === \App\Prerequisites\PrerequisiteState::Ready)
    {{ $slot }}
@elseif (! $result->state->blocks())
    <div role="alert" class="alert alert-info alert-soft mb-3 text-sm">
        <x-icon name="info" />
        <span>{{ $result->message() }}</span>
        @if ($result->ctaVisible())
            <x-button :href="$result->ctaUrl()" size="sm" tone="primary">{{ __($result->ctaLabelKey) }}</x-button>
        @endif
    </div>
    {{ $slot }}
@else
    <x-empty-state framed :icon="$icon" :tone="$result->state->tone()"
                   :title="__('prerequisites.blocked.' . $result->state->value)"
                   :message="$result->message()">
        <x-slot:action>
            @if ($result->ctaVisible())
                <x-button :href="$result->ctaUrl()" tone="primary" size="sm" icon="arrow_forward">
                    {{ __($result->ctaLabelKey) }}
                </x-button>
            @elseif ($result->responsibleRoleKey !== null)
                <span class="text-sm text-base-content/70">
                    {{ __('prerequisites.contact_role', ['role' => __($result->responsibleRoleKey)]) }}
                </span>
            @endif
        </x-slot:action>
    </x-empty-state>
@endif
