{{--
  Created on   : Tue Sep 29 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _schedules_notice.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Hinweis der Abrechnungspläne (Slot `invoice-schedule-index.notice`,
  MVP-1039): Serien laufen lokal, die Übergabe an Lexware getrennt.
  Erwartet: $plan (Tarifname).
--}}
<div role="status" class="alert alert-info text-sm">
    <span>{{ __('lexware.schedules.hint', ['plan' => $plan]) }}</span>
    <a href="{{ route('lexoffice.handover.index') }}" class="link">{{ __('lexware.handover.title') }}</a>
</div>
