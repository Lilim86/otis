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

    'tipos_almacen' => ['A' => 'Almacén administrativo', 'H' => 'Almacén de herramientas', 'R' => 'Almacén de repuestos', 'M' => 'Almacén de materiales'],

    'estados_cuota' => ['PENDIENTE' => 'Pendiente de facturar', 'FACTURADA' => 'Facturada', 'COBRADA' => 'Cobrada'],

    'tipos_ingreso' => ['RECIBO_OFICIAL' => 'Recibo oficial de ingreso', 'TRASPASO_EXTRACTO' => 'Traspaso de ingreso (cobro por extracto)'],

    'tipos_fondo' => ['CARGO_CUENTA' => 'Cargo de cuenta', 'FONDO_VIAJE' => 'Fondo para viajes', 'CAJA_CHICA' => 'Caja chica'],

    // Agenda de Secretaría
    'gerente_general' => env('GERENTE_GENERAL', 'Ing. Mario Paredes'),
    'tipos_cita' => ['CITA' => 'Cita', 'CONSULTA' => 'Consulta', 'REUNION' => 'Reunión', 'VISITA' => 'Visita a obra'],
    'situaciones_cita' => ['PROGRAMADA' => 'Programada', 'CONFIRMADA' => 'Confirmada', 'ATENDIDA' => 'Atendida', 'CANCELADA' => 'Cancelada'],

    // Portal del cliente: cuenta a la que se transfiere (hoy es una cuenta de pruebas) y cronograma de mantenimiento.
    'pagos' => [
        'cuenta_destino' => env('PAGOS_CUENTA_DESTINO'),
        'banco' => env('PAGOS_BANCO', 'Banco por definir'),
        'titular' => env('PAGOS_TITULAR', 'CGI Bolivia (cuenta de pruebas)'),
        'moneda' => 'BOB',
    ],
    'mantenimiento' => [
        'intervalo_dias_habiles' => 30,
        // Feriados nacionales de fecha fija (mes-día). Los móviles (Carnaval, Viernes Santo, Corpus Christi) no están.
        'feriados' => ['01-01', '01-22', '05-01', '06-21', '08-06', '11-02', '12-25'],
    ],

    'tipos_visita' => ['PREVENTIVO' => 'Preventivo', 'CORRECTIVO' => 'Correctivo'],
    'situaciones_visita' => ['PROGRAMADA' => 'Programada', 'SOLICITADA' => 'Solicitada por el cliente', 'REALIZADA' => 'Realizada', 'REPROGRAMADA' => 'Reprogramada', 'CANCELADA' => 'Cancelada'],

    // Quien emite las facturas y recibos (los datos fiscales reales se cargan en el .env).
    'emisor' => [
        'razon_social' => env('EMPRESA_RAZON_SOCIAL', 'Compañía Comercial General Industrial Ltda.'),
        'nombre_comercial' => env('EMPRESA_NOMBRE', 'CGI BOLIVIA'),
        'nit' => env('EMPRESA_NIT', '0000000000'),
        'direccion' => env('EMPRESA_DIRECCION', 'La Paz, Bolivia'),
        'telefono' => env('EMPRESA_TELEFONO', '800-16-1002'),
        'correo' => env('EMPRESA_CORREO'),
        'actividad' => 'Venta, instalación y mantenimiento de ascensores',
    ],

    'monedas' => ['BOB' => 'Bolivianos', 'USD' => 'Dólares'],

    // Precios de venta a partir del precio de compra FOB. Los factores se deducen de un solo
    // ejemplo del SOFIA (FOB 10 -> base 18.29 -> cliente 21.39). Hay que confirmarlos con la empresa.
    'factor_venta_base' => 1.829,
    'factor_venta_cliente' => 2.139,
    'tipo_cambio_usd' => 6.96,
];
