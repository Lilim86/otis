<script setup>
import { ref, computed, onMounted } from 'vue'
import { tecnico, leerError } from '@/services'
import { fecha } from '@/modulos/comun'

const visitas = ref([])
const verRealizadas = ref(false)
const cargando = ref(false)
const aviso = ref({ tipo: '', texto: '' })

async function cargar() {
  cargando.value = true
  try {
    visitas.value = await tecnico.ruta({ realizadas: verRealizadas.value ? 1 : 0 })
  } catch (e) {
    aviso.value = { tipo: 'error', texto: leerError(e).mensaje }
  } finally {
    cargando.value = false
  }
}
onMounted(cargar)

// Agrupa por día: atrasadas, hoy y lo que viene.
const grupos = computed(() => {
  const mapa = new Map()
  for (const v of visitas.value) {
    const clave = v.situacion === 'REALIZADA' ? 'Realizadas' : v.dias < 0 ? 'Atrasadas' : v.dias === 0 ? 'Hoy' : v.fecha_programada
    if (!mapa.has(clave)) mapa.set(clave, [])
    mapa.get(clave).push(v)
  }
  const orden = ['Atrasadas', 'Hoy']
  return [...mapa.entries()].sort(([a], [b]) => (orden.indexOf(a) + 1 || 99) - (orden.indexOf(b) + 1 || 99) || a.localeCompare(b))
})
const titulo = (clave) => (['Atrasadas', 'Hoy', 'Realizadas'].includes(clave) ? clave : fecha(clave))

const mapa = (v) => (v.latitud && v.longitud ? `https://www.google.com/maps?q=${v.latitud},${v.longitud}` : `https://www.google.com/maps/search/${encodeURIComponent(`${v.edificio} ${v.direccion}`)}`)

// ---- cerrar una visita -----------------------------------------------------------
const cerrando = ref(null)
const informe = ref('')
const error = ref('')
const enviando = ref(false)

function abrir(v) {
  cerrando.value = v
  informe.value = v.informe ?? ''
  error.value = ''
}
async function completar() {
  if (informe.value.trim().length < 10) {
    error.value = 'Describe el trabajo realizado (mínimo 10 caracteres).'
    return
  }
  enviando.value = true
  try {
    await tecnico.completar(cerrando.value.id, informe.value.trim())
    cerrando.value = null
    aviso.value = { tipo: 'ok', texto: 'Visita cerrada. El cliente ya puede ver tu informe y firmar su conformidad.' }
    await cargar()
  } catch (e) {
    const { mensaje, campos } = leerError(e)
    error.value = campos.informe ?? mensaje
  } finally {
    enviando.value = false
  }
}
</script>

