---
title: "Sicurezza e hardening"
topic: admin.security
version: 2
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
fattori in parallelo (TOTP, codice e-mail, WebAuthn) — consigli
almeno due metodi. Comandi chiave: `php artisan
security:encrypt-existing` cifra i campi sensibili esistenti (dipende
dall'APP_KEY: prima faccia un backup e metta al sicuro la chiave),
`php artisan audit:verify` valida le catene hash dell'audit e
`php artisan system:health` controlla lo stato del sistema. La
panoramica componenti genera inoltre una **SBOM** (CycloneDX 1.5) per
gli audit, accessibile solo agli admin globali.

## Blocco IP temporaneo ed esportazione SIEM

Senza fail2ban sul server, WorkDiary può bloccare autonomamente e
temporaneamente gli indirizzi dopo ripetuti tentativi falliti (variabile
d'ambiente `SECURITY_IP_BAN`, disattivata per impostazione predefinita):
15 minuti, un'ora in caso di ripetizione, poi 24 ore. Le sessioni con
accesso e le reti private non sono mai interessate; i blocchi attivi si
vedono e si revocano in «Rilevamento attacchi». Poiché molti utenti possono
condividere un indirizzo (reti mobili, reti aziendali), fail2ban resta la
prima scelta. Per un SIEM, WorkDiary scrive inoltre ogni evento di sicurezza
in formato CEF o JSON in un file dedicato o tramite syslog
(`SECURITY_SIEM_FORMAT`, `SECURITY_SIEM_TARGET`).
