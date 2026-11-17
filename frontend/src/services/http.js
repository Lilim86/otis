import axios from 'axios'

const HOSTS_LOCALES = ['localhost', '127.0.0.1']

// En desarrollo la API se llama con el mismo host con el que se abrió la página:
// el navegador trata localhost y 127.0.0.1 como sitios distintos y, si no coinciden,
// no envía la cookie de sesión (error "CSRF token mismatch").
function urlDeLaApi() {
  const url = new URL(import.meta.env?.VITE_API_URL ?? 'http://127.0.0.1:8000')
  const hostDeLaPagina = window.location.hostname

  if (HOSTS_LOCALES.includes(url.hostname) && HOSTS_LOCALES.includes(hostDeLaPagina)) {
    url.hostname = hostDeLaPagina
  }

  return url.origin
}

export const http = axios.create({
  baseURL: urlDeLaApi(),
  withCredentials: true,
  withXSRFToken: true,
  headers: {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
})

// Si la sesión expira, avisa a la aplicación para que redirija al login.
http.interceptors.response.use(
  (respuesta) => respuesta,
  (error) => {
    if (error.response?.status === 401) {
      window.dispatchEvent(new CustomEvent('sesion-expirada'))
    }
    return Promise.reject(error)
  },
)

/**
 * Convierte un error de axios en { mensaje, campos }.
 * `campos` trae el primer mensaje de cada campo inválido (errores 422 de Laravel).
 */
export function leerError(error) {
  const datos = error.response?.data
  const campos = {}

  for (const [campo, mensajes] of Object.entries(datos?.errors ?? {})) {
    campos[campo] = mensajes[0]
  }

  return {
    estado: error.response?.status ?? null,
    mensaje: datos?.message ?? 'No se pudo conectar con el servidor.',
    campos,
  }
}
