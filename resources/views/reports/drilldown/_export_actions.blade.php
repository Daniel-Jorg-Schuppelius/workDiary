{{--
  Created on   : Sun Oct 04 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _export_actions.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Exportleiste eines Drilldowns: $route und $params (ohne `export`).
--}}
<x-report-export :url="fn (string $format) => route($route, array_filter($params + ['export' => $format], fn ($v) => $v !== null && $v !== ''))" />
