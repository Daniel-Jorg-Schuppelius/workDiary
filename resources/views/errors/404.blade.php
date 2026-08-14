{{--
  Created on   : Thu Jul 09 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : 404.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@include('errors._page', [
    'code' => 404,
    'icon' => 'search_off',
    'tone' => 'primary',
    'title' => __('errors.404.title'),
    'message' => __('errors.404.message'),
])
