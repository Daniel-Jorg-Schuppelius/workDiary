{{--
  Created on   : Tue Sep 29 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _link.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Deep-Link zur verknüpften Todoist-Aufgabe (Feature 055, MVP-116) im
  Aufgabendialog (Slot `task-dialog.links`, MVP-1041). Erwartet: $url.
--}}
<a href="{{ $url }}" target="_blank" rel="noopener noreferrer" class="link link-primary text-sm inline-flex items-center gap-1">
    <x-icon name="open_in_new" class="text-base" />{{ __('todoist::todoist.task_link') }}
</a>
