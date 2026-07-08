---
title: "Sicurezza e hardening"
topic: admin.security
version: 1
audience:
    - admin
related:
    - admin.handbook
    - admin.backups
    - isms.software
---

La pagina **Sicurezza** riunisce in sola lettura lo stato rilevante:
sessioni attive, token API (solo metadati), integrazioni esterne,
ultimi export, accessi del supporto, copertura 2FA e cifratura at
rest. Gli utenti possono registrare più metodi di autenticazione a due
fattori in parallelo (TOTP, codice e-mail, WebAuthn) — consiglia
almeno due metodi. Comandi chiave: `php artisan
security:encrypt-existing` cifra i campi sensibili esistenti (dipende
dall'APP_KEY: prima fai backup e metti al sicuro la chiave),
`php artisan audit:verify` valida le catene hash dell'audit e
`php artisan system:health` controlla lo stato del sistema. La
panoramica componenti genera inoltre una **SBOM** (CycloneDX 1.5) per
gli audit, accessibile solo agli admin globali.
