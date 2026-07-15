---
title: "Destinos de copia de seguridad en la nube"
topic: backup-targets.overview
version: 1
audience: []
related:
    - admin.integrations
---

WorkDiary respalda toda la instalación cifrada en Dropbox, OneDrive o Google Drive (la copia externa de la estrategia 3-2-1). El texto en claro nunca sale de la instalación — solo se suben partes cifradas con un manifiesto de confirmación firmado.

**Conexiones:** Solo el operador de la plataforma gestiona los destinos de copia; cada proveedor recibe su propia cuenta OAuth (separada de la entrada de documentos, con permisos de escritura dedicados). Si falta un permiso necesario, el destino queda visiblemente bloqueado.

**Claves:** BACKUP_MASTER_KEY (ENV, ¡guardar sin conexión!) es la única vía regular de descifrado; un par de claves de recuperación opcional descifra en caso de emergencia. Sin clave de recuperación la página advierte permanentemente — perder la clave maestra inutiliza todas las copias.

**Operación:** La ejecución nocturna crea una instantánea (volcado de BD + archivos), la cifra, sube las partes de forma reanudable y aplica la retención (7 diarias / 4 semanales / 12 mensuales; la retención legal protege generaciones individuales). Una verificación semanal por muestreo comprueba firma y hashes; la prueba de restauración restaura en un directorio aislado y registra RPO/RTO — hasta la primera prueba verde una generación cuenta como «guardada, restauración no confirmada».
