{{--
  Created on   : Tue Sep 15 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : learning-trainer-question.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
@component('mail::message')
# {{ __('learning.mail.question_heading', ['course' => $courseTitle]) }}

{{ __('learning.mail.question_intro', ['name' => $learner->name, 'course' => $courseTitle]) }}

@component('mail::panel')
{{ $question }}
@endcomponent

{{ __('learning.mail.question_reply_hint') }}
@endcomponent
