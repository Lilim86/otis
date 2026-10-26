import { cargos, profesiones, personal } from '@/services'

const lista = (clave, titulo, servicio) => ({
  clave,
  titulo,
  servicio,
  aprobacion: false,
  busqueda: 'Buscar por nombre',
  columnas: [{ clave: 'nombre', etiqueta: 'Nombre' }],
  filtros: [],
  secciones: [{ titulo: 'Datos', campos: [{ clave: 'nombre', etiqueta: 'Nombre', requerido: true }] }],
})

export const rrhh = [
  lista('cargos', 'Cargos funcionales', cargos),
  lista('profesiones', 'Profesiones y ocupaciones', profesiones),
  {
    clave: 'personal',
    titulo: 'Registro de personal',
    servicio: personal,
    aprobacion: false,
    busqueda: 'Buscar por CI o nombre',
    columnas: [
      { clave: 'ci', etiqueta: 'CI' },
      { clave: 'nombre', etiqueta: 'Nombre', valor: (f) => `${f.apellidos} ${f.nombres}` },
      { clave: 'cargo', etiqueta: 'Cargo', valor: (f) => f.cargo?.nombre ?? '—' },
      { clave: 'profesion', etiqueta: 'Profesión', valor: (f) => f.profesion?.nombre ?? '—' },
    ],
    filtros: [],
    secciones: [
      {
        titulo: 'Datos del empleado',
        campos: [
          { clave: 'ci', etiqueta: 'CI', requerido: true },
          { clave: 'nombres', etiqueta: 'Nombres', requerido: true },
          { clave: 'apellidos', etiqueta: 'Apellidos', requerido: true },
          { clave: 'cargo_id', etiqueta: 'Cargo', tipo: 'select', requerido: true, remoto: { servicio: 'cargos', texto: 'nombre' } },
          { clave: 'profesion_id', etiqueta: 'Profesión', tipo: 'select', requerido: true, remoto: { servicio: 'profesiones', texto: 'nombre' } },
        ],
      },
    ],
  },
]
