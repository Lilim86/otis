import { empresas, edificios, contratos, cuotasContrato, pedidosCatalogo } from '@/services'
import { ubicacion, regional, bs, fecha, fechaHora, nombrePersona } from './comun'

const accionPedido = (etiqueta, situacion, confirmar, resultado) => ({
  etiqueta,
  permiso: 'pedidos-catalogo.editar',
  recargar: true,
  visible: (f) => f.situacion === 'PENDIENTE',
  async ejecutar(f) {
    if (confirmar && !window.confirm(`¿Cancelar el pedido ${f.codigo}?`)) return
    await pedidosCatalogo.modificar(f.id, { situacion })
    return `${f.codigo} ${resultado}.`
  },
})

export const comercial = [
  {
    clave: 'pedidos-catalogo',
    titulo: 'Pedidos del catálogo',
    servicio: pedidosCatalogo,
    sinFormulario: true, // los crea el cliente desde su portal
    busqueda: 'Buscar en las observaciones',
    accionesFila: [accionPedido('Marcar atendido', 'ATENDIDO', false, 'marcado como atendido'), accionPedido('Cancelar', 'CANCELADO', true, 'cancelado')],
    columnas: [
      { clave: 'codigo', etiqueta: 'Pedido' },
      { clave: 'cliente', etiqueta: 'Cliente', valor: (f) => f.empresa?.denominacion ?? '—' },
      { clave: 'edificio', etiqueta: 'Edificio', valor: (f) => f.edificio?.denominacion ?? '—' },
      { clave: 'producto', etiqueta: 'Producto', valor: (f) => f.producto?.nombre ?? '—' },
      { clave: 'cantidad', etiqueta: 'Cant.' },
      { clave: 'reunion', etiqueta: 'Reunión', valor: (f) => (f.cita ? fechaHora(f.cita.fecha_inicio) : fecha(f.fecha_compromiso)) },
      { clave: 'situacion', etiqueta: 'Situación', catalogo: 'situaciones_pedido' },
      { clave: 'observaciones', etiqueta: 'Observaciones' },
    ],
    filtros: [{ clave: 'situacion', etiqueta: 'Situación', catalogo: 'situaciones_pedido' }],
    secciones: [],
  },
  {
    clave: 'empresas',
    titulo: 'Empresas',
    servicio: empresas,
    aprobacion: true,
    busqueda: 'Buscar por denominación, NIT o sigla',
    columnas: [
      { clave: 'denominacion', etiqueta: 'Denominación' },
      { clave: 'nit', etiqueta: 'NIT' },
      { clave: 'tipo_empresa', etiqueta: 'Tipo' },
      { clave: 'telefono_celular', etiqueta: 'Celular' },
    ],
    filtros: [],
    secciones: [
      {
        titulo: 'Datos',
        campos: [
          { clave: 'denominacion', etiqueta: 'Denominación', requerido: true },
          { clave: 'nit', etiqueta: 'NIT', requerido: true },
          { clave: 'tipo_empresa', etiqueta: 'Tipo de empresa', tipo: 'select', catalogo: 'tipos_empresa', requerido: true },
          { clave: 'sigla', etiqueta: 'Sigla' },
          { clave: 'fecha_creacion', etiqueta: 'Fecha de creación', tipo: 'fecha' },
        ],
      },
      {
        titulo: 'Contacto',
        campos: [
          { clave: 'telefono_fijo', etiqueta: 'Teléfono fijo' },
          { clave: 'telefono_celular', etiqueta: 'Celular' },
          { clave: 'correo_contacto', etiqueta: 'Correo', tipo: 'email' },
          { clave: 'pagina_web', etiqueta: 'Página web' },
        ],
      },
      { titulo: 'Ubicación', campos: ubicacion },
    ],
  },
  {
    clave: 'edificios',
    titulo: 'Edificios y proyectos',
    servicio: edificios,
    aprobacion: true,
    busqueda: 'Buscar por código o denominación',
    columnas: [
      { clave: 'codigo_edificio', etiqueta: 'Código' },
      { clave: 'denominacion', etiqueta: 'Edificio' },
      { clave: 'tipo_edificacion', etiqueta: 'Tipo' },
      { clave: 'empresa', etiqueta: 'Empresa', valor: (f) => f.empresa?.denominacion ?? '—' },
      { clave: 'cobrador', etiqueta: 'Cobrador', valor: (f) => (f.cobrador ? nombrePersona(f.cobrador) : '—') },
    ],
    filtros: [],
    secciones: [
      {
        titulo: 'Datos',
        campos: [
          { clave: 'codigo_edificio', etiqueta: 'Código', requerido: true },
          { clave: 'denominacion', etiqueta: 'Denominación', requerido: true },
          { clave: 'tipo_edificacion', etiqueta: 'Tipo de edificación', tipo: 'select', catalogo: 'tipos_edificacion', requerido: true },
          { clave: 'empresa_id', etiqueta: 'Empresa (cliente)', tipo: 'select', remoto: { servicio: 'empresas', texto: 'denominacion' } },
          // El cobrador que pasa a cobrar a este edificio: el edificio aparece en su ruta.
          { clave: 'cobrador_id', etiqueta: 'Cobrador asignado', tipo: 'select', remoto: { servicio: 'personas', texto: nombrePersona, params: { tipo_persona: 'COBRADOR' } } },
          { clave: 'referencia_cercana', etiqueta: 'Referencia cercana' },
        ],
      },
      { titulo: 'Ubicación', campos: ubicacion },
    ],
  },
  {
    clave: 'contratos',
    titulo: 'Contratos de ventas',
    servicio: contratos,
    aprobacion: true,
    busqueda: 'Buscar por código',
    columnas: [
      { clave: 'codigo_contrato', etiqueta: 'Contrato' },
      { clave: 'empresa', etiqueta: 'Cliente', valor: (f) => f.empresa?.denominacion ?? '—' },
      { clave: 'edificio', etiqueta: 'Edificio', valor: (f) => f.edificio?.denominacion ?? '—' },
      { clave: 'fecha_inicio', etiqueta: 'Inicio', valor: (f) => fecha(f.fecha_inicio) },
      { clave: 'fecha_fin', etiqueta: 'Fin', valor: (f) => fecha(f.fecha_fin) },
      { clave: 'monto_total', etiqueta: 'Monto', valor: (f) => bs(f.monto_total) },
    ],
    filtros: [],
    secciones: [
      {
        titulo: 'Contrato',
        campos: [
          { clave: 'codigo_contrato', etiqueta: 'Código del contrato' },
          { clave: 'empresa_id', etiqueta: 'Cliente (empresa)', tipo: 'select', requerido: true, remoto: { servicio: 'empresas', texto: 'denominacion' } },
          { clave: 'edificio_id', etiqueta: 'Edificio', tipo: 'select', requerido: true, remoto: { servicio: 'edificios', texto: 'denominacion' } },
          regional,
          { clave: 'unidad_oficina', etiqueta: 'Unidad / oficina' },
          { clave: 'fecha_inicio', etiqueta: 'Fecha de inicio', tipo: 'fecha', requerido: true },
          { clave: 'fecha_fin', etiqueta: 'Fecha de fin', tipo: 'fecha', requerido: true },
          { clave: 'monto_total', etiqueta: 'Monto total (Bs)', tipo: 'numero', requerido: true },
        ],
      },
    ],
  },
  {
    clave: 'cuotas-contrato',
    titulo: 'Cuotas de contrato',
    servicio: cuotasContrato,
    aprobacion: false,
    busqueda: 'Buscar por concepto',
    columnas: [
      { clave: 'contrato', etiqueta: 'Cliente', valor: (f) => f.contrato?.empresa?.denominacion ?? `#${f.contrato_id}` },
      { clave: 'nro_cuota', etiqueta: 'Cuota' },
      { clave: 'fecha_cuota', etiqueta: 'Vence', valor: (f) => fecha(f.fecha_cuota) },
      { clave: 'estado_cuota', etiqueta: 'Situación', catalogo: 'estados_cuota' },
      { clave: 'monto_bs', etiqueta: 'Monto', valor: (f) => bs(f.monto_bs) },
    ],
    filtros: [{ clave: 'estado_cuota', etiqueta: 'Situación', catalogo: 'estados_cuota' }],
    secciones: [
      {
        titulo: 'Cuota',
        campos: [
          { clave: 'contrato_id', etiqueta: 'Contrato', tipo: 'select', requerido: true, remoto: { servicio: 'contratos', texto: 'codigo_contrato' } },
          { clave: 'nro_cuota', etiqueta: 'Nro. de cuota', tipo: 'numero', requerido: true },
          { clave: 'fecha_cuota', etiqueta: 'Fecha de la cuota', tipo: 'fecha', requerido: true },
          { clave: 'monto_bs', etiqueta: 'Monto (Bs)', tipo: 'numero', requerido: true },
          { clave: 'estado_cuota', etiqueta: 'Situación', tipo: 'select', catalogo: 'estados_cuota', defecto: 'PENDIENTE' },
          { clave: 'concepto', etiqueta: 'Concepto' },
        ],
      },
    ],
  },
]
