{{--
  Created on   : Tue Sep 15 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _settings_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html

  Einstellungen der Lernplattform (Feature 149, MVP-786). Variablen:
  $scopeToCourses, $gamificationEnabled, $leaderboardEnabled, $embedHosts,
  $categories (Kurskategorien, eine je Zeile — MVP-788).
--}}
<x-modal
    :title="__('learning.title.settings')"
    :eyebrow="__('learning.section')"
    icon="settings"
    tone="primary"
    :action="route('learning.settings.update')"
    method="PUT"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('learning.action.save')">

    <x-form-group :legend="__('learning.field.trainer_scoping')" icon="badge" tone="primary" cols="1">
        <x-checkbox-field name="scope_to_courses" :label="__('learning.field.scope_to_courses')"
                          :hint="__('learning.help.scope_to_courses')" :checked="(bool) old('scope_to_courses', $scopeToCourses)" />
    </x-form-group>

    <x-form-group :legend="__('learning.field.gamification')" icon="stars" tone="warning" cols="1">
        <x-checkbox-field name="gamification_enabled" :label="__('learning.field.gamification_enabled')"
                          :hint="__('learning.help.gamification')" :checked="(bool) old('gamification_enabled', $gamificationEnabled)" />
        <x-checkbox-field name="leaderboard_enabled" :label="__('learning.field.leaderboard_enabled')"
                          :hint="__('learning.help.leaderboard')" :checked="(bool) old('leaderboard_enabled', $leaderboardEnabled)" />
    </x-form-group>

    <x-form-group :legend="__('learning.field.categories')" icon="category" tone="info" cols="1">
        <x-textarea-field name="categories" :label="__('learning.field.categories_list')" rows="4" maxlength="4000"
                          :hint="__('learning.help.categories_list')" :value="old('categories', $categories)" />
    </x-form-group>

    <x-form-group :legend="__('learning.field.embed_hosts')" icon="public" tone="neutral" cols="1">
        <x-textarea-field name="embed_hosts" :label="__('learning.field.embed_hosts')" rows="3" maxlength="2000"
                          :hint="__('learning.help.embed_hosts')" :value="old('embed_hosts', $embedHosts)" />
    </x-form-group>
</x-modal>
