import { http } from './http'

/** Descarga el Kardex de inventario y lo abre como PDF en una pestaña nueva. */
export async function abrirKardexPdf() {
  const { data } = await http.get('/api/reportes/kardex', { responseType: 'blob' })
  const url = URL.createObjectURL(data)
  window.open(url, '_blank')
  setTimeout(() => URL.revokeObjectURL(url), 60_000)
}
