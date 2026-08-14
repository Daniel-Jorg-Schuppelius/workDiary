{{--
  Created on   : Tue May 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _pagination.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Wiederverwendbarer Pagination-Block für Duties-Tabs.
     Delegiert an die zentrale <x-pagination>-Komponente im stehenden Modus
     (Footer-Panel unter dem main), damit ALLE Seiten dasselbe Verhalten nutzen. --}}
<x-pagination :paginator="$paginator" standing />
