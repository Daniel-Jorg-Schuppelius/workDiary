{{--
  Created on   : Sat Nov 21 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _link_findings_bulk.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Sammelleiste der Prüffälle (MVP-1784): gewählte Bezüge in einem Schritt lösen — die Seite lädt einmal statt je Bezug.
     Steht innerhalb von [data-bulk-form]; die Zeilen behalten ihre eigenen Formulare. --}}
<form method="POST" action="{{ route('finance.resale.links.bulk-destroy') }}" data-bulk-ids-form class="contents"
      data-confirm-dialog data-confirm-message="{{ __('resale.link.bulk_unlink_confirm') }}" data-confirm-tone="warning">
    @csrf
    <x-bulk-toolbar :label="__('resale.link.bulk_selected')" tone="warning" icon="link_off">
        <x-slot:actions>
            <x-icon-btn icon="link_off" size="sm" tone="primary" type="submit" show-label>{{ __('resale.link.bulk_unlink') }}</x-icon-btn>
        </x-slot:actions>
    </x-bulk-toolbar>
</form>
