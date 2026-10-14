import { http } from './http'

/** Listas cerradas para combos (tipos de documento, expedido en, estados, regionales...). */
export const catalogos = () => http.get('/api/catalogos').then((r) => r.data.data)

/**
 * Reportes de seguimiento. tipo: cuotas_por_facturar, facturas_por_cobrar, acumulado_regional,
 * acumulado_cliente, mora_regional, programado_vs_ejecutado. Params: gestion, regional_id.
 */
export const seguimiento = (params) => http.get('/api/reportes/seguimiento', { params }).then((r) => r.data.data)

/** agrupar_por: cobrador, regional, cuenta_bancaria, cliente, mes. Params: desde, hasta. */
export const reporteCobranzas = (params) => http.get('/api/reportes/cobranzas', { params }).then((r) => r.data.data)

/** tipo: contratos, repuestos, reparaciones. Param: dias. */
export const alertas = (params) => http.get('/api/alertas', { params }).then((r) => r.data.data)

/** Descarga un informe como CSV para Excel. `ruta` es la misma del informe, ej. '/api/reportes/seguimiento'. */
export async function descargarCsv(ruta, params) {
  const { data } = await http.get(ruta, { params: { ...params, formato: 'csv' }, responseType: 'blob' })
  const url = URL.createObjectURL(data)
  const enlace = Object.assign(document.createElement('a'), { href: url, download: `${ruta.split('/').pop()}.csv` })
  enlace.click()
  URL.revokeObjectURL(url)
}
