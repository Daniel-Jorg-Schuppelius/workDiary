---
title: "Datos de demostración"
topic: admin.demo-data
version: 2
audience:
    - admin
related:
    - admin.tenants
    - admin.handbook
    - admin.data-transfer
---

Los datos de demostración sirven para llenar una organización con datos
de ejemplo para pruebas, formación y presentaciones, según un **sector de
muestra** elegido: cada perfil sectorial tiene exactamente uno, con sus
propios clientes, proyectos, un encargo principal completo, material,
activo, protocolo firmado y ejecución de procedimiento. **Crear
organización de demostración** (administrador de plataforma) genera una
organización nueva y aislada; **Generar (seed)** llena la organización
actual, todavía vacía; **Restablecer (reset)** borra y vuelve a crear los
datos de un inquilino de demostración conservando sector y alcance. Sin
marcar «Mostrar el alcance completo», la demostración sigue la
recomendación de módulos del perfil y solo crea datos para los módulos
activos. El diálogo indica de antemano con qué licencia funcionará la
demostración: si la instancia puede emitir licencias, la organización
recibe una licencia temporal; de lo contrario se aplica la licencia de la
instalación, y sin ambas la demostración funciona en el plan Free con la
mayoría de los módulos bloqueados. El restablecimiento solo se permite en
inquilinos marcados como demo (`is_demo`) y allí sobrescribe los datos
existentes; con un plazo de conservación configurado, el planificador
elimina definitivamente las organizaciones de demostración caducadas.
Todas las acciones requieren permisos propios y se registran en el log
de auditoría.
