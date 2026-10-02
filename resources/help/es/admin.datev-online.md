---
title: "Conectar DATEV Online"
topic: admin.datev-online
version: 1
audience:
    - admin
related:
    - admin.integrations
    - admin.plugins
---

La integración transfiere los lotes contables cerrados y las imágenes de
justificantes directamente a DATEV Unternehmen online, sin descargar ni
subir archivos a mano.

**Requisitos:** Un registro de aplicación en DATEV (ID y secreto de
cliente del portal de desarrolladores de DATEV) y un usuario de DATEV con
acceso al mandante. Introduzca las credenciales en la configuración del
plugin y registre en la aplicación de DATEV la dirección de redirección
que aparece allí. Mientras DATEV no haya autorizado el uso productivo,
mantenga activado «Usar sandbox».

**Iniciar sesión y elegir el mandante:** «Iniciar sesión con DATEV» lleva
al inicio de sesión de DATEV y de vuelta. Después elija el mandante
(número de asesor-número de mandante) de la lista de mandantes habilitados
para usted.

**Lotes contables:** Los lotes cerrados de la exportación DATEV se pueden
entregar como importación EXTF con «Transferir a DATEV». Los números de
asesor y de mandante del lote deben coincidir con el mandante conectado.
DATEV procesa la importación en segundo plano; la ejecución nocturna
consulta el resultado, «Consultar estado de importación» lo hace de
inmediato. Una importación fallida puede volver a transferirse tras la
corrección.

**Imágenes de justificantes:** Si está activado, la ejecución nocturna
transfiere las facturas emitidas como «Rechnungsausgang» y las recibidas
como «Rechnungseingang», cada justificante exactamente una vez y solo a
partir de la fecha configurada (por defecto, el día del inicio de sesión).
«Transferir ahora» inicia la ejecución de inmediato.

**Desconectar:** La conexión puede desconectarse en cualquier momento; los
datos ya transferidos permanecen en DATEV.
