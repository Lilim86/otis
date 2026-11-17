<?php

/**
 * Catálogos del negocio, tomados del sistema SOFIA original.
 * Son listas cerradas: se validan en la API y se publican en GET /api/catalogos.
 */
return [
    // Control de privilegios por operación. En false (por defecto) cualquier usuario autenticado puede operar;
    // en true cada operación exige el permiso "modulo.operacion" (ver, crear, editar, eliminar, restaurar, aprobar, anular).
    'permisos_activos' => env('PERMISOS_ACTIVOS', false),

    // Flujo de aprobación de los registros (igual que SOFIA): REG -> APR, o ANL (anulado).
    'estados_registro' => ['REG' => 'Registrado', 'APR' => 'Aprobado', 'ANL' => 'Anulado'],

    'tipos_documento' => ['CI' => 'Cédula de identidad', 'NIT' => 'NIT', 'PAS' => 'Pasaporte', 'OTRO' => 'Otro'],

    'expedido_en' => [
        'LPZ' => 'La Paz', 'CBB' => 'Cochabamba', 'SCZ' => 'Santa Cruz', 'ORU' => 'Oruro', 'PTS' => 'Potosí',
        'TJA' => 'Tarija', 'CHQ' => 'Chuquisaca', 'BEN' => 'Beni', 'PND' => 'Pando',
    ],

    'tipos_persona' => [
        'CLIENTE_INDIVIDUAL' => 'Cliente (Persona individual)',
        'PROVEEDOR_INDIVIDUAL' => 'Proveedor (Persona individual)',
        'PERSONAL' => 'Personal',
        'BENEFICIARIO' => 'Beneficiario',
        'COBRADOR' => 'Cobrador',
        'TECNICO' => 'Técnico',
    ],

    'tipos_empresa' => [
        'EMPRESA_CLIENTE' => 'Empresa - Cliente',
        'EMPRESA_PROVEEDOR' => 'Empresa - Proveedor',
        'INSTITUCION' => 'Institución',
    ],

    'tipos_edificacion' => [
        'EDIFICIO_DEPARTAMENTOS' => 'Edificio de departamentos',
        'EDIFICIO_MIXTO' => 'Edificio mixto (comercial, departamentos y/o oficinas)',
        'CASA_PARTICULAR' => 'Casa particular',
        'OFICINAS' => 'Oficinas',
        'HOTEL' => 'Hotel',
    ],

    'unidades_medida' => ['EQP' => 'Equipo', 'SER' => 'Servicio', 'UND' => 'Unidad', 'KG' => 'Kilogramo', 'LT' => 'Litro', 'MT' => 'Metro', 'GLB' => 'Global'],

    'rotacion' => ['ALTO' => 'Alto', 'PROMEDIO' => 'Promedio', 'BAJO' => 'Bajo', 'NO_APROBADO' => 'No aprobado'],

    'tipos_insumo' => ['HERRAMIENTA' => 'Herramienta', 'REPUESTO' => 'Repuesto', 'MATERIAL' => 'Material', 'SERVICIO' => 'Servicio'],

    // Motivo de cada movimiento de almacén: de aquí sale quién tiene las herramientas y qué se gastó en cada visita.
    'motivos_movimiento' => ['COMPRA' => 'Compra', 'INICIAL' => 'Stock inicial', 'CONSUMO' => 'Consumo en una visita', 'PRESTAMO' => 'Préstamo de herramienta', 'DEVOLUCION' => 'Devolución de herramienta', 'AJUSTE' => 'Ajuste de inventario'],

    'tipos_almacen' => ['A' => 'Almacén administrativo', 'H' => 'Almacén de herramientas', 'R' => 'Almacén de repuestos', 'M' => 'Almacén de materiales'],

    'estados_cuota' => ['PENDIENTE' => 'Pendiente de facturar', 'FACTURADA' => 'Facturada', 'COBRADA' => 'Cobrada'],

    // Portal del cobrador: qué cobra, cómo le pagan y en qué va su rendición ante Tesorería.
    'tipos_cobro' => ['MANTENIMIENTO' => 'Mantenimiento', 'CONTRATO' => 'Contrato y renovación'],
    'metodos_pago' => ['EFECTIVO' => 'Efectivo', 'CHEQUE' => 'Cheque', 'TRANSFERENCIA' => 'Transferencia'],
    'situaciones_tesoreria' => ['PENDIENTE' => 'Por enviar', 'ENVIADO' => 'En Tesorería', 'OBSERVADO' => 'Observado', 'APROBADO' => 'Aprobado'],

    'tipos_ingreso' => ['RECIBO_OFICIAL' => 'Recibo oficial de ingreso', 'TRASPASO_EXTRACTO' => 'Traspaso de ingreso (cobro por extracto)'],

    'tipos_fondo' => ['CARGO_CUENTA' => 'Cargo de cuenta', 'FONDO_VIAJE' => 'Fondo para viajes', 'CAJA_CHICA' => 'Caja chica'],

    // Agenda de Secretaría
    'gerente_general' => env('GERENTE_GENERAL', 'Ing. Mario Paredes'),
    // Correo que recibe los pedidos del catálogo. Sin él, el aviso queda solo en el sistema.
    'gerente_correo' => env('GERENTE_CORREO'),
    'url_sistema' => env('FRONTEND_URL_PORTAL', 'http://127.0.0.1:5173'),
    'zona_horaria' => 'America/La_Paz',
    'situaciones_pedido' => ['PENDIENTE' => 'Pendiente', 'ATENDIDO' => 'Atendido', 'CANCELADO' => 'Cancelado'],
    'tipos_cita' => ['CITA' => 'Cita', 'CONSULTA' => 'Consulta', 'REUNION' => 'Reunión', 'VISITA' => 'Visita a obra', 'PEDIDO' => 'Pedido del catálogo'],
    'situaciones_cita' => ['PROGRAMADA' => 'Programada', 'CONFIRMADA' => 'Confirmada', 'ATENDIDA' => 'Atendida', 'CANCELADA' => 'Cancelada'],

    // Portal del cliente: cuenta a la que se transfiere (hoy es una cuenta de pruebas) y cronograma de mantenimiento.
    'pagos' => [
        'cuenta_destino' => env('PAGOS_CUENTA_DESTINO'),
        'banco' => env('PAGOS_BANCO', 'Banco por definir'),
        'titular' => env('PAGOS_TITULAR', 'CGI Bolivia (cuenta de pruebas)'),
        'moneda' => 'BOB',
        // QR de cobro que ve el cliente en su portal. Cobranzas lo puede reemplazar desde el sistema;
        // mientras no lo haga se usa este QR de prueba (Banco Unión, monto fijo de Bs 10, uso múltiple).
        'qr' => [
            'imagen' => resource_path('pagos/qr-cobro-prueba.png'),
            'banco' => 'Banco Unión S.A.',
            'cuenta' => '10000067980637',
            'titular' => 'Matthew Flores',
            'glosa' => 'C G I LTDA',
            'monto' => 10.00,
            'vence' => '2028-11-03',
            'uso_unico' => false,
            'prueba' => true,
        ],
    ],
    'mantenimiento' => [
        'intervalo_dias_habiles' => 30,
        // Visitas que un técnico puede tener en un mismo día.
        'max_visitas_por_dia' => 4,
        // Feriados nacionales de fecha fija (mes-día). Los móviles (Carnaval, Viernes Santo, Corpus Christi) no están.
        'feriados' => ['01-01', '01-22', '05-01', '06-21', '08-06', '11-02', '12-25'],
    ],

    // Parámetros de los aportes de ingeniería (rutas, confiabilidad, prioridad de fallas y cobranza).
    'ingenieria' => [
        // Punto de partida de las rutas: oficina central (Calle Reyes Ortiz 73, La Paz).
        'base' => ['latitud' => -16.5008, 'longitud' => -68.1302],
        // La distancia en línea recta se multiplica por este factor de tortuosidad (calles de La Paz).
        'factor_ruta' => 1.35,
        'velocidad_kmh' => 18,
        'minutos_por_visita' => 75,
        'costo_bs_km' => 2.8,
        // Tiempo máximo de respuesta (horas) por prioridad de falla.
        'objetivo_horas' => ['P1' => 2, 'P2' => 8, 'P3' => 48, 'P4' => 120],
        // Una visita preventiva está "a tiempo" si se hace hasta N días después de lo programado.
        'tolerancia_dias' => 2,
        // Días de gracia para considerar puntual un pago.
        'gracia_pago_dias' => 5,
    ],

    'tipos_visita' => ['PREVENTIVO' => 'Preventivo', 'CORRECTIVO' => 'Correctivo'],
    'situaciones_visita' => ['PROGRAMADA' => 'Programada', 'SOLICITADA' => 'Solicitada por el cliente', 'EN_CURSO' => 'En curso (técnico en sitio)', 'REALIZADA' => 'Realizada', 'REPROGRAMADA' => 'Reprogramada', 'CANCELADA' => 'Cancelada'],

    // Quien emite las facturas y recibos (los datos fiscales reales se cargan en el .env).
    'emisor' => [
        'razon_social' => env('EMPRESA_RAZON_SOCIAL', 'Compañía Comercial General Industrial Limitada'),
        'nombre_comercial' => env('EMPRESA_NOMBRE', 'CGI Ltda. - OTIS'),
        'nit' => env('EMPRESA_NIT', '1018533029'),
        'direccion' => env('EMPRESA_DIRECCION', 'Calle Reyes Ortiz Nº 73, Edificio Gundlach Torre Oeste, Piso Nº 15, Zona Central'),
        'ciudad' => env('EMPRESA_CIUDAD', 'La Paz'),
        'telefono' => env('EMPRESA_TELEFONO', '2337676'),
        'correo' => env('EMPRESA_CORREO'),
        'actividad' => 'Venta, instalación y mantenimiento de ascensores',
        // Datos de la factura (formato de la representación gráfica del SIN).
        'sucursal' => 'CASA MATRIZ',
        'punto_venta' => 1,
        'codigo_servicio' => '10011',
        // Recibo oficial de cobranza impreso (talonario R-08-01).
        'recibo' => [
            'codigo' => 'R-08-01',
            'version' => '1',
            'direccion' => 'C. Reyes Ortiz No. 73 esq. Federico Suazo. Edificio Torres Gundlach Torre Oeste, piso 15',
            'telefono' => '2-2337696',
        ],
    ],

    'monedas' => ['BOB' => 'Bolivianos', 'USD' => 'Dólares'],

    // Precios de venta a partir del precio de compra FOB. Los factores se deducen de un solo
    // ejemplo del SOFIA (FOB 10 -> base 18.29 -> cliente 21.39). Hay que confirmarlos con la empresa.
    'factor_venta_base' => 1.829,
    'factor_venta_cliente' => 2.139,
    'tipo_cambio_usd' => 6.96,
];
