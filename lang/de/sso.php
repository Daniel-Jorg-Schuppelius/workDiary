<?php
/*
 * Created on   : Sun Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : sso.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

return [
    'title' => 'SSO & Verzeichnisdienste',
    'intro' => 'SCIM-2.0-Provisionierung: Ihr Identitätsanbieter (Entra ID, Keycloak, Okta …) legt Konten an, aktualisiert und deaktiviert sie. Ein deaktiviertes Konto kann sich sofort nicht mehr anmelden; Rollen und fachliche Daten bleiben in WorkDiary. Authentifizierung über einen Bearer-Token je Organisation.',
    'base_url' => 'SCIM-Basis-URL',

    'new_token_heading' => 'Neues Token',
    'new_token_hint' => 'Jetzt kopieren — der Klartext wird nur dieses eine Mal angezeigt und danach nur noch als Hash gespeichert.',

    'issue_heading' => 'Token ausstellen',
    'tokens_heading' => 'Ausgestellte Token',
    'no_tokens' => 'Noch kein Token ausgestellt.',

    'groups_heading' => 'SCIM-Gruppen → Team',
    'groups_hint' => 'Vom Identitätsanbieter provisionierte Gruppen. Eine Zuordnung zu einem Team spiegelt die Mitglieder nach WorkDiary (team_user) — Rollen werden dabei nie vergeben.',
    'no_groups' => 'Noch keine SCIM-Gruppe provisioniert.',

    'oidc_heading' => 'OIDC-Single-Sign-on',
    'oidc_hint' => 'Anmeldung über OpenID Connect (Entra ID, Keycloak, Google …). Kontoverknüpfung ausschließlich über Issuer + Subject; SSO legt nie Konten an und vergibt nie Rollen. Nach IdP-Login ist die Mehr-Faktor-Prüfung Sache des Identitätsanbieters.',
    'saml_heading' => 'SAML 2.0',
    'saml_hint' => 'SP-initiierte Anmeldung über SAML 2.0. Assertions müssen signiert sein; IdP-initiierte (unsolicited) Antworten werden abgelehnt. Für Zertifikatsrotation kann ein zweites Zertifikat parallel hinterlegt werden.',

    'break_glass_heading' => 'Break-Glass-Konten',
    'break_glass_hint' => 'Nicht föderierte Notfallkonten, die sich trotz SSO-Pflicht weiter mit Passwort anmelden dürfen. Jede Nutzung wird auditiert. Mindestens ein Konto behalten, sonst sperrt ein IdP-Ausfall die Organisation aus.',
    'no_break_glass' => 'Kein Break-Glass-Konto festgelegt.',

    'discover' => [
        'hint' => 'Geben Sie die Kennung Ihrer Organisation ein, um die Anmeldung über Ihren Identitätsanbieter zu starten.',
        'org_label' => 'Organisations-Kennung',
        'org_placeholder' => 'z. B. muster-gmbh',
        'submit' => 'Weiter zum Identitätsanbieter',
        'back_to_login' => 'Zurück zur Anmeldung',
    ],

    'protocol' => [
        'oidc' => 'OIDC',
        'saml' => 'SAML 2.0',
    ],

    'field' => [
        'label' => 'Bezeichnung',
        'label_placeholder' => 'z. B. Entra ID Produktion',
        'team_none' => '— kein Team —',
        'start_url' => 'SSO-Start-URL',
        'callback_url' => 'Redirect-/Callback-URL (beim IdP registrieren)',
        'acs_url' => 'ACS-URL (beim IdP registrieren)',
        'metadata_url' => 'SP-Metadata-URL',
        'issuer' => 'Issuer',
        'client_id' => 'Client-ID',
        'client_secret' => 'Client-Secret',
        'secret_keep' => 'leer lassen = unverändert',
        'scopes' => 'Scopes',
        'idp_entity_id' => 'IdP-Entity-ID',
        'idp_sso_url' => 'IdP-SSO-URL',
        'idp_certificate' => 'IdP-Signaturzertifikat (PEM)',
        'idp_certificate_next' => 'Folgezertifikat (Rotation, optional)',
        'idp_certificate_next_hint' => 'Während der Zertifikatsrotation werden beide akzeptiert.',
        'active' => 'Aktiv',
        'enforced' => 'SSO-Pflicht',
        'enforced_hint' => 'Sperrt den Passwort-Login aller Konten dieser Organisation (außer Break-Glass).',
        'email_link' => 'E-Mail-Erstverknüpfung',
        'email_link_hint' => 'Beim ersten SSO-Login ein vorhandenes Konto über die E-Mail verknüpfen (nur bei genau einem Treffer). Danach zählt nur noch Issuer + Subject.',
        'private_network' => 'IdP im privaten Netz erlauben',
        'private_network_hint' => 'SSRF-Schutz-Ausnahme für On-Premise-IdPs (z. B. internes Keycloak).',
        'break_glass_user' => 'Konto',
    ],

    'action' => [
        'issue' => 'Ausstellen',
        'revoke' => 'Widerrufen',
        'save_mapping' => 'Speichern',
        'save_connection' => 'Verbindung speichern',
        'test_connection' => 'Verbindung testen',
        'remove_connection' => 'Verbindung entfernen',
        'break_glass_add' => 'Als Break-Glass-Konto festlegen',
        'break_glass_remove' => 'Entziehen',
    ],

    'col' => [
        'status' => 'Status',
        'last_used' => 'Zuletzt genutzt',
        'group' => 'Gruppe',
        'members' => 'Mitglieder',
        'team' => 'Team',
    ],

    'status' => [
        'active' => 'Aktiv',
        'inactive' => 'Inaktiv',
        'revoked' => 'Widerrufen',
        'enforced' => 'SSO-Pflicht',
    ],

    'flash' => [
        'token_issued' => 'SCIM-Token ausgestellt.',
        'token_revoked' => 'SCIM-Token widerrufen.',
        'group_mapped' => 'Team-Zuordnung gespeichert.',
        'connection_saved' => ':protocol-Verbindung gespeichert.',
        'connection_ok' => ':protocol-Verbindung erfolgreich geprüft.',
        'connection_removed' => 'Verbindung entfernt.',
        'break_glass_added' => 'Break-Glass-Konto festgelegt.',
        'break_glass_removed' => 'Break-Glass-Status entzogen.',
    ],

    'error' => [
        'discovery_failed' => 'Die OIDC-Discovery des Identitätsanbieters ist nicht erreichbar oder unvollständig.',
        'issuer_mismatch' => 'Der Issuer der Discovery-Antwort stimmt nicht mit der Konfiguration überein.',
        'token_exchange_failed' => 'Der Code-Tausch beim Identitätsanbieter ist fehlgeschlagen.',
        'token_invalid' => 'Das Anmelde-Token des Identitätsanbieters ist ungültig.',
        'token_expired' => 'Das Anmelde-Token des Identitätsanbieters ist abgelaufen.',
        'jwks_failed' => 'Die Signaturschlüssel des Identitätsanbieters konnten nicht geladen werden.',
        'no_account' => 'Für diese Identität ist kein WorkDiary-Konto verknüpft. Bitte wenden Sie sich an Ihre Administration.',
        'org_without_sso' => 'Für diese Kennung ist kein Single-Sign-on eingerichtet.',
        'flow_expired' => 'Die SSO-Anmeldung ist abgelaufen. Bitte erneut versuchen.',
        'module_disabled' => 'Single-Sign-on ist für diese Organisation nicht verfügbar.',
        'url_not_public' => 'Die URL ist nicht öffentlich erreichbar. Für interne Identitätsanbieter die Option „IdP im privaten Netz erlauben" setzen.',
        'saml_invalid' => 'Die SAML-Antwort ist ungültig.',
        'saml_unsolicited' => 'Unaufgeforderte (IdP-initiierte) SAML-Antworten werden abgelehnt. Bitte die Anmeldung über WorkDiary starten.',
        'saml_no_nameid' => 'Die SAML-Antwort enthält keine NameID. Beim IdP (z. B. ADFS) eine NameID-Claim-Regel hinterlegen.',
        'saml_settings_invalid' => 'Die SAML-Konfiguration ist unvollständig oder ungültig.',
        'saml_certificate_invalid' => 'Das IdP-Zertifikat konnte nicht gelesen werden (PEM erwartet).',
    ],
];
