---
title: "SSO et services d'annuaire"
topic: admin.sso
version: 1
audience:
    - admin
related:
    - admin.integrations
---

Sur cette page, vous gérez la connexion au fournisseur d'identité de
votre organisation : provisionnement SCIM, authentification unique
OIDC et SAML 2.0. Le module fait partie du plan Enterprise.

**Provisionnement SCIM :** votre fournisseur d'identité (Entra ID,
Keycloak, Okta …) crée, met à jour et désactive les comptes via le
point de terminaison SCIM. Vous émettez pour cela un jeton bearer — le
texte en clair n'est affiché qu'une seule fois. Un compte désactivé
dans l'annuaire perd immédiatement la connexion, les sessions et les
jetons API. SCIM n'attribue jamais de rôles ; vous pouvez associer
délibérément des groupes SCIM à une équipe.

**Authentification unique OIDC :** enregistrez l'issuer, l'ID client
et le secret client issus de l'enregistrement d'application de votre
fournisseur d'identité et déclarez-y l'URL de callback affichée. Les
employés démarrent la connexion via l'URL de démarrage SSO ou le lien
« Se connecter via l'authentification unique » sur la page de
connexion (qui demande l'identifiant de l'organisation).

**SAML 2.0 :** enregistrez l'entity ID, l'URL SSO et le certificat de
signature du fournisseur d'identité ; fournissez l'URL des métadonnées
SP à l'IdP. Les réponses doivent contenir des assertions signées ; les
connexions initiées par l'IdP (non sollicitées) sont rejetées. Pour la
rotation des certificats, vous pouvez enregistrer un certificat
successeur en parallèle.

**Liaison de compte :** une identité IdP est liée à un compte
WorkDiary exclusivement via issuer + subject. Le SSO ne crée jamais de
comptes et n'attribue jamais de rôles — les comptes proviennent de
SCIM ou sont créés manuellement. En option, la liaison initiale par
e-mail peut connecter un compte existant via son adresse e-mail lors
de la première connexion (uniquement en cas de correspondance unique).

**SSO obligatoire et break-glass :** le SSO obligatoire bloque côté
serveur la connexion par mot de passe pour tous les comptes de
l'organisation. Définissez d'abord au moins un compte de secours : un
compte d'urgence non fédéré qui peut continuer à se connecter
localement — sinon une panne du fournisseur d'identité bloque toute
l'organisation. Chaque connexion de secours est auditée.

**Multifacteur :** après une connexion SSO, la vérification
multifacteur relève du fournisseur d'identité. La connexion locale, y
compris les méthodes à deux facteurs, reste inchangée pour les comptes
sans SSO.
