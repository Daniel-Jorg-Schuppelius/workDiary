{{--
  Created on   : Sun Oct 04 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : page-fill.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Voll-Höhe-Seite: der Inhalt füllt den Viewport, die Liste scrollt in sich
  (`<x-table scroll="flex">`). Einbinden vor `@section('content')`.
--}}
@section('main-class', 'min-h-0 flex flex-col lg:overflow-clip')
