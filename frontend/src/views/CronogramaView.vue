<script setup>
import { ref, computed, onMounted } from 'vue'
import { visitasMantenimiento, leerError } from '@/services'
import { fecha } from '@/modulos/comun'
import { useCatalogosStore } from '@/stores/catalogos'
import { useSesionStore } from '@/stores/sesion'

const catalogos = useCatalogosStore()
const sesion = useSesionStore()
const hoy = new Date().toISOString().slice(0, 10)
const sumarDias = (dias) => new Date(Date.now() + dias * 86400000).toISOString().slice(0, 10)

const desde = ref(sumarDias(-7))
const hasta = ref(sumarDias(30))
const tablero = ref(null)
const filtroTecnico = ref('')
const cargando = ref(false)
const aviso = ref({ tipo: '', texto: '' })

async function cargar() {
  cargando.value = true
  try {
    tablero.value = await visitasMantenimiento.tablero({ desde: desde.value, hasta: hasta.value })
  } catch (e) {
    aviso.value = { tipo: 'error', texto: leerError(e).mensaje }
  } finally {
    cargando.value = false
  }
}
onMounted(() => {
  catalogos.cargar?.()
  cargar()
})

const estados = {
  EN_SITIO: { texto: 'En sitio ahora', clase: 'bg-emerald-100 text-emerald-800', punto: 'bg-emerald-500 animate-pulse' },
  CON_RUTA_HOY: { texto: 'Con ruta hoy', clase: 'bg-oro-100 text-oro-800', punto: 'bg-oro-500' },
  LIBRE: { texto: 'Libre hoy', clase: 'bg-slate-100 text-slate-600', punto: 'bg-slate-400' },
}
const colorSituacion = {
  PROGRAMADA: 'bg-slate-100 text-slate-700',
  REPROGRAMADA: 'bg-amber-100 text-amber-800',
  SOLICITADA: 'bg-sky-100 text-sky-800',
  EN_CURSO: 'bg-emerald-100 text-emerald-800',
  REALIZADA: 'bg-emerald-50 text-emerald-700',
}
const textoSituacion = (c) => (catalogos.datos.situaciones_visita ?? []).find((o) => o.codigo === c)?.etiqueta ?? c
const desdeHora = (iso) => (iso ? new Date(iso).toLocaleTimeString('es-BO', { hour: '2-digit', minute: '2-digit' }) : '')

const visitas = computed(() => (tablero.value?.visitas ?? []).filter((v) => !filtroTecnico.value || String(v.tecnico_id ?? 'ninguno') === filtroTecnico.value))
const porDia = computed(() => {
  const mapa = new Map()
  for (const v of visitas.value) {
    if (!mapa.has(v.fecha)) mapa.set(v.fecha, [])
    mapa.get(v.fecha).push(v)
  }
  return [...mapa.entries()]
})
const etiquetaDia = (f) => (f === hoy ? `Hoy · ${fecha(f)}` : f < hoy ? `${fecha(f)} (pasado)` : fecha(f))

async function asignar(visita, tecnicoId) {
  try {
    await visitasMantenimiento.actualizar(visita.id, { tecnico_id: tecnicoId || null })
    aviso.value = { tipo: 'ok', texto: 'Técnico asignado.' }
    await cargar()
  } catch (e) {
    const { mensaje, campos } = leerError(e)
    aviso.value = { tipo: 'error', texto: campos.fecha_programada ?? campos.tecnico_id ?? mensaje }
    await cargar()
  }
}
async function repartir() {
  try {
    const r = await visitasMantenimiento.autoasignar({ desde: hoy, hasta: hasta.value })
    aviso.value = {
      tipo: r.asignadas ? 'ok' : 'error',
      texto: r.asignadas
        ? `${r.asignadas} visita(s) asignada(s) a los técnicos con menos carga.${r.sin_cupo ? ` ${r.sin_cupo} quedaron sin cupo (máximo diario alcanzado).` : ''}`
        : 'No había visitas por asignar, o los técnicos ya llegaron a su máximo por día.',
    }
    await cargar()
  } catch (e) {
    aviso.value = { tipo: 'error', texto: leerError(e).mensaje }
  }
}
const puedeAsignar = computed(() => sesion.puede('visitas-mantenimiento.editar'))
</script>

