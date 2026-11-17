<script setup>
import { ref, onMounted } from 'vue'
import { movimientosAlmacen, leerError } from '@/services'
import { fecha } from '@/modulos/comun'

const filas = ref([])
const cargando = ref(true)
const error = ref('')

onMounted(async () => {
  try {
    filas.value = await movimientosAlmacen.herramientasEnPoder()
  } catch (e) {
    error.value = leerError(e).mensaje
  } finally {
    cargando.value = false
  }
})
</script>

<template>
  <div>
    <h1 class="text-xl font-bold text-marca-900">Herramientas en poder</h1>
    <p class="text-sm text-slate-500">Lo que salió del almacén como préstamo y todavía no se devolvió. Para devolver, registra un ingreso con motivo “Devolución” en Movimientos de almacén.</p>
    <p v-if="error" class="mt-3 rounded bg-red-50 px-3 py-2 text-sm text-red-700">{{ error }}</p>
    <div class="mt-4 overflow-x-auto rounded-lg bg-white shadow-sm">
      <table class="min-w-full text-left text-sm">
        <thead class="bg-slate-50 text-xs uppercase text-slate-500">
          <tr><th class="px-4 py-3">Quién la tiene</th><th class="px-4 py-3">Herramienta</th><th class="px-4 py-3">Cantidad</th><th class="px-4 py-3">Desde</th><th class="px-4 py-3">Días fuera</th></tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-if="cargando"><td colspan="5" class="px-4 py-8 text-center text-slate-400">Cargando…</td></tr>
          <tr v-else-if="!filas.length"><td colspan="5" class="px-4 py-8 text-center text-slate-400">Todas las herramientas están en el almacén.</td></tr>
          <tr v-for="f in filas" v-else :key="`${f.personal_id}-${f.insumo_id}`">
            <td class="px-4 py-2 font-medium text-marca-900">{{ f.persona }}</td>
            <td class="px-4 py-2">{{ f.herramienta }} <span class="text-xs text-slate-400">{{ f.codigo }}</span></td>
            <td class="px-4 py-2">{{ f.cantidad }}</td>
            <td class="px-4 py-2">{{ fecha(f.desde) }}</td>
            <td class="px-4 py-2"><span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="f.dias > 15 ? 'bg-rose-100 text-rose-800' : 'bg-slate-100 text-slate-700'">{{ f.dias }} días</span></td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
