<script setup>
import { ref, onMounted } from 'vue'
import { portal, leerError } from '@/services'
import { bs, fecha } from '@/modulos/comun'

const contratos = ref([])
const edificios = ref([])
const error = ref('')

onMounted(async () => {
  try {
    ;[contratos.value, edificios.value] = await Promise.all([portal.contratos(), portal.equipos()])
  } catch (e) {
    error.value = leerError(e).mensaje
  }
})
const lugar = (u) => [u.zona, u.municipio, u.departamento, u.pais].filter(Boolean).join(', ')
</script>

<template>
  <div>
    <h1 class="font-display text-2xl font-semibold text-marca-900">Contratos y equipos</h1>
    <p v-if="error" class="mt-3 rounded bg-red-50 px-3 py-2 text-sm text-red-700">{{ error }}</p>

    <h2 class="mt-6 text-sm font-semibold uppercase tracking-wide text-slate-500">Contratos</h2>
    <p v-if="!contratos.length" class="mt-2 rounded-lg bg-white p-6 text-center text-slate-400 shadow-sm">No tienes contratos registrados.</p>
    <div class="mt-2 grid gap-4 md:grid-cols-2">
      <div v-for="c in contratos" :key="c.id" class="rounded-2xl border-t-4 border-oro-500 bg-white p-5 shadow-sm">
        <div class="flex items-center justify-between">
          <span class="font-semibold text-marca-900">{{ c.codigo }}</span>
          <span class="rounded-full px-2.5 py-0.5 text-xs font-medium" :class="c.vigente ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-600'">{{ c.vigente ? 'Vigente' : 'Finalizado' }}</span>
        </div>
        <p class="text-sm text-slate-600">{{ c.edificio }}</p>
        <p v-if="lugar(c.ubicacion)" class="text-xs text-slate-400">{{ lugar(c.ubicacion) }}</p>
        <dl class="mt-3 grid grid-cols-2 gap-2 text-sm">
          <div><dt class="text-xs text-slate-400">Vigencia</dt><dd>{{ fecha(c.fecha_inicio) }} – {{ fecha(c.fecha_fin) }}</dd></div>
          <div><dt class="text-xs text-slate-400">Monto</dt><dd>{{ bs(c.monto_total) }}</dd></div>
          <div><dt class="text-xs text-slate-400">Pagado / saldo</dt><dd>{{ bs(c.cobrado_bs) }} / {{ bs(c.saldo_bs) }}</dd></div>
          <div><dt class="text-xs text-slate-400">Visitas</dt><dd>{{ c.visitas_realizadas }} de {{ c.visitas_programadas }}</dd></div>
        </dl>
        <p v-if="c.proxima_visita" class="mt-3 text-xs text-oro-700">Próxima visita: {{ fecha(c.proxima_visita) }}</p>
      </div>
    </div>

    <h2 class="mt-8 text-sm font-semibold uppercase tracking-wide text-slate-500">Edificios y ascensores</h2>
    <p v-if="!edificios.length" class="mt-2 rounded-lg bg-white p-6 text-center text-slate-400 shadow-sm">Todavía no hay equipos registrados.</p>
    <div v-for="e in edificios" :key="e.id" class="mt-3 rounded-2xl bg-white p-5 shadow-sm">
      <div class="flex flex-wrap items-center justify-between gap-2">
        <div><span class="font-semibold text-marca-900">{{ e.denominacion }}</span> <span class="text-xs text-slate-400">{{ e.codigo }}</span></div>
        <span class="text-xs text-slate-500">Último mantenimiento: {{ e.ultimo_mantenimiento ? fecha(e.ultimo_mantenimiento) : 'sin registro' }}</span>
      </div>
      <p v-if="lugar(e.ubicacion)" class="text-xs text-slate-400">{{ lugar(e.ubicacion) }}</p>
      <ul class="mt-3 divide-y divide-slate-100 text-sm">
        <li v-for="q in e.equipos" :key="q.id" class="flex flex-wrap justify-between gap-2 py-2"><span>{{ q.descripcion }} <span class="text-xs text-slate-400">{{ q.codigo }}</span></span><span class="text-xs text-slate-500">{{ [q.marca, q.modelo, q.paradas ? `${q.paradas} paradas` : ''].filter(Boolean).join(' · ') }}</span></li>
        <li v-if="!e.equipos.length" class="py-2 text-slate-400">Sin equipos registrados.</li>
      </ul>
    </div>
  </div>
</template>
