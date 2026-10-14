import axios from 'axios'

// Abre el frontend en http://127.0.0.1:5173 (no localhost) para que la cookie de
// sesión se comparta con el backend en http://127.0.0.1:8000.
export const http = axios.create({
  baseURL: import.meta.env?.VITE_API_URL ?? 'http://127.0.0.1:8000',
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
