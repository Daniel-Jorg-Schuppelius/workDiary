{{--
  Created on   : Mon Sep 14 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _enroll_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Teilnehmer einschreiben (Feature 149, MVP-778): eine Person aus der
  Organisation, eine bekannte externe Person oder eine neue externe Person.
  Die Felder je Art blendet `reveal` ein (CSP-Build: nur Methodenaufrufe).
  Variablen: $course, $users (id, name), $externals (id, name, email), $parties.
--}}
<x-modal
    :title="__('learning.action.add_participant')"
    :eyebrow="$course->title"
    icon="person_add"
    tone="primary"
    :action="route('learning.courses.enrollments.store', $course)"
    method="POST"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('learning.action.add_participant')">

    <div x-data="reveal(@js(old('learner_kind', 'user')))">
        <x-form-group :legend="__('learning.field.learner')" icon="person" tone="primary" cols="2">
            <x-select-field name="learner_kind" :label="__('learning.field.learner_kind')" required span="2" x-model="value">
                <option value="user" @selected(old('learner_kind', 'user') === 'user')>{{ __('learning.field.learner_user') }}</option>
                <option value="external" @selected(old('learner_kind') === 'external')>{{ __('learning.field.learner_external') }}</option>
                <option value="external_new" @selected(old('learner_kind') === 'external_new')>{{ __('learning.field.learner_external_new') }}</option>
            </x-select-field>

            <div class="col-span-2" x-show="is('user')" x-cloak>
                <x-select-field name="user_id" :label="__('learning.field.learner_user')">
                    <option value="">{{ __('learning.field.choose_person') }}</option>
                    @foreach ($users as $user)
                        <option value="{{ $user->sqid }}" @selected((string) old('user_id') === (string) $user->sqid)>{{ $user->name }}</option>
                    @endforeach
                </x-select-field>
            </div>

            <div class="col-span-2" x-show="is('external')" x-cloak>
                <x-select-field name="external_participant_id" :label="__('learning.field.learner_external')">
                    <option value="">{{ __('learning.field.choose_person') }}</option>
                    @foreach ($externals as $external)
                        <option value="{{ $external->sqid }}" @selected((string) old('external_participant_id') === (string) $external->sqid)>{{ $external->name }} · {{ $external->email }}</option>
                    @endforeach
                </x-select-field>
            </div>

            <div class="col-span-2 grid gap-3 sm:grid-cols-2" x-show="is('external_new')" x-cloak>
                <x-input-field name="name" :label="__('learning.field.name')" minlength="2" maxlength="160" :value="old('name')" />
                <x-input-field name="email" type="email" :label="__('learning.field.email')" maxlength="190" :value="old('email')" />
                <x-select-field name="party" :label="__('learning.field.external_party')" span="2">
                    @foreach ($parties as $party)
                        <option value="{{ $party->value }}" @selected(old('party', 'other') === $party->value)>{{ $party->label() }}</option>
                    @endforeach
                </x-select-field>
            </div>
        </x-form-group>

        <x-form-group :legend="__('learning.field.deadlines')" icon="event" tone="neutral" cols="2">
            <x-input-field name="due_at" type="date" :label="__('learning.field.due_at')" :value="old('due_at')" />
            <x-input-field name="access_until" type="date" :label="__('learning.field.access_until')"
                           :hint="__('learning.help.access_until_default')" :value="old('access_until')" />
            <x-input-field name="reason" :label="__('learning.field.reason')" maxlength="255" span="2" :value="old('reason')" />
            <div class="col-span-2" x-show="isAny('external', 'external_new')" x-cloak>
                <x-checkbox-field name="send_link" :label="__('learning.field.send_link')"
                                  :hint="__('learning.help.send_link')" :checked="(bool) old('send_link', true)" />
            </div>
        </x-form-group>
    </div>
</x-modal>
