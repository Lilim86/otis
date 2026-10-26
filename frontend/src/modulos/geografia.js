import { departamentos, provincias, municipios, zonas, viasAcceso } from '@/services'

const simple = (clave, titulo, servicio, padre) => ({
  clave,
  titulo,
  servicio,
  aprobacion: false,
  busqueda: 'Buscar por nombre',
  columnas: [
    { clave: 'nombre', etiqueta: 'Nombre' },
    ...(padre ? [{ clave: padre.relacion, etiqueta: padre.etiqueta, valor: (f) => f[padre.relacion]?.nombre ?? '—' }] : []),
  ],
  filtros: [],
  secciones: [
    {
      titulo: 'Datos',
      campos: [
        ...(padre ? [{ clave: padre.campo, etiqueta: padre.etiqueta, tipo: 'select', requerido: true, remoto: { servicio: padre.servicio, texto: 'nombre' } }] : []),
        { clave: 'nombre', etiqueta: 'Nombre', requerido: true },
      ],
    },
  ],
})

export const geografia = [
  simple('departamentos', 'Departamentos', departamentos, { campo: 'pais_id', relacion: 'pais', etiqueta: 'País', servicio: 'paises' }),
  simple('provincias', 'Provincias', provincias, { campo: 'departamento_id', relacion: 'departamento', etiqueta: 'Departamento', servicio: 'departamentos' }),
  simple('municipios', 'Municipios', municipios, { campo: 'provincia_id', relacion: 'provincia', etiqueta: 'Provincia', servicio: 'provincias' }),
  simple('zonas', 'Zonas / Barrios', zonas, { campo: 'municipio_id', relacion: 'municipio', etiqueta: 'Municipio', servicio: 'municipios' }),
  simple('vias-acceso', 'Vías de acceso', viasAcceso, { campo: 'zona_id', relacion: 'zona', etiqueta: 'Zona', servicio: 'zonas' }),
]
