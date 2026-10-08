---
title: "Seguridad y endurecimiento"
topic: admin.security
version: 2
keywords:
    - 2FA
    - doble factor
    - passkey
    - cifrado
    - bloqueo de IP
    - fuerza bruta
    - SIEM
    - cuenta hackeada
    - robo de cuenta
    - SBOM
    - resumen de seguridad
    - detección de intrusiones
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
**WebAuthn**); recomiende al menos dos. El comando
`php artisan security:encrypt-existing` cifra campos sensibles
existentes de forma idempotente — el cifrado depende del **APP_KEY**,
así que haga copia de seguridad y guarde la clave por separado.
`php artisan audit:verify` valida las cadenas de hash de los registros
de auditoría y `php artisan system:health` comprueba el estado del
sistema; la vista de componentes genera además una **SBOM**
(CycloneDX 1.5) para auditorías.

## Bloqueo temporal de IP y exportación SIEM

Sin fail2ban en el servidor, WorkDiary puede bloquear por sí mismo
direcciones de forma temporal tras intentos fallidos repetidos (variable de
entorno `SECURITY_IP_BAN`, desactivada por defecto): 15 minutos, una hora
si se repite y después 24 horas. Las sesiones iniciadas y las redes
privadas nunca se ven afectadas; los bloqueos activos se ven y se levantan
en «Detección de ataques». Como muchos usuarios pueden compartir una
dirección (redes móviles, redes de empresa), fail2ban sigue siendo la
primera opción. Para un SIEM, WorkDiary escribe además cada evento de
seguridad en formato CEF o JSON en un archivo propio o por syslog
(`SECURITY_SIEM_FORMAT`, `SECURITY_SIEM_TARGET`).

## Proteger una cuenta tras una usurpación

Si se confirma que alguien ha usurpado una cuenta, cerrar la sesión no basta:
quien conoce la contraseña vuelve a iniciar sesión. «Proteger la cuenta» en la
gestión de sesiones (para miembros de su organización) y «Confirmar la
usurpación de la cuenta» en un evento de seguridad (administración de la
plataforma) terminan todas las sesiones y tokens de API, invalidan la
contraseña y todas las llaves de acceso y envían a la persona un enlace para
establecer una nueva contraseña. Los métodos de dos factores basados en
aplicación se conservan. La acción aparece como evento de seguridad y en el
registro de auditoría. Su propia cuenta la protege en su página de
autenticación de dos factores.
