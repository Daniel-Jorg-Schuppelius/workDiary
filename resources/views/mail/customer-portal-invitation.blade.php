@component('mail::message')
# {{ __('Willkommen im Kundenportal von :org', ['org' => $brandName]) }}

{{ __('Hallo :name,', ['name' => $portalUser->name]) }}

{{ __(':org hat für Sie einen Zugang zum Kundenportal eingerichtet. Über den folgenden Link legen Sie Ihr persönliches Passwort fest und melden sich anschließend an.', ['org' => $brandName]) }}

@component('mail::button', ['url' => $acceptUrl])
{{ __('Passwort festlegen') }}
@endcomponent

@component('mail::panel')
{{ __('Der Link ist einmalig verwendbar und gültig bis :date.', ['date' => optional($portalUser->portal_invite_expires_at)->format('d.m.Y')]) }}
@endcomponent

{{ __('Wenn Sie diese Einladung nicht erwartet haben, ignorieren Sie diese E-Mail — es wird kein Zugang ohne Passwortvergabe aktiviert.') }}
@endcomponent
