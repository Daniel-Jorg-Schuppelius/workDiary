{{--
  Created on   : Mon Nov 02 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _voucher_sync.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Beleg-Sync von Hand anstoßen (Slot plugin-settings.actions im Einstellungsdialog,
  Vollscan 2026-11-02 gap-12). Eigenes Formular außerhalb des Einstellungsformulars;
  der Lauf ist inkrementell und läuft im Hintergrund (SyncVouchersJob).
--}}
<x-action-form :action="route('lexoffice.vouchers.sync')"
               :confirm="__('lexoffice::finance.vouchers.sync_confirm')"
               confirm-icon="cloud_sync" confirm-tone="primary"
               :confirm-label="__('lexoffice::finance.vouchers.sync_now')">
    <x-icon-btn icon="cloud_sync" tone="outline" size="sm" type="submit" show-label
                :title="__('lexoffice::finance.vouchers.sync_hint')">{{ __('lexoffice::finance.vouchers.sync_now') }}</x-icon-btn>
</x-action-form>
