// Piezas que se repiten en varios módulos.
export const ubicacion = [
  { clave: 'pais_id', etiqueta: 'País', tipo: 'select', remoto: { servicio: 'paises', texto: 'nombre' } },
  { clave: 'departamento_id', etiqueta: 'Departamento', tipo: 'select', remoto: { servicio: 'departamentos', texto: 'nombre', depende: 'pais_id' } },
  { clave: 'provincia_id', etiqueta: 'Provincia', tipo: 'select', remoto: { servicio: 'provincias', texto: 'nombre', depende: 'departamento_id' } },
  { clave: 'municipio_id', etiqueta: 'Municipio', tipo: 'select', remoto: { servicio: 'municipios', texto: 'nombre', depende: 'provincia_id' } },
  { clave: 'zona_id', etiqueta: 'Zona / Barrio', tipo: 'select', remoto: { servicio: 'zonas', texto: 'nombre', depende: 'municipio_id' } },
  { clave: 'via_acceso_id', etiqueta: 'Vía de acceso (calle, av.)', tipo: 'select', remoto: { servicio: 'viasAcceso', texto: 'nombre', depende: 'zona_id' } },
]

export const regional = { clave: 'regional_id', etiqueta: 'Regional', tipo: 'select', remoto: { servicio: 'departamentos', texto: 'nombre' } }

export const bs = (n) => (n == null || n === '' ? '—' : new Intl.NumberFormat('es-BO', { style: 'currency', currency: 'BOB' }).format(n))

export const fecha = (v) => (v ? String(v).slice(0, 10).split('-').reverse().join('/') : '—')
export const fechaHora = (v) => (v ? `${fecha(v)} ${String(v).slice(11, 16)}` : '—')
