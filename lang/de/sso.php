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

    'field' => [
        'label' => 'Bezeichnung',
        'label_placeholder' => 'z. B. Entra ID Produktion',
        'team_none' => '— kein Team —',
    ],

    'action' => [
        'issue' => 'Ausstellen',
        'revoke' => 'Widerrufen',
        'save_mapping' => 'Speichern',
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
        'revoked' => 'Widerrufen',
    ],

    'flash' => [
        'token_issued' => 'SCIM-Token ausgestellt.',
        'token_revoked' => 'SCIM-Token widerrufen.',
        'group_mapped' => 'Team-Zuordnung gespeichert.',
    ],
];
