import { bancos, cuentasBancarias, cobranzas, pagosClientes, comprobantes, comprobantePagoCliente } from '@/services'
import ModelosComprobante from '@/components/ModelosComprobante.vue'
import { bs, fecha } from './comun'

export const financiero = [
  {
    clave: 'bancos',
    titulo: 'Bancos y entidades',
    servicio: bancos,
    aprobacion: false,
    busqueda: 'Buscar por nombre o sigla',
    columnas: [
      { clave: 'nombre', etiqueta: 'Banco' },
      { clave: 'sigla', etiqueta: 'Sigla' },
    ],
    filtros: [],
    secciones: [{ titulo: 'Datos', campos: [{ clave: 'nombre', etiqueta: 'Nombre', requerido: true }, { clave: 'sigla', etiqueta: 'Sigla' }] }],
  },
  {
    clave: 'cuentas-bancarias',
    titulo: 'Cuentas bancarias',
    servicio: cuentasBancarias,
    aprobacion: false,
    busqueda: 'Buscar por número de cuenta',
    columnas: [
      { clave: 'banco', etiqueta: 'Banco', valor: (f) => f.banco?.nombre ?? '—' },
      { clave: 'numero_cuenta', etiqueta: 'Cuenta' },
      { clave: 'moneda', etiqueta: 'Moneda', catalogo: 'monedas' },
    ],
    filtros: [],
    secciones: [
      {
        titulo: 'Cuenta',
        campos: [
          { clave: 'banco_id', etiqueta: 'Banco', tipo: 'select', requerido: true, remoto: { servicio: 'bancos', texto: 'nombre' } },
          { clave: 'numero_cuenta', etiqueta: 'Número de cuenta', requerido: true },
          { clave: 'moneda', etiqueta: 'Moneda', tipo: 'select', catalogo: 'monedas', requerido: true, defecto: 'BOB' },
        ],
      },
    ],
  },
  {
    clave: 'cobranzas',
    titulo: 'Seguimiento de cobranzas',
    servicio: cobranzas,
    aprobacion: true,
    cabecera: ModelosComprobante,
    // Facturas y recibos en PDF; también se mandan al correo del cliente.
    accionesFila: [
      { etiqueta: 'Recibo', permiso: 'comprobantes.ver', visible: (f) => f.estado_registro !== 'ANL' && Number(f.cobrado_bs) > 0, ejecutar: (f) => comprobantes.ver(f.id, 'recibo') },
      { etiqueta: 'Factura', permiso: 'comprobantes.ver', visible: (f) => f.estado_registro !== 'ANL', ejecutar: (f) => comprobantes.ver(f.id, 'factura') },
      {
        etiqueta: 'Enviar por correo',
        permiso: 'comprobantes.crear',
        visible: (f) => f.estado_registro !== 'ANL',
        async ejecutar(f) {
          const tipo = window.prompt('¿Qué enviar? Escribe "recibo" o "factura"', Number(f.cobrado_bs) > 0 ? 'recibo' : 'factura')?.trim().toLowerCase()
          if (!tipo) return
          const correo = window.prompt('Correo del cliente (déjalo vacío para usar el de la empresa):', f.contrato?.empresa?.correo_contacto ?? '')
          if (correo === null) return
          return (await comprobantes.enviar(f.id, tipo, correo.trim())).message
        },
      },
    ],
    busqueda: 'Buscar por concepto, factura o recibo',
    columnas: [
      { clave: 'fecha_cobro', etiqueta: 'Fecha', valor: (f) => fecha(f.fecha_cobro) },
      { clave: 'cliente', etiqueta: 'Cliente', valor: (f) => f.contrato?.empresa?.denominacion ?? '—' },
      { clave: 'concepto', etiqueta: 'Concepto' },
      { clave: 'monto_facturado', etiqueta: 'Facturado', valor: (f) => bs(f.monto_facturado) },
      { clave: 'cobrado_bs', etiqueta: 'Cobrado', valor: (f) => bs(f.cobrado_bs) },
      { clave: 'cobrador', etiqueta: 'Cobrador', valor: (f) => (f.cobrador ? `${f.cobrador.primer_apellido ?? ''} ${f.cobrador.nombres ?? ''}`.trim() : '—') },
    ],
    filtros: [],
    secciones: [
      {
        titulo: 'Cobranza',
        campos: [
          { clave: 'contrato_id', etiqueta: 'Contrato', tipo: 'select', requerido: true, remoto: { servicio: 'contratos', texto: 'codigo_contrato' } },
          { clave: 'cuota_id', etiqueta: 'Cuota', tipo: 'select', remoto: { servicio: 'cuotasContrato', texto: 'nro_cuota', depende: 'contrato_id' } },
          { clave: 'concepto', etiqueta: 'Concepto', requerido: true },
          { clave: 'monto_facturado', etiqueta: 'Monto facturado (Bs)', tipo: 'numero', requerido: true },
          { clave: 'cobrado_bs', etiqueta: 'Cobrado (Bs)', tipo: 'numero' },
          { clave: 'fecha_cobro', etiqueta: 'Fecha de cobro', tipo: 'fecha', requerido: true },
          { clave: 'cuenta_bancaria_id', etiqueta: 'Cuenta bancaria', tipo: 'select', remoto: { servicio: 'cuentasBancarias', texto: 'numero_cuenta' } },
          { clave: 'nro_factura', etiqueta: 'Nro. de factura' },
          { clave: 'nro_recibo', etiqueta: 'Nro. de recibo' },
          { clave: 'comprobante_deposito', etiqueta: 'Comprobante de depósito' },
        ],
      },
    ],
  },
  {
    clave: 'pagos-clientes',
    titulo: 'Pagos de clientes por transferencia',
    servicio: pagosClientes,
    aprobacion: true,
    sinFormulario: true,
    accionesFila: [{ etiqueta: 'Ver comprobante', permiso: 'pagos-clientes.ver', visible: (f) => !!f.comprobante_nombre, ejecutar: (f) => comprobantePagoCliente(f.id) }],
    etiquetas: { aprobar: 'Confirmar', anular: 'Rechazar' },
    pedirMotivo: 'Motivo del rechazo (el cliente lo verá):',
    busqueda: 'Buscar por nro. de transacción o banco',
    columnas: [
      { clave: 'fecha_pago', etiqueta: 'Fecha', valor: (f) => fecha(f.fecha_pago) },
      { clave: 'empresa', etiqueta: 'Cliente', valor: (f) => f.empresa?.denominacion ?? '—' },
      { clave: 'contrato', etiqueta: 'Contrato', valor: (f) => f.contrato?.codigo_contrato ?? `#${f.contrato_id}` },
      { clave: 'cuota', etiqueta: 'Cuota', valor: (f) => f.cuota?.nro_cuota ?? '—' },
      { clave: 'monto_bs', etiqueta: 'Monto', valor: (f) => bs(f.monto_bs) },
      { clave: 'nro_transaccion', etiqueta: 'Nro. transacción' },
      { clave: 'banco_origen', etiqueta: 'Banco origen' },
      { clave: 'comprobante_nombre', etiqueta: 'Comprobante', valor: (f) => (f.comprobante_nombre ? 'Adjunto' : '—') },
      { clave: 'motivo_rechazo', etiqueta: 'Motivo rechazo' },
    ],
    filtros: [],
    secciones: [],
  },
]
