<script setup>
import { ref, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { portal, leerError } from '@/services'
import { bs, fecha } from '@/modulos/comun'

const r = ref(null)
const error = ref('')

onMounted(async () => {
  try {
    r.value = await portal.resumen()
  } catch (e) {
    error.value = leerError(e).mensaje
  }
})
const cuando = (dias) => (dias === 0 ? 'hoy' : dias === 1 ? 'mañana' : `en ${dias} días`)
</script>

<template>
  <div>
    <p v-if="error" class="rounded bg-red-50 px-3 py-2 text-sm text-red-700">{{ error }}</p>
    <template v-if="r">
      <h1 class="font-display text-2xl font-semibold text-marca-900">Hola, {{ r.empresa.denominacion }}</h1>
      <p class="text-sm text-slate-500">NIT {{ r.empresa.nit }}</p>

      <!-- Avisos -->
      <div class="mt-5 space-y-3">
        <div v-if="r.cuotas_vencidas" class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
          Tienes <strong>{{ r.cuotas_vencidas }}</strong> cuota(s) vencida(s). <RouterLink :to="{ name: 'portal-pagos' }" class="font-semibold underline">Ir a pagos</RouterLink>
        </div>
        <div v-if="r.proxima_visita" class="rounded-lg border border-oro-300 bg-oro-50 px-4 py-3 text-sm text-oro-900">
          Próximo mantenimiento: <strong>{{ fecha(r.proxima_visita.fecha_programada) }}</strong> ({{ cuando(r.proxima_visita.dias) }}) en {{ r.proxima_visita.edificio }}.
        </div>
        <div v-if="r.solicitudes_abiertas" class="rounded-lg border border-marca-200 bg-marca-50 px-4 py-3 text-sm text-marca-800">
          Tienes {{ r.solicitudes_abiertas }} solicitud(es) de servicio abierta(s).
        </div>
      </div>

      <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-2xl border-l-4 border-marca-600 bg-white p-5 shadow-sm"><p class="text-xs font-bold uppercase text-slate-500">Contratos vigentes</p><p class="mt-1 text-3xl font-black text-marca-900">{{ r.contratos_vigentes }}</p></div>
        <div class="rounded-2xl border-l-4 border-oro-500 bg-white p-5 shadow-sm"><p class="text-xs font-bold uppercase text-slate-500">Edificios / equipos</p><p class="mt-1 text-3xl font-black text-marca-900">{{ r.edificios }} / {{ r.equipos }}</p></div>
        <div class="rounded-2xl border-l-4 border-emerald-600 bg-white p-5 shadow-sm"><p class="text-xs font-bold uppercase text-slate-500">Mantenimientos realizados</p><p class="mt-1 text-3xl font-black text-marca-900">{{ r.visitas_realizadas }}</p><p v-if="r.calificacion_promedio" class="text-xs text-slate-500">Tu nota promedio: {{ r.calificacion_promedio }} / 5</p></div>
        <div class="rounded-2xl border-l-4 border-rose-800 bg-white p-5 shadow-sm"><p class="text-xs font-bold uppercase text-slate-500">Saldo por pagar</p><p class="mt-1 text-2xl font-black text-marca-900">{{ bs(r.saldo_pendiente_bs) }}</p><p class="text-xs text-slate-500">{{ r.cuotas_pendientes }} cuota(s) · {{ r.pagos_en_verificacion }} pago(s) en verificación</p></div>
      </div>

      <div v-if="r.ultima_visita" class="mt-6 rounded-2xl bg-white p-5 shadow-sm">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Último mantenimiento</h2>
        <p class="mt-2 text-sm text-slate-700">
          <strong>{{ fecha(r.ultima_visita.fecha_realizada) }}</strong> · {{ r.ultima_visita.edificio }}
          <span v-if="r.ultima_visita.tecnico"> · técnico {{ r.ultima_visita.tecnico }}</span>
        </p>
        <p v-if="r.ultima_visita.informe" class="mt-1 text-sm text-slate-600">{{ r.ultima_visita.informe }}</p>
      </div>

      <div class="mt-6 flex flex-wrap gap-3">
        <RouterLink :to="{ name: 'portal-mantenimiento' }" class="rounded-lg bg-gradient-to-r from-oro-500 to-oro-400 px-5 py-3 text-xs font-bold uppercase tracking-widest text-marca-950 shadow">Reportar una falla</RouterLink>
        <RouterLink :to="{ name: 'portal-pagos' }" class="rounded-lg border border-marca-900 px-5 py-3 text-xs font-bold uppercase tracking-widest text-marca-900">Pagar por transferencia</RouterLink>
      </div>
    </template>
  </div>
</template>
