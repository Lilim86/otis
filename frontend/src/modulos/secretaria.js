import { citas } from '@/services'
import { fechaHora } from './comun'

export const secretaria = [
  {
    clave: 'citas',
    titulo: 'Agenda del gerente general',
    servicio: citas,
    aprobacion: false,
    busqueda: 'Buscar por asunto, visitante o lugar',
    columnas: [
      { clave: 'fecha_inicio', etiqueta: 'Inicio', valor: (f) => fechaHora(f.fecha_inicio) },
      { clave: 'fecha_fin', etiqueta: 'Fin', valor: (f) => String(f.fecha_fin ?? '').slice(11, 16) || '—' },
      { clave: 'titulo', etiqueta: 'Asunto' },
      { clave: 'tipo', etiqueta: 'Tipo', catalogo: 'tipos_cita' },
      { clave: 'con_quien', etiqueta: 'Con quién' },
      { clave: 'empresa', etiqueta: 'Empresa', valor: (f) => f.empresa?.denominacion ?? '—' },
      { clave: 'situacion', etiqueta: 'Situación', catalogo: 'situaciones_cita' },
    ],
    filtros: [
      { clave: 'situacion', etiqueta: 'Situación', catalogo: 'situaciones_cita' },
      { clave: 'tipo', etiqueta: 'Tipo', catalogo: 'tipos_cita' },
    ],
    secciones: [
      {
        titulo: 'Cita',
        campos: [
          { clave: 'titulo', etiqueta: 'Asunto', requerido: true },
          { clave: 'tipo', etiqueta: 'Tipo', tipo: 'select', catalogo: 'tipos_cita', defecto: 'CITA' },
          { clave: 'fecha_inicio', etiqueta: 'Inicio', tipo: 'fechahora', requerido: true },
          { clave: 'fecha_fin', etiqueta: 'Fin', tipo: 'fechahora', requerido: true },
          { clave: 'situacion', etiqueta: 'Situación', tipo: 'select', catalogo: 'situaciones_cita', defecto: 'PROGRAMADA' },
          { clave: 'lugar', etiqueta: 'Lugar' },
          { clave: 'con_quien', etiqueta: 'Con quién' },
          { clave: 'telefono', etiqueta: 'Teléfono' },
          { clave: 'empresa_id', etiqueta: 'Empresa', tipo: 'select', remoto: { servicio: 'empresas', texto: 'denominacion' } },
          { clave: 'edificio_id', etiqueta: 'Edificio', tipo: 'select', remoto: { servicio: 'edificios', texto: 'denominacion' } },
          { clave: 'motivo', etiqueta: 'Motivo / notas', tipo: 'textarea' },
        ],
      },
    ],
  },
]
