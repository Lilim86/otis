# SOFIA (original) vs LILIM (este sistema)

Equivalencias entre el menú del SOFIA y la API de LILIM. Base: capturas de pantalla del SOFIA v1.1.3147.
`*` = lleva el flujo de aprobación REG → APR / ANL (`POST /{id}/aprobar`, `POST /{id}/anular`).

| Menú SOFIA | Pantalla | Recurso en la API |
|---|---|---|
| Planificación | Países, departamentos, provincias, municipios, zonas, vías de acceso | `paises`, `departamentos`, `provincias`, `municipios`, `zonas`, `vias-acceso` |
| Barra | Personas | `personas` * |
| Barra | Empresas | `empresas` * |
| Barra | Edificios | `edificios` * |
| Barra | Equipos (ascensores, escaleras) | `equipos` * (+ `POST equipos/calcular-precios`) |
| Barra | Bienes y Servicios | `insumos` *, clasificador en `grupos-bienes` |
| Barra | Alertas | `GET alertas?tipo=contratos\|repuestos\|reparaciones` |
| Barra | Rep. Seguimiento | `GET reportes/seguimiento?tipo=...` |
| Barra | Contratos / Ventas | `contratos` *, `cuotas-contrato` |
| Barra | Rep. Cobranzas | `GET reportes/cobranzas?agrupar_por=...`, `cobranzas` * |
| Barra | Rep. Tesorería | `GET tesoreria/conciliacion` |
| Barra | Compras | `compras` *, `facturas-proveedor` *, `ordenes-cancelacion` * |
| Barra | Almacenes | `almacenes`, `movimientos-almacen`, `GET almacenes/{id}/inventario`, `GET reportes/inventario` |
| RRHH | Planillas y pagos personal | `planillas` * |
| RRHH | Clasificadores | `cargos`, `profesiones` |
| Financiero | Cobranzas (registro y seguimiento) | `cobranzas` *, `GET reportes/seguimiento` |
| Financiero | Tesorería | `ingresos-tesoreria` *, `traspasos-cuentas` *, `ordenes-cancelacion` *, `GET tesoreria/conciliacion` |
| Financiero | Fondos a rendir (cargos de cuenta, viajes, caja chica) | `fondos-rendir` *, `rendiciones-fondo` * |
| Administrativo | Compras administrativas / órdenes de cancelación | `compras`, `ordenes-cancelacion` |
| Administrativo | Contratos de técnicos | `contratos-tecnicos` * |
| Administrativo | Salida / inventario de almacén | `movimientos-almacen`, `almacenes/{id}/inventario` |
| Seguridad | Administración de usuarios | `usuarios` |
| Seguridad | Control de accesos | `GET seguridad/accesos` |
| Seguridad | Control de privilegios de operación | `seguridad/roles`, `seguridad/permisos`, `PUT seguridad/usuarios/{id}/roles` |
| Barra | Cambiar contraseña | (pendiente) |

## Lo que NO se pudo replicar y por qué

- **Contabilidad:** aparece deshabilitada en el menú del SOFIA.
- **Facturación SOFIA nuevo / facturas antiguas / orden de cobro:** no hay capturas de esas pantallas; solo se ve el título del menú.
- **Procesos de RRHH, solicitud y orden de cancelación de personal, reportes de RRHH:** solo se ve el título del menú.
- **Alerta de "mantenimientos gratuitos (actas de entrega definitiva)":** no hay datos de actas ni de meses de garantía.
- **Factores de precios (`config/sofia.php`):** se dedujeron de un solo ejemplo (FOB 10 → 18.29 → 21.39). Confirmar con la empresa.
- **Cálculo de mora por "tipo de mora" del SOFIA:** se simplificó a facturas con saldo pendiente.

## Reportes combinados de tablas

Pantalla `Reportes` (`/reportes`) y API `GET /api/reportes/combinados[/{clave}]` (`?formato=json|csv|pdf`, filtros `desde`, `hasta`, `departamento_id`, `q`).
Cada reporte une su módulo con las tablas relacionadas y con la ubicación completa (país → vía de acceso, deducida desde el nivel más bajo que tenga el registro).
Lo ve quien tenga `modulo.ver` de alguno de los módulos del reporte, así cada rol ve solo los suyos.
Claves: ubicaciones, empresas, edificios, contratos, personas, equipos, mantenimientos, ordenes_mantenimiento, emergencias, kardex, inventario_almacen, movimientos, compras, cobranzas, cuotas_pendientes, cuentas_bancarias, personal, agenda.

## Portal del cliente, comprobantes y avisos

- **Portal del cliente** (`/portal` en Vue, `/api/portal/*`): inicio, mantenimiento (historial, reportar falla, calificar, firmar conformidad, informe PDF), cronograma (cada 30 días hábiles), pagos por transferencia (con foto o PDF del comprobante) y contratos y equipos.
- **Pagos de clientes** (`/api/pagos-clientes`): Financiero confirma (nace la cobranza, avanza la cuota, se avisa al cliente) o rechaza con motivo.
- **Facturas y recibos** (`/api/comprobantes/...`): modelo de diseño, PDF por cobranza y envío por correo con bitácora (`envios_comprobante`). La factura es un **modelo sin validez tributaria**.
- **Ruta del técnico** (`/api/tecnico/ruta`): sus visitas, y cerrar cada una con su informe.
- **Avisos automáticos**: `php artisan avisos:clientes` (programado a las 08:00; requiere `php artisan schedule:work` o el cron de Laravel).
