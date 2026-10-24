{{--
  Created on   : Fri Oct 23 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _link.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Link zur Quellaufgabe im Aufgabendialog (Slot task-dialog.links). $url ist
  vom Adapter geprüft (nur https auf dem Anbieter-Host), $label der Linktext.
--}}
<a href="{{ $url }}" target="_blank" rel="noopener noreferrer" class="link link-primary text-sm inline-flex items-center gap-1">
    <x-icon name="open_in_new" class="text-base" />{{ $label }}
</a>
