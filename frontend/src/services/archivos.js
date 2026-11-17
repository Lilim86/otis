import { http } from './http'

/** Si el servidor contestó con un error en JSON pero pedimos un archivo, el cuerpo llega como Blob: se lee para mostrar el mensaje. */
async function pedirArchivo(ruta, params) {
  try {
    return await http.get(ruta, { params, responseType: 'blob' })
  } catch (e) {
    if (e.response?.data instanceof Blob) {
      try {
        e.response.data = JSON.parse(await e.response.data.text())
      } catch {
        e.response.data = {}
      }
    }
    throw e
  }
}

const liberarLuego = (url) => setTimeout(() => URL.revokeObjectURL(url), 60_000)

/** Abre un PDF (o imagen) en una pestaña nueva. */
export async function abrirArchivo(ruta, params) {
  const { data } = await pedirArchivo(ruta, params)
  const url = URL.createObjectURL(data)
  window.open(url, '_blank')
  liberarLuego(url)
}

/** Baja un archivo con el nombre dado. */
export async function bajarArchivo(ruta, nombre, params) {
  const { data } = await pedirArchivo(ruta, params)
  const url = URL.createObjectURL(data)
  Object.assign(document.createElement('a'), { href: url, download: nombre }).click()
  liberarLuego(url)
}

// ---- facturas y recibos --------------------------------------------------------
export const comprobantes = {
  /** tipo: 'factura' | 'recibo' */
  ver: (cobranzaId, tipo) => abrirArchivo(`/api/comprobantes/cobranzas/${cobranzaId}/${tipo}`),
  modelo: (tipo) => abrirArchivo(`/api/comprobantes/modelo/${tipo}`),
  /** Envía el comprobante por correo al cliente. `correo` es opcional: sin él va al correo de la empresa. */
  enviar: (cobranzaId, tipo, correo) =>
    http.post(`/api/comprobantes/cobranzas/${cobranzaId}/enviar`, { tipo, correo: correo || null }).then((r) => r.data),
  envios: (cobranzaId) => http.get(`/api/comprobantes/cobranzas/${cobranzaId}/envios`).then((r) => r.data.data),
}

export const informeVisitaPdf = (visitaId) => abrirArchivo(`/api/visitas-mantenimiento/${visitaId}/informe`)
export const comprobantePagoCliente = (pagoId) => abrirArchivo(`/api/pagos-clientes/${pagoId}/comprobante`)

// ---- QR de cobro (lo administra Cobranzas y lo ve el cliente en su portal) -------
export const qrCobro = {
  datos: () => http.get('/api/pagos-qr').then((r) => r.data.data),
  /** URL local (blob) de la imagen: la ruta exige sesión, así que no sirve un <img src> directo. */
  imagen: async () => URL.createObjectURL((await pedirArchivo('/api/pagos-qr/imagen')).data),
  descargar: () => bajarArchivo('/api/pagos-qr/imagen', 'QR-pago-CGI.png'),
  /** datos: { banco, cuenta, titular, glosa, monto, vence, uso_unico, prueba, qr (File opcional) } */
  guardar: (datos) => {
    const form = new FormData()
    for (const [k, v] of Object.entries(datos)) {
      if (v === null || v === undefined || v === '') continue
      form.append(k, typeof v === 'boolean' ? (v ? '1' : '0') : v)
    }
    return http.post('/api/cobranzas/qr', form).then((r) => r.data)
  },
  restablecer: () => http.delete('/api/cobranzas/qr').then((r) => r.data),
}