<template>
  <div>
    <div class="flex flex-wrap items-end justify-between gap-3">
      <div>
        <h1 class="text-xl font-bold text-marca-900">Cronograma y técnicos</h1>
        <p class="text-sm text-slate-500">Qué técnico está en cada visita, cuánta carga tiene y qué falta asignar.</p>
      </div>
      <div class="flex flex-wrap items-end gap-2 text-xs text-slate-500">
        <label>Desde <input v-model="desde" type="date" class="mt-1 block rounded border border-slate-300 px-2 py-1.5 text-sm text-slate-800" @change="cargar" /></label>
        <label>Hasta <input v-model="hasta" type="date" class="mt-1 block rounded border border-slate-300 px-2 py-1.5 text-sm text-slate-800" @change="cargar" /></label>
        <button v-if="puedeAsignar" class="rounded bg-gradient-to-r from-oro-500 to-oro-400 px-4 py-2 text-sm font-bold text-marca-950 hover:brightness-105" @click="repartir">Repartir visitas sin técnico</button>
      </div>
    </div>

    <p v-if="aviso.texto" class="mt-3 rounded px-3 py-2 text-sm" :class="aviso.tipo === 'ok' ? 'bg-emerald-50 text-emerald-800' : 'bg-red-50 text-red-700'">{{ aviso.texto }}</p>
    <p v-if="cargando && !tablero" class="mt-6 text-slate-400">Cargando…</p>

    <template v-if="tablero">
      <div class="mt-4 grid grid-cols-2 gap-3 lg:grid-cols-5">
        <div class="rounded-lg bg-white p-3 shadow-sm"><p class="text-xs text-slate-500">Visitas en el rango</p><p class="text-2xl font-bold text-marca-900">{{ tablero.resumen.visitas }}</p></div>
        <div class="rounded-lg bg-white p-3 shadow-sm"><p class="text-xs text-slate-500">Realizadas</p><p class="text-2xl font-bold text-emerald-600">{{ tablero.resumen.realizadas }}</p></div>
        <div class="rounded-lg bg-white p-3 shadow-sm"><p class="text-xs text-slate-500">En curso ahora</p><p class="text-2xl font-bold text-emerald-600">{{ tablero.resumen.en_curso }}</p></div>
        <div class="rounded-lg bg-white p-3 shadow-sm"><p class="text-xs text-slate-500">Sin técnico</p><p class="text-2xl font-bold" :class="tablero.resumen.sin_tecnico ? 'text-amber-600' : 'text-emerald-600'">{{ tablero.resumen.sin_tecnico }}</p></div>
        <div class="rounded-lg bg-white p-3 shadow-sm"><p class="text-xs text-slate-500">Atrasadas</p><p class="text-2xl font-bold" :class="tablero.resumen.atrasadas ? 'text-rose-700' : 'text-emerald-600'">{{ tablero.resumen.atrasadas }}</p></div>
      </div>

      <h2 class="mt-6 text-xs font-semibold uppercase tracking-[0.25em] text-oro-700">Técnicos</h2>
      <p v-if="!tablero.tecnicos.length" class="mt-2 rounded-lg bg-white p-4 text-sm text-slate-500 shadow-sm">Aún no hay técnicos: crea un usuario y vincúlalo a un registro de Personal.</p>
      <div class="mt-2 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
        <button
          v-for="t in tablero.tecnicos"
          :key="t.id"
          class="rounded-xl border bg-white p-4 text-left shadow-sm transition hover:border-oro-400"
          :class="String(t.id) === filtroTecnico ? 'border-oro-500 ring-1 ring-oro-300' : 'border-transparent'"
          @click="filtroTecnico = String(t.id) === filtroTecnico ? '' : String(t.id)"
        >
          <div class="flex items-start justify-between gap-2">
            <p class="font-semibold text-marca-900">{{ t.nombre }}</p>
            <span class="flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium" :class="estados[t.estado].clase">
              <span class="h-2 w-2 rounded-full" :class="estados[t.estado].punto"></span>{{ estados[t.estado].texto }}
            </span>
          </div>
          <p v-if="t.en_curso" class="mt-2 rounded bg-emerald-50 px-2 py-1 text-sm text-emerald-900">
            🛗 {{ t.en_curso.edificio }} <span class="text-emerald-700">· {{ t.en_curso.cliente }}</span>
            <span v-if="t.en_curso.iniciada_en" class="text-xs text-emerald-700"> · desde las {{ desdeHora(t.en_curso.iniciada_en) }}</span>
          </p>
          <div class="mt-3 grid grid-cols-4 gap-2 text-center text-xs text-slate-500">
            <div><p class="text-lg font-bold text-marca-900">{{ t.hoy }}</p>hoy</div>
            <div><p class="text-lg font-bold text-marca-900">{{ t.pendientes }}</p>pendientes</div>
            <div><p class="text-lg font-bold" :class="t.atrasadas ? 'text-rose-700' : 'text-marca-900'">{{ t.atrasadas }}</p>atrasadas</div>
            <div><p class="text-lg font-bold text-emerald-600">{{ t.realizadas }}</p>hechas</div>
          </div>
        </button>
      </div>

      <template v-if="tablero.sin_tecnico.length">
        <h2 class="mt-6 text-xs font-semibold uppercase tracking-[0.25em] text-amber-700">Sin técnico asignado ({{ tablero.sin_tecnico.length }})</h2>
        <ul class="mt-2 divide-y divide-slate-100 rounded-lg bg-white shadow-sm">
          <li v-for="v in tablero.sin_tecnico" :key="v.id" class="flex flex-wrap items-center justify-between gap-2 px-4 py-2 text-sm">
            <span><b class="text-marca-900">{{ fecha(v.fecha) }}</b> · {{ v.edificio }} <span class="text-slate-500">· {{ v.cliente }}</span></span>
            <select v-if="puedeAsignar" class="rounded border border-slate-300 px-2 py-1 text-sm" @change="asignar(v, $event.target.value)">
              <option value="">Asignar a…</option>
              <option v-for="t in tablero.tecnicos" :key="t.id" :value="t.id">{{ t.nombre }}</option>
            </select>
          </li>
        </ul>
      </template>

      <div class="mt-6 flex flex-wrap items-center justify-between gap-2">
        <h2 class="text-xs font-semibold uppercase tracking-[0.25em] text-oro-700">Agenda del {{ fecha(tablero.desde) }} al {{ fecha(tablero.hasta) }}</h2>
        <select v-model="filtroTecnico" class="rounded border border-slate-300 bg-white px-2 py-1.5 text-sm">
          <option value="">Todos los técnicos</option>
          <option value="ninguno">Sin técnico</option>
          <option v-for="t in tablero.tecnicos" :key="t.id" :value="String(t.id)">{{ t.nombre }}</option>
        </select>
      </div>
      <p v-if="!porDia.length" class="mt-2 rounded-lg bg-white p-6 text-center text-slate-400 shadow-sm">No hay visitas en este rango.</p>
      <section v-for="[dia, lista] in porDia" :key="dia" class="mt-3">
        <h3 class="text-sm font-semibold" :class="dia === hoy ? 'text-oro-700' : 'text-marca-900'">{{ etiquetaDia(dia) }}</h3>
        <ul class="mt-1 divide-y divide-slate-100 rounded-lg bg-white shadow-sm">
          <li v-for="v in lista" :key="v.id" class="flex flex-wrap items-center justify-between gap-2 px-4 py-2 text-sm">
            <span>{{ v.edificio }} <span class="text-slate-500">· {{ v.cliente }} · {{ v.tipo === 'PREVENTIVO' ? 'preventivo' : 'correctivo' }}</span></span>
            <span class="flex items-center gap-2">
              <span class="text-slate-600">{{ v.tecnico ?? 'Sin técnico' }}</span>
              <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="colorSituacion[v.situacion]">{{ textoSituacion(v.situacion) }}</span>
            </span>
          </li>
        </ul>
      </section>
    </template>
  </div>
</template>
