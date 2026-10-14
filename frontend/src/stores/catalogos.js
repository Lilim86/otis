import { ref } from 'vue'
import { defineStore } from 'pinia'
import { catalogos } from '@/services'

export const useCatalogosStore = defineStore('catalogos', () => {
  const datos = ref({})
  let cargando = null

  /** Descarga las listas cerradas una sola vez (aunque varias pantallas las pidan a la vez). */
  function cargar() {
    cargando ??= catalogos().then((r) => (datos.value = r)).catch((e) => { cargando = null; throw e })
    return cargando
  }

  const etiqueta = (lista, codigo) => datos.value[lista]?.find((o) => o.codigo === codigo)?.etiqueta ?? codigo

  return { datos, cargar, etiqueta }
})
