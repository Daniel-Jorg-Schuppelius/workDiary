{{--
  Created on   : Mon Jun 15 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _actions.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{--
  Tagesabschluss-Aktionen als sticky CTA-Leiste (MVP-015 §2.6/§8) + Dialoge.
  Genutzt von der Tagesabschluss-Seite (Fremdtage/Admin). Die „Heute"-Seite
  platziert die Buttons stattdessen über _action_buttons in der Toolbar und
  bindet die Dialoge separat ein.
  Erwartet aus dem Host-Scope: $isOwnDay, $effectiveStatus, $monthLocked,
  $hasBlocking, $isFuture, $closure, $day, $targetUser, $correctionRequests.
--}}
<div class="sticky bottom-0 z-10 -mx-2 rounded-box border border-base-300 bg-base-100/95 p-3 shadow-xs backdrop-blur lg:static lg:mx-0">
    <div class="flex flex-wrap items-center justify-end gap-2">
        @include('time-approval.day._action_buttons')
    </div>
</div>

@include('time-approval.day._correction_dialog')
@include('time-approval.day._reopen_dialog')
