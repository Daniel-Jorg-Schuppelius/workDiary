{{--
  Created on   : Fri Sep 04 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _import_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Anbieter-Exporte ins Register bringen (Feature 152, MVP-759): Telekom-
  Käufe (CSV), Quality-Hosting-Verträge (XLSX), Preisliste (XLSX). Upsert
  über Anbieter und Kennung; unbekannte Firmen landen in der Inbox.
--}}
<x-modal
    :title="__('resale.import.title')"
    icon="upload"
    tone="primary"
    size="lg"
    :action="route('finance.resale.import.store')"
    method="POST"
    enctype="multipart/form-data"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('resale.import.submit')"
>
    <div class="text-sm text-base-content/70">{{ __('resale.import.hint') }}</div>

    <x-input-field name="telekom" type="file" :label="__('resale.import.telekom')" accept=".csv,.txt,text/csv,text/plain" />
    <x-input-field name="qualityhosting" type="file" :label="__('resale.import.qualityhosting')" accept=".xlsx,.xlsm,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" />
    <x-input-field name="pricelist" type="file" :label="__('resale.import.pricelist')" accept=".xlsx,.xlsm,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" :hint="__('resale.import.pricelist_hint')" />
</x-modal>
