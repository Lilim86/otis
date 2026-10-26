<script setup>
import { ref, onMounted } from 'vue'
import { contratos, visitasMantenimiento, leerError } from '@/services'
import { useSesionStore } from '@/stores/sesion'

const emit = defineEmits(['hecho'])
const sesion = useSesionStore()

const abierto = ref(false)
const lista = ref([])
const contratoId = ref('')
const regenerar = ref(false)
const mensaje = ref('')
const error = ref('')
const trabajando = ref(false)

onMounted(async () => {
  try {
    lista.value = await contratos.todos()
  } catch {
    lista.value = []
  }
})

async function generar() {
  error.value = ''
  mensaje.value = ''
  if (!contratoId.value) {
    error.value = 'Elige un contrato.'
    return
  }
  trabajando.value = true
  try {
    const r = await visitasMantenimiento.generarCronograma({ contrato_id: contratoId.value, regenerar: regenerar.value })
    mensaje.value = `Cronograma listo: ${r.total} visita(s) preventivas, una cada 30 días hábiles.`
    emit('hecho')
  } catch (e) {
    error.value = leerError(e).mensaje
  } finally {
    trabajando.value = false
  }
}
</script>

<template>
  <div v-if="sesion.puede('visitas-mantenimiento.crear')" class="relative">
    <button class="rounded border border-oro-500 px-4 py-2 text-sm font-medium text-marca-900 hover:bg-oro-50" @click="abierto = !abierto">
      Generar cronograma
    </button>
    <div v-if="abierto" class="absolute right-0 z-10 mt-2 w-80 rounded-lg border border-slate-200 bg-white p-4 shadow-xl">
      <p class="text-sm text-slate-600">Crea una visita preventiva cada 30 días hábiles (lunes a viernes, sin feriados) durante el contrato.</p>
      <select v-model="contratoId" class="mt-3 w-full rounded border border-slate-300 px-3 py-2 text-sm">
        <option value="">— Contrato —</option>
        <option v-for="c in lista" :key="c.id" :value="c.id">{{ c.codigo_contrato ?? `#${c.id}` }} · {{ c.empresa?.denominacion }}</option>
      </select>
      <label class="mt-3 flex items-center gap-2 text-xs text-slate-600">
        <input v-model="regenerar" type="checkbox" /> Rehacer las pendientes (conserva las realizadas)
      </label>
      <p v-if="error" class="mt-2 text-xs text-red-600">{{ error }}</p>
      <p v-if="mensaje" class="mt-2 text-xs text-emerald-700">{{ mensaje }}</p>
      <button :disabled="trabajando" class="mt-3 w-full rounded bg-marca-900 py-2 text-sm font-medium text-white hover:bg-marca-700 disabled:opacity-60" @click="generar">
        {{ trabajando ? 'Generando…' : 'Generar' }}
      </button>
    </div>
  </div>
</template>
