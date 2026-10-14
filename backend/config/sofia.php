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

    'monedas' => ['BOB' => 'Bolivianos', 'USD' => 'Dólares'],

    // Precios de venta a partir del precio de compra FOB. Los factores se deducen de un solo
    // ejemplo del SOFIA (FOB 10 -> base 18.29 -> cliente 21.39). Hay que confirmarlos con la empresa.
    'factor_venta_base' => 1.829,
    'factor_venta_cliente' => 2.139,
    'tipo_cambio_usd' => 6.96,
];
