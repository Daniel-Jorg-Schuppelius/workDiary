{{--
  Created on   : Thu Aug 27 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : onboarding.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Kachel „Onboarding" — rendert die bestehende Komponente x-onboarding-widget (inkl. Wegklicken).
--}}
<x-onboarding-widget :checklist="$checklist" :widget-dismissed-at="$widgetDismissedAt" />
