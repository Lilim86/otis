import { http } from './http'

export async function usuarioActual() {
  const { data } = await http.get('/api/user')
  return data
}

/** Inicia sesión con usuario y contraseña. Devuelve el usuario autenticado. */
export async function iniciarSesion({ username, password, remember = false }) {
  await http.get('/sanctum/csrf-cookie')
  const { data } = await http.post('/login', { username, password, remember })

  if (data?.two_factor) {
    return { requiereDosFactores: true }
  }

  return usuarioActual()
}

/**
 * Paso 1 del inicio de sesión con código OTP: valida usuario y contraseña y envía
 * un código de 6 dígitos al correo. Devuelve { message, correo, expira_en_segundos }.
 */
export async function solicitarCodigo({ username, password }) {
  await http.get('/sanctum/csrf-cookie')
  const { data } = await http.post('/api/auth/otp/solicitar', { username, password })
  return data
}

/** Paso 2: con el código recibido por correo se inicia la sesión. Devuelve el usuario. */
export async function verificarCodigo({ username, codigo }) {
  const { data } = await http.post('/api/auth/otp/verificar', { username, codigo })
  return data.user
}

export async function cerrarSesion() {
  await http.post('/logout')
}
