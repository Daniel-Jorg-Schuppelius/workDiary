{{--
  Created on   : Mon Oct 19 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _portal_reauth_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Vor „Zugang anlegen“ (Phase 153, MVP-1232): Passwortbestätigung fehlt — zuerst bestätigen, dann erneut öffnen. --}}
<x-modal
    :title="__('customer_portal.direct.title')"
    :eyebrow="$customer->name"
    icon="lock"
    tone="warning"
    hide-footer>
    <p class="text-sm">{{ __('customer_portal.direct.reauth_hint') }}</p>
    <div class="mt-4 flex justify-end">
        <x-button :href="route('password.confirm')" tone="primary" icon="lock_open">
            <span>{{ __('customer_portal.direct.reauth_action') }}</span>
        </x-button>
    </div>
</x-modal>
