import { http } from './http'

/** Reportes que une varias tablas (cliente, edificio, ubicación...). Solo salen los que el rol puede ver. */
export const catalogoReportes = () => http.get('/api/reportes/combinados').then((r) => r.data.data)

export const generarReporte = (clave, params = {}) =>
  http.get(`/api/reportes/combinados/${clave}`, { params }).then((r) => r.data.data)

/** Baja el reporte como PDF o CSV y lo abre/descarga. */
export async function descargarReporte(clave, formato, params = {}) {
  const { data } = await http.get(`/api/reportes/combinados/${clave}`, { params: { ...params, formato }, responseType: 'blob' })
  const url = URL.createObjectURL(data)
  if (formato === 'pdf') {
    window.open(url, '_blank')
  } else {
    Object.assign(document.createElement('a'), { href: url, download: `${clave}.csv` }).click()
  }
  setTimeout(() => URL.revokeObjectURL(url), 60_000)
}
