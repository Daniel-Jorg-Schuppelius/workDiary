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
    'title' => 'SSO e servizi di directory',
    'intro' => 'Provisioning SCIM 2.0: il vostro provider di identità (Entra ID, Keycloak, Okta …) crea, aggiorna e disattiva gli account. Un account disattivato non può più accedere immediatamente; ruoli e dati aziendali restano in WorkDiary. Autenticazione tramite un token bearer per organizzazione.',
    'base_url' => 'URL base SCIM',

    'new_token_heading' => 'Nuovo token',
    'new_token_hint' => 'Copialo ora — il testo in chiaro viene mostrato solo questa volta e successivamente memorizzato solo come hash.',

    'issue_heading' => 'Emetti un token',
    'tokens_heading' => 'Token emessi',
    'no_tokens' => 'Nessun token emesso finora.',

    'groups_heading' => 'Gruppi SCIM → team',
    'groups_hint' => 'Gruppi provisionati dal provider di identità. Associare un gruppo a un team rispecchia i suoi membri in WorkDiary (team_user); i ruoli non vengono mai assegnati.',
    'no_groups' => 'Nessun gruppo SCIM ancora provisionato.',

    'oidc_heading' => 'Single sign-on OIDC',
    'oidc_hint' => 'Accesso tramite OpenID Connect (Entra ID, Keycloak, Google …). Il collegamento dell’account usa solo issuer + subject; l’SSO non crea mai account e non assegna mai ruoli. Dopo l’accesso IdP, la verifica a più fattori è responsabilità del provider di identità.',
    'saml_heading' => 'SAML 2.0',
    'saml_hint' => 'Accesso avviato dall’SP tramite SAML 2.0. Le assertion devono essere firmate; le risposte avviate dall’IdP (non richieste) vengono rifiutate. Per la rotazione dei certificati è possibile memorizzare un secondo certificato in parallelo.',

    'break_glass_heading' => 'Account break-glass',
    'break_glass_hint' => 'Account di emergenza non federati che possono continuare ad accedere con password nonostante l’SSO obbligatorio. Ogni utilizzo viene registrato nell’audit. Mantenere almeno un account, altrimenti un guasto dell’IdP blocca l’organizzazione.',
    'no_break_glass' => 'Nessun account break-glass definito.',
    'domains_heading' => 'Domini e-mail',
    'domains_hint' => 'WorkDiary determina l’organizzazione dal dominio e-mail di accesso. I domini sono univoci a livello globale.',
    'no_domains' => 'Nessun dominio e-mail ancora associato.',

    'provider' => [
        'custom' => 'Provider OIDC personalizzato',
        'microsoft' => 'Microsoft 365',
        'google' => 'Google Workspace',
    ],

    'choose' => [
        'hint' => 'Per :org sono configurati più provider di accesso. Scegliere.',
    ],

    'discover' => [
        'hint' => 'Inserisci l’identificativo della tua organizzazione per avviare l’accesso tramite il tuo provider di identità.',
        'org_label' => 'Identificativo organizzazione',
        'org_placeholder' => 'ad es. acme-srl',
        'email_label' => 'Indirizzo e-mail',
        'email_placeholder' => 'es. nome@azienda.it',
        'submit' => 'Continua verso il provider di identità',
        'back_to_login' => 'Torna all’accesso',
    ],

    'protocol' => [
        'oidc' => 'OIDC',
        'saml' => 'SAML 2.0',
    ],

    'field' => [
        'label' => 'Etichetta',
        'label_placeholder' => 'ad es. Entra ID produzione',
        'tenant' => 'Directory (tenant)',
        'tenant_placeholder' => 'GUID del tenant o dominio verificato',
        'tenant_hint' => 'Specifico del tenant — mai common/organizations.',
        'tenant_keep' => 'lasciare vuoto = invariato',
        'domain' => 'Dominio e-mail',
        'domain_placeholder' => 'es. azienda.it',
        'team_none' => '— nessun team —',
        'start_url' => 'URL di avvio SSO',
        'callback_url' => 'URL di redirect/callback (da registrare presso l’IdP)',
        'acs_url' => 'URL ACS (da registrare presso l’IdP)',
        'metadata_url' => 'URL metadata SP',
        'issuer' => 'Issuer',
        'client_id' => 'Client ID',
        'client_secret' => 'Client secret',
        'secret_keep' => 'lasciare vuoto = invariato',
        'scopes' => 'Scope',
        'idp_entity_id' => 'Entity ID dell’IdP',
        'idp_sso_url' => 'URL SSO dell’IdP',
        'idp_certificate' => 'Certificato di firma dell’IdP (PEM)',
        'idp_certificate_next' => 'Certificato successore (rotazione, opzionale)',
        'idp_certificate_next_hint' => 'Durante la rotazione dei certificati vengono accettati entrambi.',
        'active' => 'Attivo',
        'enforced' => 'SSO obbligatorio',
        'enforced_hint' => 'Blocca l’accesso con password per tutti gli account di questa organizzazione (tranne break-glass).',
        'email_link' => 'Collegamento iniziale via e-mail',
        'jit' => 'Creare gli utenti al primo accesso (JIT)',
        'jit_hint' => 'Crea un nuovo account al primo accesso IdP (vale il limite di licenza). Non collega mai account esistenti — le collisioni e-mail vengono rifiutate.',
        'jit_role' => 'Ruolo predefinito JIT',
        'jit_role_none' => 'nessun ruolo',
        'email_link_hint' => 'Al primo accesso SSO, collega un account esistente tramite e-mail (solo con esattamente una corrispondenza). In seguito contano solo issuer + subject.',
        'private_network' => 'Consenti IdP su rete privata',
        'private_network_hint' => 'Eccezione alla protezione SSRF per IdP on-premise (ad es. Keycloak interno).',
        'break_glass_user' => 'Account',
    ],

    'action' => [
        'issue' => 'Emetti',
        'revoke' => 'Revoca',
        'save_mapping' => 'Salva',
        'save_connection' => 'Salva connessione',
        'test_connection' => 'Testa connessione',
        'remove_connection' => 'Rimuovi connessione',
        'break_glass_add' => 'Imposta come account break-glass',
        'break_glass_remove' => 'Rimuovi',
        'domain_add' => 'Aggiungi dominio',
        'domain_remove' => 'Rimuovi',
    ],

    'col' => [
        'status' => 'Stato',
        'last_used' => 'Ultimo utilizzo',
        'group' => 'Gruppo',
        'members' => 'Membri',
        'team' => 'Team',
    ],

    'status' => [
        'active' => 'Attivo',
        'inactive' => 'Inattivo',
        'revoked' => 'Revocato',
        'enforced' => 'SSO obbligatorio',
    ],

    'flash' => [
        'token_issued' => 'Token SCIM emesso.',
        'token_revoked' => 'Token SCIM revocato.',
        'group_mapped' => 'Associazione team salvata.',
        'connection_saved' => 'Connessione :protocol salvata.',
        'connection_ok' => 'Connessione :protocol verificata con successo.',
        'connection_removed' => 'Connessione rimossa.',
        'break_glass_added' => 'Account break-glass impostato.',
        'break_glass_removed' => 'Stato break-glass rimosso.',
        'domain_added' => 'Dominio e-mail aggiunto.',
        'domain_removed' => 'Dominio e-mail rimosso.',
    ],

    'error' => [
        'discovery_failed' => 'La discovery OIDC del provider di identità non è raggiungibile o è incompleta.',
        'issuer_mismatch' => 'L’issuer della risposta di discovery non corrisponde alla configurazione.',
        'token_exchange_failed' => 'Lo scambio del codice con il provider di identità è fallito.',
        'token_invalid' => 'Il token di accesso del provider di identità non è valido.',
        'token_expired' => 'Il token di accesso del provider di identità è scaduto.',
        'jwks_failed' => 'Impossibile caricare le chiavi di firma del provider di identità.',
        'no_account' => 'Nessun account WorkDiary è collegato a questa identità. Contattare l’amministrazione.',
        'org_without_sso' => 'Per questo identificativo non è configurato alcun single sign-on.',
        'email_without_sso' => 'Nessun single sign-on configurato per questo dominio e-mail.',
        'tenant_required' => 'Microsoft 365 richiede la directory (tenant).',
        'google_issuer_invalid' => 'Per Google Workspace è consentito solo l’issuer ufficiale https://accounts.google.com.',
        'domain_invalid' => 'Inserire un dominio e-mail valido.',
        'domain_taken' => 'Questo dominio e-mail è già associato a un’altra organizzazione.',
        'flow_expired' => 'L’accesso SSO è scaduto. Riprovare.',
        'module_disabled' => 'Il single sign-on non è disponibile per questa organizzazione.',
        'url_not_public' => 'L’URL non è raggiungibile pubblicamente. Per provider interni attivare «Consenti IdP su rete privata».',
        'entra_issuer_not_tenant_specific' => 'Microsoft Entra ID richiede l’issuer specifico del tenant (https://login.microsoftonline.com/<GUID-del-tenant>/v2.0) — mai common/organizations.',
        'entra_email_link_forbidden' => 'Il collegamento iniziale via e-mail è bloccato per Microsoft Entra ID: il claim email non è verificato (attacco nOAuth). Preparare le identità in anticipo (SCIM/manuale) oppure usare il JIT.',
        'saml_invalid' => 'La risposta SAML non è valida.',
        'saml_unsolicited' => 'Le risposte SAML non richieste (avviate dall’IdP) vengono rifiutate. Avviare l’accesso da WorkDiary.',
        'saml_no_nameid' => 'La risposta SAML non contiene una NameID. Configurare una regola claim NameID presso l’IdP (ad es. ADFS).',
        'saml_settings_invalid' => 'La configurazione SAML è incompleta o non valida.',
        'saml_certificate_invalid' => 'Impossibile leggere il certificato dell’IdP (atteso PEM).',
    ],
];
