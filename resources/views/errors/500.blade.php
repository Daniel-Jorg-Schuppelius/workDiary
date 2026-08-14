{{--
  Created on   : Thu Jul 09 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : 500.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@include('errors._page', [
    'code' => 500,
    'icon' => 'error',
    'tone' => 'error',
    'title' => __('errors.500.title'),
    'message' => __('errors.500.message'),
])
