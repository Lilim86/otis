import { http } from './http'

/** Aportes de ingeniería: rutas óptimas, asignación por cercanía, confiabilidad, nivel de servicio y cobranza. */
const leer = (ruta, params) => http.get(`/api/ingenieria/${ruta}`, { params }).then((r) => r.data.data)
const enviar = (ruta, datos) => http.post(`/api/ingenieria/${ruta}`, datos).then((r) => r.data)

export const ingenieria = {
  resumen: (params) => leer('resumen', params),
  rutas: (fecha) => leer('rutas', { fecha }),
  aplicarRutas: (fecha) => enviar('rutas/aplicar', { fecha }),
  asignacion: (params) => leer('asignacion', params),
  asignar: (params) => enviar('asignacion/aplicar', params),
  confiabilidad: () => leer('confiabilidad'),
  adelantar: (edificioId) => enviar(`confiabilidad/${edificioId}/adelantar`),
  servicio: (params) => leer('servicio', params),
  clasificarFalla: (descripcion, urgente = false) => enviar('clasificar-falla', { descripcion, urgente }).then((r) => r.data),
  cobranza: () => http.get('/api/cobranzas/analisis').then((r) => r.data.data),
}
