import { equiposSofia, visitasMantenimiento, informeVisitaPdf } from '@/services'
import GenerarCronograma from '@/components/GenerarCronograma.vue'
import { fecha, bs } from './comun'

const sustantivo = { PREVENTIVO: 'Preventivo', CORRECTIVO: 'Correctivo' }

export const tecnico = [
  {
    clave: 'equipos',
    titulo: 'Equipos y ascensores',
    servicio: equiposSofia,
    aprobacion: true,
    busqueda: 'Buscar por código, descripción, marca o modelo',
    columnas: [
      { clave: 'codigo', etiqueta: 'Código' },
      { clave: 'descripcion', etiqueta: 'Equipo' },
      { clave: 'edificio', etiqueta: 'Edificio', valor: (f) => f.edificio?.denominacion ?? '—' },
      { clave: 'marca', etiqueta: 'Marca' },
      { clave: 'modelo', etiqueta: 'Modelo' },
      { clave: 'paradas', etiqueta: 'Paradas' },
      { clave: 'precio_venta_cliente_bs', etiqueta: 'Precio cliente', valor: (f) => bs(f.precio_venta_cliente_bs) },
    ],
    filtros: [],
    secciones: [
      {
        titulo: 'Equipo',
        campos: [
          { clave: 'codigo', etiqueta: 'Código', requerido: true },
          { clave: 'descripcion', etiqueta: 'Descripción', requerido: true },
          { clave: 'edificio_id', etiqueta: 'Edificio donde está', tipo: 'select', remoto: { servicio: 'edificios', texto: 'denominacion' } },
          { clave: 'grupo_id', etiqueta: 'Grupo', tipo: 'select', remoto: { servicio: 'gruposBienes', texto: 'nombre', params: { nivel: 1 } } },
          { clave: 'unidad_medida', etiqueta: 'Unidad de medida', tipo: 'select', catalogo: 'unidades_medida' },
          { clave: 'industria', etiqueta: 'Industria' },
          { clave: 'marca', etiqueta: 'Marca' },
          { clave: 'modelo', etiqueta: 'Modelo' },
          { clave: 'paradas', etiqueta: 'Paradas', tipo: 'numero' },
          { clave: 'horas_servicio', etiqueta: 'Horas de servicio', tipo: 'numero' },
          { clave: 'caracteristicas', etiqueta: 'Características', tipo: 'textarea' },
        ],
      },
      {
        titulo: 'Precios',
        campos: [
          { clave: 'precio_compra_fob_bs', etiqueta: 'Compra FOB (Bs)', tipo: 'numero' },
          { clave: 'precio_venta_base_bs', etiqueta: 'Venta base (Bs)', tipo: 'numero' },
          { clave: 'precio_venta_cliente_bs', etiqueta: 'Venta al cliente (Bs)', tipo: 'numero' },
        ],
      },
    ],
  },
  {
    clave: 'visitas-mantenimiento',
    titulo: 'Cronograma e historial de mantenimiento',
    servicio: visitasMantenimiento,
    aprobacion: false,
    cabecera: GenerarCronograma,
    accionesFila: [{ etiqueta: 'Informe PDF', permiso: 'visitas-mantenimiento.ver', visible: (f) => f.situacion === 'REALIZADA', ejecutar: (f) => informeVisitaPdf(f.id) }],
    busqueda: 'Buscar en descripción o informe',
    columnas: [
      { clave: 'contrato', etiqueta: 'Cliente', valor: (f) => f.contrato?.empresa?.denominacion ?? `#${f.contrato_id}` },
      { clave: 'edificio', etiqueta: 'Edificio', valor: (f) => f.contrato?.edificio?.denominacion ?? '—' },
      { clave: 'tipo', etiqueta: 'Tipo', valor: (f) => `${sustantivo[f.tipo] ?? f.tipo}${f.nro_visita ? ` #${f.nro_visita}` : ''}` },
      { clave: 'fecha_programada', etiqueta: 'Programada', valor: (f) => fecha(f.fecha_programada) },
      { clave: 'fecha_realizada', etiqueta: 'Realizada', valor: (f) => fecha(f.fecha_realizada) },
      { clave: 'situacion', etiqueta: 'Situación', catalogo: 'situaciones_visita' },
      { clave: 'calificacion', etiqueta: 'Nota del cliente', valor: (f) => (f.calificacion ? `${f.calificacion} / 5` : '—') },
    ],
    filtros: [
      { clave: 'situacion', etiqueta: 'Situación', catalogo: 'situaciones_visita' },
      { clave: 'tipo', etiqueta: 'Tipo', catalogo: 'tipos_visita' },
    ],
    secciones: [
      {
        titulo: 'Visita',
        campos: [
          { clave: 'contrato_id', etiqueta: 'Contrato', tipo: 'select', requerido: true, remoto: { servicio: 'contratos', texto: 'codigo_contrato' } },
          {
            clave: 'tipo', etiqueta: 'Tipo', tipo: 'select', defecto: 'PREVENTIVO',
            opciones: [{ valor: 'PREVENTIVO', texto: 'Preventivo' }, { valor: 'CORRECTIVO', texto: 'Correctivo' }],
          },
          { clave: 'fecha_programada', etiqueta: 'Fecha programada', tipo: 'fecha', requerido: true },
          { clave: 'situacion', etiqueta: 'Situación', tipo: 'select', catalogo: 'situaciones_visita', defecto: 'PROGRAMADA' },
          { clave: 'tecnico_id', etiqueta: 'Técnico', tipo: 'select', remoto: { servicio: 'personal', texto: 'nombres' } },
          { clave: 'fecha_realizada', etiqueta: 'Fecha en que se realizó', tipo: 'fecha' },
          { clave: 'descripcion', etiqueta: 'Trabajo a realizar / falla reportada', tipo: 'textarea' },
          { clave: 'informe', etiqueta: 'Informe técnico (lo ve el cliente)', tipo: 'textarea' },
        ],
      },
    ],
  },
]
