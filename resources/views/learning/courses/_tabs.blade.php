{{--
  Created on   : Mon Sep 28 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _tabs.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Reiter eines Kurses (MVP-969); erwartet $course. --}}
<x-tab-nav class="flex-none" :items="[
    ['route' => 'learning.courses.show', 'params' => $course, 'routeIs' => 'learning.courses.show', 'icon' => 'school', 'label' => __('learning.field.course')],
    ['route' => 'learning.courses.gradebook.show', 'params' => $course, 'routeIs' => 'learning.courses.gradebook.show', 'icon' => 'grading', 'label' => __('learning.title.gradebook'),
     'when' => auth()->user()?->can(\App\Enums\User\Permission::LearningGrade->value) ?? false],
]" />
