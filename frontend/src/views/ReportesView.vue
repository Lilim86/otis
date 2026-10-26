<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { catalogoReportes, generarReporte, descargarReporte, departamentos, leerError } from '@/services'

const catalogo = ref({})
const clave = ref('')
const filtros = reactive({ desde: '', hasta: '', departamento_id: '', q: '' })
const regionales = ref([])
const reporte = ref(null)
const cargando = ref(false)
const error = ref('')

const sinReportes = computed(() => !Object.keys(catalogo.value).length)
const parametros = () => Object.fromEntries(Object.entries(filtros).filter(([, v]) => v !== ''))

async function cargar() {
  if (!clave.value) {
    reporte.value = null
    return
  }
  cargando.value = true
  error.value = ''
  try {
    reporte.value = await generarReporte(clave.value, parametros())
  } catch (e) {
    error.value = leerError(e).mensaje
    reporte.value = null
  } finally {
    cargando.value = false
  }
}

let temporizador = null
watch(clave, cargar)
watch(filtros, () => {
  clearTimeout(temporizador)
  temporizador = setTimeout(cargar, 350)
})

onMounted(async () => {
  try {
    catalogo.value = await catalogoReportes()
    regionales.value = await departamentos.todos()
  } catch (e) {
    error.value = leerError(e).mensaje
  }
})

const descargar = (formato) => descargarReporte(clave.value, formato, parametros()).catch((e) => (error.value = leerError(e).mensaje))
const numero = (v) => (typeof v === 'number' ? v.toLocaleString('es-BO', { maximumFractionDigits: 2 }) : (v ?? ''))
</script>

<template>
  <div>
    <h1 class="text-xl font-bold text-marca-900">Reportes combinados</h1>
    <p class="mt-1 text-sm text-slate-500">Cada reporte une el módulo con las tablas relacionadas y su ubicación completa. Ves solo los de tu rol.</p>

    <p v-if="error" class="mt-3 rounded bg-red-50 px-3 py-2 text-sm text-red-700">{{ error }}</p>
    <p v-if="sinReportes && !error" class="mt-4 text-slate-500">Tu rol no tiene reportes disponibles.</p>

    <div v-else class="mt-4 grid gap-3 rounded-lg bg-white p-4 shadow-sm md:grid-cols-6">
      <label class="text-xs text-slate-500 md:col-span-2">
        Reporte
        <select v-model="clave" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm text-slate-800">
          <option value="">— Elige un reporte —</option>
          <optgroup v-for="(lista, area) in catalogo" :key="area" :label="area">
            <option v-for="(titulo, k) in lista" :key="k" :value="k">{{ titulo }}</option>
          </optgroup>
        </select>
      </label>
      <label class="text-xs text-slate-500">Desde<input v-model="filtros.desde" type="date" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm text-slate-800" /></label>
      <label class="text-xs text-slate-500">Hasta<input v-model="filtros.hasta" type="date" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm text-slate-800" /></label>
      <label class="text-xs text-slate-500">
        Regional
        <select v-model="filtros.departamento_id" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm text-slate-800">
          <option value="">Todas</option>
          <option v-for="d in regionales" :key="d.id" :value="d.id">{{ d.nombre }}</option>
        </select>
      </label>
      <label class="text-xs text-slate-500">Buscar<input v-model="filtros.q" placeholder="Cliente, edificio…" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 text-sm text-slate-800" /></label>

      <div v-if="reporte" class="flex items-end justify-end gap-2 md:col-span-6">
        <button class="rounded border border-marca-900 px-4 py-2 text-sm font-medium text-marca-900 hover:bg-slate-50" @click="descargar('csv')">Excel (CSV)</button>
        <button class="rounded bg-marca-900 px-4 py-2 text-sm font-medium text-white hover:bg-marca-700" @click="descargar('pdf')">Imprimir PDF</button>
      </div>
    </div>

    <p v-if="cargando" class="mt-4 text-slate-400">Generando…</p>
    <div v-else-if="reporte" class="mt-4 overflow-x-auto rounded-lg bg-white shadow-sm">
      <p class="border-b border-slate-100 px-4 py-3 text-sm text-slate-600">{{ reporte.titulo }} — {{ reporte.filas.length }} registro(s)</p>
      <table class="min-w-full text-left text-xs">
        <thead class="bg-slate-50 uppercase text-slate-500">
          <tr><th v-for="(etiqueta, k) in reporte.columnas" :key="k" class="whitespace-nowrap px-3 py-2">{{ etiqueta }}</th></tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-for="(fila, i) in reporte.filas.slice(0, 200)" :key="i" class="hover:bg-slate-50">
            <td v-for="(etiqueta, k) in reporte.columnas" :key="k" class="px-3 py-2">{{ numero(fila[k]) }}</td>
          </tr>
          <tr v-if="!reporte.filas.length"><td :colspan="Object.keys(reporte.columnas).length" class="px-3 py-8 text-center text-slate-400">No hay datos para este reporte.</td></tr>
          <tr v-if="Object.keys(reporte.sumas).length" class="bg-slate-50 font-bold text-marca-900">
            <td v-for="(etiqueta, k, i) in reporte.columnas" :key="k" class="px-3 py-2">{{ i === 0 ? 'TOTALES' : reporte.sumas[k] != null ? numero(reporte.sumas[k]) : '' }}</td>
          </tr>
        </tbody>
      </table>
      <p v-if="reporte.filas.length > 200" class="px-4 py-2 text-xs text-slate-400">Vista previa de 200; el PDF y el CSV traen todos.</p>
    </div>
  </div>
</template>
