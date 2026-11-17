import { ref, computed } from 'vue'
import { defineStore } from 'pinia'
import { cobrador } from '@/services'

/** Datos del portal del cobrador que comparten la ruta y los recibos. */
export const useCobradorStore = defineStore('cobrador', () => {
  const ruta = ref(null)
  const cuentas = ref([])
  const cobros = ref([])

  async function cargarRuta() {
    const r = await cobrador.ruta()
    ruta.value = r.data
    cuentas.value = r.cuentas
  }

  async function cargarCobros() {
    cobros.value = await cobrador.cobros()
  }

  const recargar = () => Promise.all([cargarRuta(), cargarCobros()])

  /** Lo que el cobrador todavía tiene que entregar a Tesorería. */
  const porEnviar = computed(() => cobros.value.filter((c) => ['PENDIENTE', 'OBSERVADO'].includes(c.situacion_tesoreria)))

  return { ruta, cuentas, cobros, porEnviar, cargarRuta, cargarCobros, recargar }
})
