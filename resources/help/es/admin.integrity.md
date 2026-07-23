---
title: "Integridad del código fuente"
topic: admin.integrity
version: 1
audience:
    - admin
related:
    - admin.security
---

La **supervisión de integridad del código fuente** (funcionalidad 095)
detecta manipulaciones de los archivos de la instalación: cada archivo fuente
se comprueba con SHA-256 contra una **línea base**, `vendor/` con una suma de
verificación por paquete. El hash raíz de la línea base forma parte del
manifiesto de release firmado — una línea base manipulada falla la
verificación de firma.

- **Panel de estado**: resultado de la última verificación, origen de la
  línea base (release = firmable, local = detección de deriva desde el
  congelado) y hash raíz.
- **Verificar ahora** encola una verificación; el resultado aparece en la
  lista de hallazgos y en la cadena hash `audit_logs`.
- **Congelar línea base** crea una nueva línea base local — necesario tras
  cambios legítimos (hotfix, `composer dump-autoload`); de lo contrario cada
  ejecución informará una desviación permanente.
- **Alertas**: los administradores de plataforma reciben notificación ante
  hallazgos nuevos o modificados; el fin de alerta llega tras la siguiente
  ejecución limpia.
- **Límites**: la verificación detecta, no impide. Un atacante con control
  total del servidor también puede atacar el verificador — siguen
  recomendados la supervisión externa (`integrity:verify --json`, código de
  salida) y el endurecimiento del SO (montajes de solo lectura, AIDE).
  `.env` y `storage/` quedan deliberadamente fuera de la línea base.

La ejecución diaria se desactiva con `INTEGRITY_CHECK_ENABLED` y se
replanifica en la página del planificador.
