<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { portal, leerError } from '@/services'
import { fecha } from '@/modulos/comun'

const visitas = ref([])
const contratos = ref([])
const contratoId = ref('')
const intervalo = ref(30)
const error = ref('')
const cargando = ref(false)

async function cargar() {
  cargando.value = true
  try {
    const r = await portal.cronograma(contratoId.value ? { contrato_id: contratoId.value } : {})
    visitas.value = r.data
    intervalo.value = r.intervalo_dias_habiles
  } catch (e) {
    error.value = leerError(e).mensaje
  } finally {
    cargando.value = false
  }
}
onMounted(async () => {
  contratos.value = await portal.contratos().catch(() => [])
  cargar()
})
watch(contratoId, cargar)

const estilo = {
  REALIZADA: { punto: 'bg-emerald-500', texto: 'Realizada' },
  PROGRAMADA: { punto: 'bg-marca-600', texto: 'Programada' },
  REPROGRAMADA: { punto: 'bg-amber-500', texto: 'Reprogramada' },
  CANCELADA: { punto: 'bg-slate-400', texto: 'Cancelada' },
  SOLICITADA: { punto: 'bg-oro-500', texto: 'Solicitada' },
}
const proxima = computed(() => visitas.value.find((v) => v.situacion !== 'REALIZADA' && v.situacion !== 'CANCELADA' && v.dias >= 0))
const realizadas = computed(() => visitas.value.filter((v) => v.situacion === 'REALIZADA').length)
const avance = computed(() => (visitas.value.length ? Math.round((realizadas.value / visitas.value.length) * 100) : 0))
const cuando = (d) => (d === null ? '' : d < 0 ? `hace ${-d} día(s)` : d === 0 ? 'hoy' : d === 1 ? 'mañana' : `en ${d} días`)
const mes = (f) => new Date(`${f}T12:00:00`).toLocaleDateString('es-BO', { month: 'long', year: 'numeric' })
</script>

<template>
  <div>
    <h1 class="font-display text-2xl font-semibold text-marca-900">Cronograma de mantenimiento</h1>
    <p class="text-sm text-slate-500">Una visita preventiva cada {{ intervalo }} días hábiles (lunes a viernes, sin feriados) durante tu contrato.</p>

    <p v-if="error" class="mt-3 rounded bg-red-50 px-3 py-2 text-sm text-red-700">{{ error }}</p>

    <div class="mt-4 flex flex-wrap items-center gap-4 rounded-lg bg-white p-4 shadow-sm">
      <select v-model="contratoId" class="rounded border border-slate-300 px-2 py-2 text-sm"><option value="">Todos los contratos</option><option v-for="c in contratos" :key="c.id" :value="c.id">{{ c.codigo }} · {{ c.edificio }}</option></select>
      <div class="min-w-48 flex-1">
        <div class="flex justify-between text-xs text-slate-500"><span>Avance del contrato</span><span>{{ realizadas }} de {{ visitas.length }} visitas</span></div>
        <div class="mt-1 h-2 overflow-hidden rounded-full bg-slate-100"><div class="h-full bg-gradient-to-r from-oro-500 to-oro-300" :style="{ width: avance + '%' }"></div></div>
      </div>
    </div>

    <div v-if="proxima" class="mt-4 rounded-lg border border-oro-300 bg-oro-50 px-4 py-3 text-sm text-oro-900">
      Próxima visita: <strong>{{ fecha(proxima.fecha_programada) }}</strong> ({{ cuando(proxima.dias) }}) · {{ proxima.edificio }}
    </div>

    <p v-if="cargando" class="mt-6 text-slate-400">Cargando…</p>
    <p v-else-if="!visitas.length" class="mt-6 rounded-lg bg-white p-8 text-center text-slate-400 shadow-sm">Todavía no hay cronograma para tus contratos. La empresa lo genera al firmar el contrato.</p>
    <ol v-else class="relative mt-6 border-s-2 border-oro-300 ps-6">
      <li v-for="(v, i) in visitas" :key="v.id" class="mb-5">
        <p v-if="i === 0 || mes(v.fecha_programada) !== mes(visitas[i - 1].fecha_programada)" class="-ms-6 mb-2 ps-6 text-xs font-semibold uppercase tracking-widest text-oro-700">{{ mes(v.fecha_programada) }}</p>
        <span class="absolute -start-[7px] mt-1.5 size-3 rounded-full ring-4 ring-marfil" :class="estilo[v.situacion]?.punto"></span>
        <div class="rounded-xl bg-white p-4 shadow-sm" :class="v.situacion === 'CANCELADA' ? 'opacity-60' : ''">
          <div class="flex flex-wrap items-center justify-between gap-2">
            <span class="text-sm font-semibold text-marca-900">Visita #{{ v.nro_visita }} · {{ fecha(v.fecha_programada) }}</span>
            <span class="text-xs text-slate-500">{{ estilo[v.situacion]?.texto }}<span v-if="v.situacion !== 'REALIZADA' && v.dias !== null"> · {{ cuando(v.dias) }}</span></span>
          </div>
          <p class="text-xs text-slate-500">{{ v.edificio }}<span v-if="v.tecnico"> · técnico {{ v.tecnico }}</span></p>
          <p v-if="v.informe" class="mt-2 text-sm text-slate-600">{{ v.informe }}</p>
        </div>
      </li>
    </ol>
  </div>
</template>
