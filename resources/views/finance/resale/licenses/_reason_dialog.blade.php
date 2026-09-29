{{--
  Created on   : Tue Sep 29 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _reason_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Rücknahme, Sperre und Freigabe einer Lizenz (MVP-1024) — jeweils mit
  Grund; die Freigabe zusätzlich mit ausdrücklicher Bestätigung.
  Erwartet: $mode (return|block|unblock), $action, $license, optional $blockedReason.
--}}
<x-modal
    :title="__('resale.license.reason.' . $mode . '.title')"
    :eyebrow="$license"
    :icon="['return' => 'undo', 'block' => 'block', 'unblock' => 'lock_open'][$mode]"
    :tone="$mode === 'unblock' ? 'primary' : 'warning'"
    :action="$action"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('resale.license.reason.' . $mode . '.submit')">
    <p class="text-sm text-muted">{{ __('resale.license.reason.' . $mode . '.hint') }}</p>
    @if (($blockedReason ?? null) !== null)
        <p class="text-sm">{{ __('resale.license.reason.blocked_because', ['reason' => $blockedReason]) }}</p>
    @endif
    <x-input-field name="reason" :label="__('resale.license.field.reason')" :value="old('reason')" required maxlength="255" />
    @if ($mode === 'unblock')
        <x-checkbox-field name="confirmed" :toggle="false" :label="__('resale.license.reason.unblock.confirm')" />
    @endif
</x-modal>
