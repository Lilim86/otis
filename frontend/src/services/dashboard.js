import { http } from './http'

/** { total_edificios, total_equipos, total_contratos, monto_contratos, dolar_bob } */
export async function obtenerResumen() {
  const { data } = await http.get('/api/dashboard/resumen')
  return data.data
}
