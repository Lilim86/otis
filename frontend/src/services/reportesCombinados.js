import { http } from './http'
import { abrirArchivo, bajarArchivo } from './archivos'

/** Reportes que une varias tablas (cliente, edificio, ubicación...). Solo salen los que el rol puede ver. */
export const catalogoReportes = () => http.get('/api/reportes/combinados').then((r) => r.data.data)

export const generarReporte = (clave, params = {}) =>
  http.get(`/api/reportes/combinados/${clave}`, { params }).then((r) => r.data.data)

const EXTENSION = { xlsx: 'xlsx', csv: 'csv' }

/** Baja el reporte: el PDF se abre en otra pestaña; Excel (xlsx) y CSV se descargan. */
export function descargarReporte(clave, formato, params = {}) {
  const ruta = `/api/reportes/combinados/${clave}`
  const fecha = new Date().toISOString().slice(0, 10).replaceAll('-', '')
  return formato === 'pdf'
    ? abrirArchivo(ruta, { ...params, formato })
    : bajarArchivo(ruta, `${clave}_${fecha}.${EXTENSION[formato]}`, { ...params, formato })
}
