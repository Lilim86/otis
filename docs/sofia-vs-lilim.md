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

## Sincronía entre módulos

- **Requisitos previos:** un registro solo se puede apoyar en otro que esté vigente (no en la papelera, no dado de baja y no anulado). Aplica a todos los selectores de la API (`CrudController::existe`).
- **Comercial:** un contrato exige que el edificio sea del mismo cliente; una visita debe caer dentro de la vigencia del contrato.
- **Almacenes:** el stock solo cambia por movimientos. El stock inicial de un bien se registra como un ingreso (motivo `INICIAL`) en el almacén elegido. Cada movimiento puede llevar motivo (compra, consumo, préstamo, devolución, ajuste), responsable y visita. Un bien con tipo de almacén solo se mueve en un almacén de ese tipo.
- **Herramientas:** préstamo = salida con motivo `PRESTAMO` y responsable; devolución = ingreso con motivo `DEVOLUCION` (no puede devolver más de lo prestado). `GET /api/movimientos-almacen/herramientas-en-poder` y la pantalla *Herramientas en poder* muestran quién tiene qué y desde cuándo.
- **Técnico ↔ almacén:** al cerrar una visita (`POST /api/tecnico/visitas/{id}/completar`) puede anotar `materiales`; salen del almacén de forma automática (motivo `CONSUMO`, ligados a la visita) y, si no alcanza el saldo, no se cierra nada.
- **Cronograma ↔ técnicos:** `POST /api/tecnico/visitas/{id}/iniciar` deja la visita `EN_CURSO` (el técnico está en sitio). `GET /api/visitas-mantenimiento/tablero` entrega la carga y el estado de cada técnico; `POST /api/visitas-mantenimiento/autoasignar` reparte las visitas sin técnico. Máximo de visitas por técnico y día: `sofia.mantenimiento.max_visitas_por_dia`.
- **Panel del administrador:** clientes (contratos, equipos, por cobrar, próxima visita), técnicos en campo, stock bajo y herramientas fuera del almacén; cada bloque aparece solo si el rol puede ver ese módulo.

## Aportes de ingeniería

Pantallas: **Técnico → Ingeniería: rutas y confiabilidad** (`/ingenieria`) y **Financiero → Análisis de cobranza** (`/cobranza-analisis`).
Datos de demostración: `php artisan db:seed --class=IngenieriaDemoSeeder` (14 edificios con coordenadas de La Paz, 6 clientes, 3 técnicos; inventados).
Parámetros en `config/sofia.php → ingenieria`.

| Aporte | Método | Cómo se mide el impacto |
|---|---|---|
| Rutas óptimas del día (`RutasService`) | TSP: vecino más cercano + 2-opt; distancia Haversine × factor de calles 1.35 | km y horas: orden de carga vs. ruta óptima; ahorro acumulado en `optimizaciones_ruta` |
| Asignación por cercanía | Costo de inserción en la ruta + penalización por carga | km de 14 días: "por carga" (método anterior) vs. "por cercanía" (simulación) |
| Confiabilidad (`ConfiabilidadService`) | λ = fallas/días, MTBF, MTTR, disponibilidad, P(falla) = 1 − e^(−λt) | Riesgo por edificio; "Adelantar" la preventiva de los de riesgo alto |
| Nivel de servicio | Cumplimiento preventivo (tolerancia 2 días), respuesta por prioridad vs. objetivo | Respuesta a fallas antes (sin clasificar) vs. después (P1..P4) |
| Prioridad de fallas (`PrioridadFallasService`) | Puntaje por palabras clave → P1 (2 h), P2 (8 h), P3 (48 h), P4 (120 h); P1/P2 al técnico más cercano | Se aplica sola al reportar en el portal |
| Riesgo de mora y flujo de caja (`CobranzaAnaliticaService`) | Laplace p = (puntuales+1)/(n+2), puntaje 0-100 (A-D), proyección a 13 semanas | Backtest a 90 días: error del modelo vs. el método tradicional |

## Reportes, QR de cobro y comprobantes

- Reportes combinados: botones **Descargar Excel** (`.xlsx` real, con totales y filtros), **PDF** y CSV (`?formato=xlsx|pdf|csv`).
- **QR de cobro**: Cobranzas lo ve y lo reemplaza (botón «QR de cobro» en Seguimiento de cobranzas; `POST/DELETE /api/cobranzas/qr`); el cliente lo ve en Portal → Pagos (`GET /api/pagos-qr`). Mientras no se suba otro, se usa el QR de prueba de `resources/pagos/`.
- **Recibo oficial de cobranza** (talonario R-08-01) y **factura** con el formato de la representación gráfica del SIN. «Recibo en blanco» sale con las casillas vacías para que Cobranzas lo llene; el de una cobranza se llena con lo registrado.
