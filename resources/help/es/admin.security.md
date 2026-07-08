---
title: "Seguridad y endurecimiento"
topic: admin.security
version: 1
audience:
    - admin
related:
    - admin.handbook
    - admin.backups
    - isms.software
---

La página de administración **«Seguridad»** reúne en modo solo lectura
el estado relevante: sesiones activas, tokens de API (solo metadatos),
integraciones externas, últimos exportes, accesos de soporte, cobertura
de 2FA y cifrado en reposo. Los usuarios pueden registrar varios
métodos de doble factor en paralelo (**TOTP**, **código por correo**,
**WebAuthn**); recomienda al menos dos. El comando
`php artisan security:encrypt-existing` cifra campos sensibles
existentes de forma idempotente — el cifrado depende del **APP_KEY**,
así que haz copia de seguridad y guarda la clave por separado.
`php artisan audit:verify` valida las cadenas de hash de los registros
de auditoría y `php artisan system:health` comprueba el estado del
sistema; la vista de componentes genera además una **SBOM**
(CycloneDX 1.5) para auditorías.
