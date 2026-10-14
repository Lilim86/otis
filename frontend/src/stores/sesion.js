import { ref, computed } from 'vue'
import { defineStore } from 'pinia'
import { usuarioActual, solicitarCodigo, verificarCodigo, cerrarSesion } from '@/services'

export const useSesionStore = defineStore('sesion', () => {
  const usuario = ref(null)
  const cargada = ref(false)

  const autenticado = computed(() => usuario.value !== null)

  /** Pregunta al backend si hay una sesión activa. Se hace una sola vez al abrir la aplicación. */
  async function cargar() {
    if (cargada.value) return
    try {
      usuario.value = await usuarioActual()
    } catch {
      usuario.value = null
    } finally {
      cargada.value = true
    }
  }

  const pedirCodigo = (credenciales) => solicitarCodigo(credenciales)

  async function confirmarCodigo(datos) {
    await verificarCodigo(datos)
    usuario.value = await usuarioActual() // trae también roles y permisos
  }

  async function salir() {
    try {
      await cerrarSesion()
    } finally {
      usuario.value = null
    }
  }

  /**
   * Con privilegios desactivados, o siendo Super Administrador, todo está permitido.
   * Si no, se necesita el permiso "modulo.operacion".
   */
  function puede(permiso) {
    const u = usuario.value
    if (!u) return false
    if (u.es_super_admin || !u.permisos_activos) return true
    return u.permisos?.includes(permiso) ?? false
  }

  return { usuario, cargada, autenticado, cargar, pedirCodigo, confirmarCodigo, salir, puede }
})
