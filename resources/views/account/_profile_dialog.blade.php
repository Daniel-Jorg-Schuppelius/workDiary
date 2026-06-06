@php
    $isDialog = $isDialog ?? true;
    /** @var \App\Models\User $user */
    $prefs = $user->preferences();
    $themes = (array) config('personalization.themes', []);
    $startpages = (array) config('personalization.startpages', []);
    $avatarMaxKb = (int) config('branding.limits.avatar_kb', 1024);
    $currentAvatar = $user->avatar();
    $avatarPreview = $currentAvatar !== null
        ? \App\Http\Controllers\AttachmentController::downloadUrl($currentAvatar)
        : null;
@endphp

<x-modal
    :title="__('Profil')"
    :eyebrow="__('Konto')"
    icon="person"
    tone="info"
    :action="route('account.profile.update')"
    method="PUT"
    enctype="multipart/form-data"
    :form-data="['data-entry-form' => '']"
    :submit-label="__('Speichern')">
    @if ($isDialog)
        <input type="hidden" name="_dialog_url" value="{{ route('account.profile.edit') }}?dialog=1">
    @endif

    <x-form-group :legend="__('Profildaten')" icon="person" tone="info" cols="2">
        <div class="fieldset">
            <label class="fieldset-label" for="name">{{ __('Anzeigename') }}</label>
            <input type="text" id="name" name="name" value="{{ old('name', $user->name) }}" class="input input-bordered w-full" required>
            @error('name')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>

        <div class="fieldset">
            <label class="fieldset-label" for="email">{{ __('E-Mail') }}</label>
            <input type="email" id="email" name="email" value="{{ old('email', $user->email) }}" class="input input-bordered w-full" required>
            @error('email')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>
    </x-form-group>

    @include('users._contact_fields', ['user' => $user])

    <x-form-group :legend="__('Profilbild')" icon="account_circle" tone="primary" cols="1"
                  :description="__('PNG, JPG oder WEBP. Max. :max KB.', ['max' => $avatarMaxKb])"
                  x-data="{
                      fileName: null,
                      error: null,
                      maxKb: {{ $avatarMaxKb }},
                      remove: false,
                      onChange(event) {
                          this.error = null;
                          const f = event.target.files && event.target.files[0];
                          if (!f) { this.fileName = null; return; }
                          if (f.size > this.maxKb * 1024) {
                              this.error = '{{ __('Datei ist größer als das Limit.') }}';
                              event.target.value = '';
                              this.fileName = null;
                              return;
                          }
                          this.fileName = f.name;
                          this.remove = false;
                      }
                  }">
        <div class="flex items-start gap-4">
            <div class="shrink-0">
                @if ($avatarPreview)
                    <img src="{{ $avatarPreview }}" alt="{{ __('Vorschau') }}"
                         class="w-20 h-20 rounded-full object-cover ring ring-base-300">
                @else
                    <div class="w-20 h-20 rounded-full bg-base-200 flex items-center justify-center text-base-content/50">
                        <x-icon name="person" />
                    </div>
                @endif
            </div>

            <div class="flex-1 space-y-2">
                <input type="file" name="avatar"
                       accept="image/png,image/jpeg,image/webp"
                       class="file-input file-input-bordered file-input-sm w-full"
                       @change="onChange($event)">
                <template x-if="fileName">
                    <p class="text-xs opacity-70" x-text="fileName"></p>
                </template>
                <p class="text-xs text-error" x-show="error" x-text="error"></p>
                @error('avatar')<p class="text-xs text-error">{{ $message }}</p>@enderror

                @if ($currentAvatar)
                    <label class="label cursor-pointer justify-start gap-2 py-1">
                        <input type="hidden" name="remove_avatar" value="0">
                        <input type="checkbox" name="remove_avatar" value="1" class="checkbox checkbox-sm checkbox-error"
                               x-model="remove">
                        <span class="label-text text-sm">{{ __('Aktuelles Bild beim Speichern entfernen') }}</span>
                    </label>
                @endif
            </div>
        </div>
    </x-form-group>

    <x-form-group :legend="__('Persönliche Präferenzen')" icon="tune" tone="ghost" cols="2">
        <div class="fieldset">
            <label class="fieldset-label">{{ __('Theme') }}</label>
            <select name="preferences[theme]" class="select select-bordered w-full">
                <option value="">{{ __('Standard') }}</option>
                @foreach ($themes as $theme)
                    <option value="{{ $theme }}" @selected(old('preferences.theme', $prefs['theme'] ?? '') === $theme)>{{ ucfirst($theme) }}</option>
                @endforeach
            </select>
            @error('preferences.theme')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>

        <div class="fieldset">
            <label class="fieldset-label">{{ __('Sprache') }}</label>
            <x-locale-select name="preferences[locale]"
                             :selected="old('preferences.locale', $prefs['locale'] ?? null)"
                             include-blank :blank-label="__('Organisation übernehmen')" />
            @error('preferences.locale')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>

        <div class="fieldset">
            <label class="fieldset-label">{{ __('Zeitzone') }}</label>
            <x-timezone-select name="preferences[timezone]"
                               :selected="old('preferences.timezone', $prefs['timezone'] ?? null)"
                               include-blank :blank-label="__('Organisation übernehmen')" />
            <p class="mt-1 text-xs text-base-content/60">{{ __('Überschreibt die Zeitzone der Organisation für deine Ansichten.') }}</p>
            @error('preferences.timezone')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>

        <div class="fieldset">
            <label class="fieldset-label">{{ __('Datumsformat') }}</label>
            <input type="text" name="preferences[date_format]" maxlength="32"
                   class="input input-bordered w-full"
                   placeholder="{{ config('personalization.defaults.date_format') }}"
                   value="{{ old('preferences.date_format', $prefs['date_format'] ?? '') }}">
            @error('preferences.date_format')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>

        <div class="fieldset">
            <label class="fieldset-label">{{ __('Uhrzeitformat') }}</label>
            <input type="text" name="preferences[time_format]" maxlength="32"
                   class="input input-bordered w-full"
                   placeholder="{{ config('personalization.defaults.time_format') }}"
                   value="{{ old('preferences.time_format', $prefs['time_format'] ?? '') }}">
            @error('preferences.time_format')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>

        <div class="fieldset md:col-span-2">
            <label class="fieldset-label">{{ __('Startseite nach dem Login') }}</label>
            <select name="preferences[startpage]" class="select select-bordered w-full">
                <option value="">{{ __('Standard') }}</option>
                @foreach ($startpages as $route)
                    <option value="{{ $route }}" @selected(old('preferences.startpage', $prefs['startpage'] ?? '') === $route)>{{ $route }}</option>
                @endforeach
            </select>
            @error('preferences.startpage')<p class="mt-1 text-sm text-error">{{ $message }}</p>@enderror
        </div>
    </x-form-group>

    <x-slot:footerExtra>
        <x-icon-btn icon="lock" size="sm"
                    data-entry-modal-trigger
                    :href="route('account.password.edit')"
                    show-label>{{ __('Passwort ändern') }}</x-icon-btn>
    </x-slot:footerExtra>
</x-modal>