<template>
  <div>
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h1 class="text-xl font-bold text-marca-900">Mi ruta</h1>
        <p class="text-sm text-slate-500">Tus visitas de mantenimiento: lo atrasado, lo de hoy y los próximos 14 días.</p>
      </div>
      <label class="flex items-center gap-2 text-sm text-slate-600"><input v-model="verRealizadas" type="checkbox" @change="cargar" /> Ver también las realizadas del último mes</label>
    </div>

    <p v-if="aviso.texto" class="mt-3 rounded px-3 py-2 text-sm" :class="aviso.tipo === 'ok' ? 'bg-emerald-50 text-emerald-800' : 'bg-red-50 text-red-700'">{{ aviso.texto }}</p>
    <p v-if="cargando" class="mt-6 text-slate-400">Cargando…</p>
    <p v-else-if="!visitas.length" class="mt-6 rounded-lg bg-white p-8 text-center text-slate-400 shadow-sm">No tienes visitas pendientes. ¡Buen trabajo!</p>

    <section v-for="[clave, lista] in grupos" :key="clave" class="mt-6">
      <h2 class="text-xs font-semibold uppercase tracking-[0.25em]" :class="clave === 'Atrasadas' ? 'text-rose-700' : 'text-oro-700'">{{ titulo(clave) }}</h2>
      <ul class="mt-2 space-y-3">
        <li v-for="v in lista" :key="v.id" class="rounded-xl border-l-4 bg-white p-4 shadow-sm" :class="v.situacion === 'REALIZADA' ? 'border-emerald-500' : v.dias < 0 ? 'border-rose-700' : 'border-oro-500'">
          <div class="flex flex-wrap items-start justify-between gap-2">
            <div>
              <p class="font-semibold text-marca-900">{{ v.edificio }}</p>
              <p class="text-xs text-slate-500">{{ v.cliente }} · contrato {{ v.contrato }} · {{ v.tipo === 'PREVENTIVO' ? `preventivo #${v.nro_visita}` : 'correctivo' }}</p>
            </div>
            <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs text-slate-600">{{ fecha(v.fecha_programada) }}</span>
          </div>
          <p v-if="v.direccion" class="mt-2 text-sm text-slate-600">
            📍 {{ v.direccion }}<span v-if="v.referencia"> · {{ v.referencia }}</span>
            <a :href="mapa(v)" target="_blank" rel="noopener" class="ml-2 text-marca-600 underline hover:text-oro-600">Ver en el mapa</a>
          </p>
          <p v-if="v.contacto" class="text-sm text-slate-600">☎ {{ v.contacto.nombre }}<span v-if="v.contacto.telefono"> · <a :href="`tel:${v.contacto.telefono}`" class="text-marca-600 underline">{{ v.contacto.telefono }}</a></span></p>
          <p v-if="v.descripcion" class="mt-2 rounded bg-slate-50 p-2 text-sm text-slate-700">{{ v.descripcion }}</p>
          <ul v-if="v.equipos.length" class="mt-2 text-xs text-slate-500">
            <li v-for="e in v.equipos" :key="e.codigo">🛗 {{ e.descripcion }} <span class="text-slate-400">{{ e.codigo }} {{ e.detalle }}</span></li>
          </ul>
          <button v-if="v.situacion !== 'REALIZADA'" class="mt-3 rounded bg-marca-900 px-4 py-2 text-sm font-medium text-white hover:bg-marca-700" @click="abrir(v)">Cerrar visita con informe</button>
          <p v-else-if="v.informe" class="mt-2 text-sm text-emerald-800">✔ {{ v.informe }}</p>
        </li>
      </ul>
    </section>

    <div v-if="cerrando" class="fixed inset-0 z-20 flex items-start justify-center overflow-y-auto bg-black/50 p-4" @mousedown.self="cerrando = null">
      <form class="my-8 w-full max-w-lg rounded-xl bg-white p-6 shadow-xl" novalidate @submit.prevent="completar">
        <h2 class="text-lg font-bold text-marca-900">Informe de la visita</h2>
        <p class="text-sm text-slate-500">{{ cerrando.edificio }} · {{ fecha(cerrando.fecha_programada) }}</p>
        <label class="mt-4 block text-sm"><span class="text-slate-600">Trabajo realizado y novedades <span class="text-red-600">*</span></span>
          <textarea v-model="informe" rows="6" placeholder="Ej: Se lubricaron guías y cables, se revisó el freno y el sensor de puertas. Sin novedades." class="mt-1 w-full rounded border px-3 py-2 focus:outline-none" :class="error ? 'border-rose-400' : 'border-slate-300 focus:border-oro-500'"></textarea>
          <span v-if="error" class="text-xs text-red-600">{{ error }}</span>
        </label>
        <div class="mt-5 flex justify-end gap-3">
          <button type="button" class="rounded border border-slate-300 px-4 py-2 text-sm" @click="cerrando = null">Cancelar</button>
          <button :disabled="enviando" class="rounded bg-gradient-to-r from-oro-500 to-oro-400 px-5 py-2 text-sm font-bold text-marca-950 disabled:opacity-60">{{ enviando ? 'Guardando…' : 'Marcar como realizada' }}</button>
        </div>
      </form>
    </div>
  </div>
</template>
