import { http } from './http'
import { abrirArchivo } from './archivos'

/** Portal del cliente: todo está limitado por el backend a la empresa del usuario. */
const base = '/api/portal'
const leer = (ruta, params) => http.get(`${base}/${ruta}`, { params }).then((r) => r.data)

export const portal = {
  resumen: () => leer('resumen').then((r) => r.data),
  contratos: () => leer('contratos').then((r) => r.data),
  equipos: () => leer('equipos').then((r) => r.data),
  mantenimientos: (params) => leer('mantenimientos', params).then((r) => r.data),
  solicitarMantenimiento: (datos) => http.post(`${base}/mantenimientos/solicitar`, datos).then((r) => r.data.data),
  calificar: (id, datos) => http.post(`${base}/mantenimientos/${id}/calificar`, datos).then((r) => r.data.data),
  /** { intervalo_dias_habiles, data } */
  cronograma: (params) => leer('cronograma', params),
  cuotas: () => leer('cuotas').then((r) => r.data),
  cuentaDestino: () => leer('pagos/cuenta').then((r) => r.data),
  pagos: () => leer('pagos').then((r) => r.data),
  /** `datos` puede traer `comprobante` (un File): se envía como formulario. */
  pagar: (datos) => {
    const form = new FormData()
    for (const [k, v] of Object.entries(datos)) if (v !== null && v !== undefined && v !== '') form.append(k, v)
    return http.post(`${base}/pagos`, form).then((r) => r.data)
  },
  verComprobante: (pagoId) => abrirArchivo(`${base}/pagos/${pagoId}/comprobante`),
  verRecibo: (pagoId) => abrirArchivo(`${base}/pagos/${pagoId}/recibo`),
  verInforme: (visitaId) => abrirArchivo(`${base}/mantenimientos/${visitaId}/informe`),
  /** firma: { ancho, alto, trazos: [[[x, y], ...], ...] } */
  firmar: (visitaId, firma, firmadoPor) => http.post(`${base}/mantenimientos/${visitaId}/firmar`, { ...firma, firmado_por: firmadoPor }).then((r) => r.data.data),
}
