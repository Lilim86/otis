import { http } from './http'
import { abrirArchivo } from './archivos'

/** Portal del cobrador: el backend limita todo a sus edificios y a sus cobros. */
const base = '/api/cobrador'

export const cobrador = {
  /** { data: { base, paradas, al_dia, resumen }, cuentas } */
  ruta: () => http.get(`${base}/ruta`).then((r) => r.data),
  cobros: (params) => http.get(`${base}/cobros`, { params }).then((r) => r.data.data),
  /** datos: { cuota_id, tipo_cobro, metodo_pago, monto, fecha_cobro, nro_recibo, nro_cheque, banco_cheque, nro_transferencia, cuenta_bancaria_id, ... } */
  registrar: (datos) => http.post(`${base}/cobros`, datos).then((r) => r.data),
  actualizar: (id, datos) => http.put(`${base}/cobros/${id}`, datos).then((r) => r.data.data),
  eliminar: (id) => http.delete(`${base}/cobros/${id}`),
  enviar: (ids) => http.post(`${base}/cobros/enviar`, { ids }).then((r) => r.data),
  /** tipo: 'factura' | 'recibo' */
  verComprobante: (id, tipo) => abrirArchivo(`${base}/cobros/${id}/${tipo}`),
}

/** Tesorería: bandeja de lo que mandan los cobradores. */
export const cobrosCobradores = {
  /** situacion: ENVIADO | OBSERVADO | APROBADO. Devuelve { data: [grupos por cobrador], por_aprobar } */
  listar: (situacion) => http.get('/api/ingresos-tesoreria/cobradores', { params: { situacion } }).then((r) => r.data),
  aprobar: (id, cuentaBancariaId) => http.post(`/api/ingresos-tesoreria/cobradores/${id}/aprobar`, { cuenta_bancaria_id: cuentaBancariaId }).then((r) => r.data),
  observar: (id, motivo) => http.post(`/api/ingresos-tesoreria/cobradores/${id}/observar`, { motivo }).then((r) => r.data),
}
