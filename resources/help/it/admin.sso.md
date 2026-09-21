---
title: "SSO e servizi di directory"
topic: admin.sso
version: 1
audience:
    - admin
modules:
    - module.sso
related:
    - admin.integrations
---

In questa pagina gestisce il collegamento al provider di identità
della sua organizzazione: provisioning SCIM, single sign-on OIDC e
SAML 2.0. Il modulo fa parte del piano Enterprise.

**Provisioning SCIM:** il suo provider di identità (Entra ID,
Keycloak, Okta …) crea, aggiorna e disattiva gli account tramite
l'endpoint SCIM. A tal fine emette un token bearer — il testo in
chiaro viene mostrato una sola volta. Un account disattivato nella
directory perde immediatamente accesso, sessioni e token API. SCIM non
assegna mai ruoli; può associare consapevolmente i gruppi SCIM a un
team.

**Single sign-on OIDC:** memorizzi issuer, client ID e client secret
dalla registrazione dell'app del suo provider di identità e registri
lì l'URL di callback mostrato. I dipendenti avviano l'accesso tramite
l'URL di avvio SSO o il link «Accedi con il single sign-on» nella
pagina di accesso (che richiede l'identificativo dell'organizzazione).

**SAML 2.0:** memorizzi entity ID, URL SSO e certificato di firma del
provider di identità; fornisca all'IdP l'URL dei metadata SP. Le
risposte devono contenere assertion firmate; gli accessi avviati
dall'IdP (non richiesti) vengono rifiutati. Per la rotazione dei
certificati può memorizzare un certificato successore in parallelo.

**Collegamento account:** un'identità IdP viene collegata a un account
WorkDiary esclusivamente tramite issuer + subject. L'SSO non crea mai
account e non assegna mai ruoli — gli account arrivano tramite SCIM o
vengono creati manualmente. In opzione, il collegamento iniziale via
e-mail può connettere un account esistente tramite il suo indirizzo
e-mail al primo accesso (solo con esattamente una corrispondenza).

**SSO obbligatorio e break-glass:** l'SSO obbligatorio blocca lato
server l'accesso con password per tutti gli account
dell'organizzazione. Definisca prima almeno un account break-glass: un
account di emergenza non federato che può continuare ad accedere
localmente — altrimenti un guasto del provider di identità blocca
l'intera organizzazione. Ogni accesso break-glass viene registrato
nell'audit.

**Più fattori:** dopo un accesso SSO, la verifica a più fattori è
responsabilità del provider di identità. L'accesso locale, inclusi i
metodi a due fattori, resta invariato per gli account senza SSO.
