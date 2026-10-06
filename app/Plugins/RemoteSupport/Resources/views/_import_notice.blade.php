{{--
  Created on   : Tue Sep 29 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _import_notice.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Hinweis im Importlauf (Slot `import-run.notice`, MVP-1041): Sitzungen ohne
  Geräte-Zuordnung liegen in der Fernwartungs-Inbox. Erwartet: $skipped.
--}}
<div role="status" class="alert alert-info">
    <x-icon name="inbox" />
    <span>
        {{ __(':n Sitzungen konnten keinem Gerät zugeordnet werden und liegen in der Fernwartungs-Inbox. Ordnen Sie die Geräte-IDs einem Asset zu, um sie als Zeiteinträge zu buchen.', ['n' => $skipped]) }}
    </span>
    <x-button :href="route('admin.remote-support.pending.index')" tone="primary" size="sm" icon="arrow_forward">{{ __('Zur Inbox') }}</x-button>
</div>
