{{--
  Created on   : Mon Oct 12 2026
  Author       : Daniel Jörg Schuppelius
  Author Uri   : https://schuppelius.org
  Filename     : _form_dialog.blade.php
  License      : AGPL-3.0-or-later
  License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
--}}
{{-- Dialog: Postfach anlegen/bearbeiten (Feature 056, MVP-1143). Der Transport steht fest; IMAP-Zugang nur bei IMAP. --}}
@php
    /** @var \App\Models\Mail\EmailConnection $connection */
    /** @var \App\Services\Mail\Contracts\MailboxTransport|null $transport */
    $isEdit = $connection->exists;
    $imap = $connection->usesImap();
    // Auswahlliste statt Vorschlagsfeld: ein datalist filtert nach dem eingetragenen Wert und verbirgt die übrigen Ordner.
    $folderSelect = ! $imap || $folders !== [];
    $folderValue = (string) old('folder', $connection->folder ?: 'INBOX');
    $processedValue = (string) old('processed_folder', $connection->processed_folder);
    $folderChoices = static fn (string $current): array => $current === '' || in_array($current, $folders, true) ? $folders : [$current, ...$folders];
@endphp
<x-modal
    :title="$isEdit ? __('mail.title_edit') : __('mail.add_heading')"
    :eyebrow="$transport?->label() ?? __('mail.transport.imap')"
    icon="mail"
    tone="primary"
    :action="$isEdit ? route('admin.mail.connection.update', $connection) : route('admin.mail.connection.store')"
    :method="$isEdit ? 'PUT' : 'POST'"
    :form-data="['data-entry-form' => '']"
    autocomplete="off"
    :submit-label="__('mail.action.save')"
>
    @unless ($isEdit)
        <input type="hidden" name="transport" value="{{ $connection->transport ?: \App\Models\Mail\EmailConnection::TRANSPORT_IMAP }}">
    @endunless

    @if ($unavailable)
        <div role="alert" class="alert alert-warning alert-soft">
            <x-icon name="warning" />
            <span>{{ $unavailable }}</span>
        </div>
    @endif

    <x-form-group :legend="__('mail.group.mailbox')" icon="mail" tone="primary" cols="2">
        <x-input-field name="name" :label="__('mail.field.name')" :value="old('name', $connection->name)" required maxlength="120" />

        @if ($imap)
            <x-input-field name="host" :label="__('mail.field.host')" :value="old('host', $connection->host)" placeholder="imap.example.com" required maxlength="190" />
            <x-input-field name="port" type="number" :label="__('mail.field.port')" :value="old('port', $connection->port ?: 993)" min="1" max="65535" required />
            <x-select-field name="encryption" :label="__('mail.field.encryption')" required>
                @foreach (['ssl' => 'SSL', 'tls' => 'STARTTLS', 'none' => __('mail.encryption.none')] as $value => $label)
                    <option value="{{ $value }}" @selected(old('encryption', $connection->encryption ?: 'ssl') === $value)>{{ $label }}</option>
                @endforeach
            </x-select-field>
            <x-input-field name="username" :label="__('mail.field.username')" :value="old('username', $connection->username)" autocomplete="off" required maxlength="190" />
            <x-input-field name="password" type="password" :label="__('mail.field.password')" autocomplete="new-password" maxlength="255"
                           :required="! $isEdit" :hint="$isEdit ? __('mail.field.password_keep') : null" />
        @else
            <x-input-field name="mailbox_address" type="email" :label="__('mail.field.mailbox_address')" :value="old('mailbox_address', $connection->mailbox_address)"
                           maxlength="190" :hint="__('mail.field.mailbox_address_hint')" span="2"
                           :data-mail-folder-source="route('admin.mail.folders', ['transport' => $connection->transport])" />
        @endif
    </x-form-group>

    <x-form-group :legend="__('mail.group.folders')" icon="folder" tone="primary" cols="2">
        @if ($folderSelect)
            <x-select-field name="folder" :label="__('mail.field.folder')" required data-mail-folder-list>
                @foreach ($folderChoices($folderValue) as $path)
                    <option value="{{ $path }}" @selected($path === $folderValue)>{{ $path }}</option>
                @endforeach
            </x-select-field>
            <x-select-field name="processed_folder" :label="__('mail.field.processed_folder')" data-mail-folder-list>
                <option value="">{{ __('mail.field.processed_folder_none') }}</option>
                @foreach ($folderChoices($processedValue) as $path)
                    <option value="{{ $path }}" @selected($path === $processedValue)>{{ $path }}</option>
                @endforeach
            </x-select-field>
        @else
            <x-input-field name="folder" :label="__('mail.field.folder')" :value="$folderValue" required maxlength="190" />
            <x-input-field name="processed_folder" :label="__('mail.field.processed_folder')" :value="$processedValue"
                           :placeholder="__('mail.field.processed_folder_placeholder')" maxlength="190" />
        @endif
        <x-input-field name="fetch_since" type="date" :label="__('mail.field.fetch_since')" :value="old('fetch_since', $connection->fetch_since?->toDateString())"
                       :max="now()->toDateString()" :hint="__('mail.field.fetch_since_hint')" span="2" />
        <p class="text-xs text-muted md:col-span-2" data-mail-folder-status>
            @if ($foldersError && $folderSelect)
                {{ __('mail.field.folder_hint_unavailable', ['error' => \Illuminate\Support\Str::limit($foldersError, 160)]) }}
            @elseif ($folderSelect)
                {{ __('mail.field.folder_hint_select') }}
            @elseif ($foldersError)
                {{ __('mail.field.folder_hint_error', ['error' => \Illuminate\Support\Str::limit($foldersError, 160)]) }}
            @else
                {{ __('mail.field.folder_hint_free') }}
            @endif
        </p>
    </x-form-group>

    <x-form-group :legend="__('mail.group.purpose')" icon="tune" tone="primary">
        <x-checkbox-field name="active" :label="__('mail.field.active')" :checked="(bool) old('active', $connection->active ?? true)" />
        <x-checkbox-field name="einvoice_intake" :label="__('mail.field.einvoice_intake')" :checked="(bool) old('einvoice_intake', $connection->einvoice_intake)"
                          :hint="__('mail.field.einvoice_intake_hint')" />
        <x-checkbox-field name="callreport_intake" :label="__('mail.field.callreport_intake')" :checked="(bool) old('callreport_intake', $connection->callreport_intake)" />
    </x-form-group>
</x-modal>
