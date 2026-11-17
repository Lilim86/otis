<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { ingenieria, leerError } from '@/services'
import { useSesionStore } from '@/stores/sesion'
import { bs, fecha } from '@/modulos/comun'

const sesion = useSesionStore()
const puedeAplicar = computed(() => sesion.puede('ingenieria.crear'))
const pestanas = { impacto: 'Impacto', rutas: 'Rutas óptimas', asignacion: 'Asignación', confiabilidad: 'Confiabilidad', servicio: 'Nivel de servicio', fallas: 'Clasificador de fallas' }
const pestana = ref('impacto')
const error = ref('')
const aviso = ref('')
const cargando = ref(false)
const hoy = new Date().toISOString().slice(0, 10)
const num = (v, d = 1) => (v == null ? '—' : Number(v).toLocaleString('es-BO', { maximumFractionDigits: d }))

async function intentar(accion) {
  error.value = ''
  cargando.value = true
  try {
    return await accion()
  } catch (e) {
    error.value = leerError(e).mensaje
  } finally {
    cargando.value = false
  }
}

// ---- impacto ------------------------------------------------------------------
const resumen = ref(null)
const cargarResumen = () => intentar(async () => (resumen.value = await ingenieria.resumen()))

// ---- rutas --------------------------------------------------------------------
const fechaRuta = ref(hoy)
const plan = ref(null)
const cargarRutas = () => intentar(async () => (plan.value = await ingenieria.rutas(fechaRuta.value)))
async function aplicarRutas() {
  const r = await intentar(() => ingenieria.aplicarRutas(fechaRuta.value))
  if (r) {
    aviso.value = r.message
    await cargarRutas()
  }
}
const COLORES = ['#0D1638', '#C99A2E', '#0F766E', '#B91C1C', '#7C3AED', '#2563EB']

/** Proyección simple lat/lon → SVG (en La Paz la distorsión es despreciable). */
const mapa = computed(() => {
  if (!plan.value) return null
  const puntos = [plan.value.base, ...plan.value.rutas.flatMap((r) => r.actual.map((p) => p.punto))].filter(Boolean)
  const lats = puntos.map((p) => p[0])
  const lons = puntos.map((p) => p[1])
  const [minLa, maxLa, minLo, maxLo] = [Math.min(...lats), Math.max(...lats), Math.min(...lons), Math.max(...lons)]
  const W = 360
  const H = 300
  const m = 18
  const esc = Math.min((W - 2 * m) / (maxLo - minLo || 1), (H - 2 * m) / (maxLa - minLa || 1))
  const xy = (p) => [m + (p[1] - minLo) * esc, m + (maxLa - p[0]) * esc]
  const linea = (paradas) => [plan.value.base, ...paradas.map((p) => p.punto), plan.value.base].map((p) => xy(p).join(',')).join(' ')
  return { W, H, base: xy(plan.value.base), xy, linea }
})

// ---- asignación ---------------------------------------------------------------
const comparacion = ref(null)
const cargarAsignacion = () => intentar(async () => (comparacion.value = await ingenieria.asignacion()))
async function asignar() {
  if (!window.confirm('¿Asignar las visitas sin técnico de los próximos 14 días según cercanía?')) return
  const r = await intentar(() => ingenieria.asignar())
  if (r) {
    aviso.value = r.message
    await cargarAsignacion()
  }
}

// ---- confiabilidad ------------------------------------------------------------
const confiabilidad = ref(null)
const cargarConfiabilidad = () => intentar(async () => (confiabilidad.value = await ingenieria.confiabilidad()))
async function adelantar(e) {
  const r = await intentar(() => ingenieria.adelantar(e.edificio_id))
  if (r) {
    aviso.value = `${e.edificio}: ${r.message}`
    await cargarConfiabilidad()
  }
}
const colorRiesgo = { ALTO: 'bg-rose-100 text-rose-800', MEDIO: 'bg-amber-100 text-amber-800', BAJO: 'bg-emerald-100 text-emerald-800' }

// ---- nivel de servicio --------------------------------------------------------
const periodo = ref({ desde: new Date(Date.now() - 365 * 864e5).toISOString().slice(0, 10), hasta: hoy })
const servicio = ref(null)
const cargarServicio = () => intentar(async () => (servicio.value = await ingenieria.servicio(periodo.value)))

// ---- clasificador -------------------------------------------------------------
const prueba = ref({ descripcion: 'Hay una persona atrapada, el ascensor se detuvo entre pisos', urgente: false })
const clase = ref(null)
const clasificar = () => intentar(async () => (clase.value = await ingenieria.clasificarFalla(prueba.value.descripcion, prueba.value.urgente)))
const colorPrioridad = { P1: 'bg-rose-600 text-white', P2: 'bg-orange-500 text-white', P3: 'bg-amber-300 text-amber-950', P4: 'bg-slate-200 text-slate-700' }

const cargas = { impacto: cargarResumen, rutas: cargarRutas, asignacion: cargarAsignacion, confiabilidad: cargarConfiabilidad, servicio: cargarServicio, fallas: clasificar }
watch(pestana, (p) => {
  aviso.value = ''
  cargas[p]()
})
onMounted(cargarResumen)
</script>

<template>
  <div>
    <h1 class="text-xl font-bold text-marca-900">Ingeniería: optimización y confiabilidad</h1>
    <p class="mt-1 text-sm text-slate-500">Rutas por algoritmo (vecino más cercano + 2-opt), asignación por cercanía, confiabilidad (MTBF, MTTR, P = 1 − e<sup>−λt</sup>) y nivel de servicio. Cada aporte se mide contra la forma de trabajo anterior.</p>

    <nav class="mt-4 flex flex-wrap gap-1 border-b border-slate-200">
      <button v-for="(t, k) in pestanas" :key="k" class="rounded-t px-4 py-2 text-sm" :class="pestana === k ? 'border-b-2 border-oro-500 font-semibold text-marca-900' : 'text-slate-500 hover:text-marca-900'" @click="pestana = k">{{ t }}</button>
    </nav>

    <p v-if="error" class="mt-3 rounded bg-red-50 px-3 py-2 text-sm text-red-700">{{ error }}</p>
    <p v-if="aviso" class="mt-3 rounded bg-emerald-50 px-3 py-2 text-sm text-emerald-800">{{ aviso }}</p>
    <p v-if="cargando" class="mt-3 text-sm text-slate-400">Calculando…</p>

    <!-- IMPACTO -->
    <section v-if="pestana === 'impacto' && resumen" class="mt-5 space-y-6">
      <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-lg bg-white p-4 shadow-sm">
          <p class="text-xs uppercase tracking-wide text-slate-500">Rutas de hoy</p>
          <p class="mt-1 text-3xl font-bold text-emerald-700">−{{ num(resumen.rutas_hoy.ahorro_km) }} km</p>
          <p class="text-xs text-slate-500">{{ num(resumen.rutas_hoy.km_antes) }} → {{ num(resumen.rutas_hoy.km_despues) }} km · {{ num(resumen.rutas_hoy.ahorro_minutos / 60) }} h menos · {{ bs(resumen.rutas_hoy.ahorro_bs) }}</p>
        </div>
        <div class="rounded-lg bg-white p-4 shadow-sm">
          <p class="text-xs uppercase tracking-wide text-slate-500">Ahorro acumulado (rutas aplicadas)</p>
          <p class="mt-1 text-3xl font-bold text-marca-900">{{ num(resumen.rutas_acumulado.ahorro_km) }} km</p>
          <p class="text-xs text-slate-500">{{ resumen.rutas_acumulado.rutas_aplicadas }} ruta(s) · {{ num(resumen.rutas_acumulado.ahorro_pct) }}% · {{ bs(resumen.rutas_acumulado.ahorro_bs) }} · {{ num(resumen.rutas_acumulado.ahorro_horas) }} h</p>
        </div>
        <div class="rounded-lg bg-white p-4 shadow-sm">
          <p class="text-xs uppercase tracking-wide text-slate-500">Disponibilidad de equipos</p>
          <p class="mt-1 text-3xl font-bold text-marca-900">{{ num(resumen.confiabilidad.disponibilidad_promedio, 2) }}%</p>
          <p class="text-xs text-slate-500">MTBF {{ num(resumen.confiabilidad.mtbf_promedio_dias) }} d · MTTR {{ num(resumen.confiabilidad.mttr_promedio_horas) }} h · {{ resumen.confiabilidad.alto }} edificio(s) en riesgo alto</p>
        </div>
        <div class="rounded-lg bg-white p-4 shadow-sm">
          <p class="text-xs uppercase tracking-wide text-slate-500">Cumplimiento preventivo</p>
          <p class="mt-1 text-3xl font-bold text-marca-900">{{ num(resumen.servicio.preventivas.cumplimiento_pct) }}%</p>
          <p class="text-xs text-slate-500">{{ resumen.servicio.preventivas.a_tiempo }} de {{ resumen.servicio.preventivas.evaluadas }} a tiempo (últimos 90 días)</p>
        </div>
      </div>

      <div class="rounded-lg bg-white p-5 shadow-sm">
        <h2 class="font-semibold text-marca-900">Antes y después</h2>
        <table class="mt-3 min-w-full text-left text-sm">
          <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-3 py-2">Indicador</th><th class="px-3 py-2 text-right">Antes</th><th class="px-3 py-2 text-right">Con el aporte</th><th class="px-3 py-2 text-right">Mejora</th></tr></thead>
          <tbody class="divide-y divide-slate-100">
            <tr>
              <td class="px-3 py-2">Km recorridos hoy (orden de carga vs. ruta óptima)</td>
              <td class="px-3 py-2 text-right">{{ num(resumen.rutas_hoy.km_antes) }}</td><td class="px-3 py-2 text-right">{{ num(resumen.rutas_hoy.km_despues) }}</td>
              <td class="px-3 py-2 text-right font-semibold text-emerald-700">{{ resumen.rutas_hoy.km_antes ? num((resumen.rutas_hoy.ahorro_km / resumen.rutas_hoy.km_antes) * 100) : '—' }}%</td>
            </tr>
            <tr>
              <td class="px-3 py-2">Km de las próximas 2 semanas (asignar por carga vs. por cercanía)</td>
              <td class="px-3 py-2 text-right">{{ num(resumen.asignacion.por_carga.km) }}</td><td class="px-3 py-2 text-right">{{ num(resumen.asignacion.por_cercania.km) }}</td>
              <td class="px-3 py-2 text-right font-semibold text-emerald-700">{{ num(resumen.asignacion.ahorro_pct) }}%</td>
            </tr>
            <tr>
              <td class="px-3 py-2">Respuesta a fallas (horas): por orden de llegada vs. clasificadas</td>
              <td class="px-3 py-2 text-right">{{ num(resumen.servicio.correctivas.sin_clasificar.respuesta_promedio_horas) }}</td>
              <td class="px-3 py-2 text-right">{{ num(resumen.servicio.correctivas.por_prioridad.find((p) => p.prioridad === 'P1')?.respuesta_promedio_horas) }} (P1) · {{ num(resumen.servicio.correctivas.por_prioridad.find((p) => p.prioridad === 'P2')?.respuesta_promedio_horas) }} (P2)</td>
              <td class="px-3 py-2 text-right text-xs text-slate-500">ver «Nivel de servicio»</td>
            </tr>
          </tbody>
        </table>
        <p class="mt-3 text-xs text-slate-400">Parámetros: factor de tortuosidad de calles 1.35, velocidad media 18 km/h, 75 min por visita, Bs 2.80 por km (config/sofia.php → ingenieria).</p>
      </div>
    </section>

    <!-- RUTAS -->
    <section v-if="pestana === 'rutas'" class="mt-5 space-y-4">
      <div class="flex flex-wrap items-end gap-3">
        <label class="text-xs text-slate-500">Fecha<input v-model="fechaRuta" type="date" class="mt-1 block rounded border border-slate-300 px-3 py-2 text-sm text-slate-800" @change="cargarRutas" /></label>
        <button v-if="puedeAplicar && plan?.rutas.length" class="rounded bg-marca-900 px-4 py-2 text-sm font-medium text-white hover:bg-marca-700" @click="aplicarRutas">Aplicar rutas óptimas</button>
        <p v-if="plan" class="text-sm text-slate-600">Total: <strong>{{ num(plan.total.km_antes) }} → {{ num(plan.total.km_despues) }} km</strong> · ahorro {{ num(plan.total.ahorro_km) }} km ({{ bs(plan.total.ahorro_bs) }}) · {{ num(plan.total.ahorro_minutos) }} min</p>
      </div>
      <p v-if="plan && !plan.rutas.length" class="rounded bg-white p-6 text-center text-slate-400 shadow-sm">No hay visitas con técnico asignado en esa fecha.</p>

      <div v-if="plan?.rutas.length && mapa" class="grid gap-4 lg:grid-cols-2">
        <div v-for="(titulo, clave) in { actual: 'Antes: orden en que se cargaron', optima: 'Después: ruta óptima' }" :key="clave" class="rounded-lg bg-white p-4 shadow-sm">
          <h3 class="text-sm font-semibold text-marca-900">{{ titulo }}</h3>
          <svg :viewBox="`0 0 ${mapa.W} ${mapa.H}`" class="mt-2 w-full rounded bg-slate-50">
            <polyline v-for="(r, i) in plan.rutas" :key="r.tecnico_id" :points="mapa.linea(r[clave])" fill="none" :stroke="COLORES[i % COLORES.length]" stroke-width="2" stroke-linejoin="round" opacity="0.85" />
            <template v-for="(r, i) in plan.rutas" :key="`p${r.tecnico_id}`">
              <g v-for="(p, j) in r[clave]" :key="p.id">
                <circle :cx="mapa.xy(p.punto)[0]" :cy="mapa.xy(p.punto)[1]" r="7" :fill="COLORES[i % COLORES.length]" />
                <text :x="mapa.xy(p.punto)[0]" :y="mapa.xy(p.punto)[1] + 3" text-anchor="middle" font-size="8" fill="#fff" font-weight="bold">{{ j + 1 }}</text>
              </g>
            </template>
            <rect :x="mapa.base[0] - 6" :y="mapa.base[1] - 6" width="12" height="12" fill="#C99A2E" stroke="#0D1638" />
          </svg>
          <p class="mt-1 text-xs text-slate-400">■ Oficina central · los números son el orden de visita</p>
        </div>
      </div>

      <div v-for="(r, i) in plan?.rutas ?? []" :key="r.tecnico_id" class="rounded-lg bg-white p-4 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-2">
          <h3 class="font-semibold" :style="{ color: COLORES[i % COLORES.length] }">{{ r.tecnico }} · {{ r.visitas }} visita(s)</h3>
          <span class="text-sm">{{ num(r.km_antes) }} → <strong>{{ num(r.km_despues) }} km</strong> <span class="ml-1 rounded-full bg-emerald-100 px-2 py-0.5 text-xs text-emerald-800">−{{ num(r.ahorro_pct) }}%</span> · {{ num(r.minutos_antes / 60) }} → {{ num(r.minutos_despues / 60) }} h de jornada</span>
        </div>
        <ol class="mt-2 grid gap-1 text-sm sm:grid-cols-2">
          <li v-for="(p, j) in r.optima" :key="p.id" class="flex gap-2"><span class="w-5 text-right font-bold text-slate-400">{{ j + 1 }}.</span> {{ p.edificio }} <span class="text-xs text-slate-400">{{ p.cliente }}</span></li>
        </ol>
        <p v-if="r.sin_ubicacion" class="mt-1 text-xs text-amber-700">{{ r.sin_ubicacion }} visita(s) sin coordenadas en el edificio: no entran en el cálculo.</p>
      </div>
    </section>

    <!-- ASIGNACIÓN -->
    <section v-if="pestana === 'asignacion' && comparacion" class="mt-5 space-y-4">
      <p class="text-sm text-slate-600">Visitas sin técnico del {{ fecha(comparacion.desde) }} al {{ fecha(comparacion.hasta) }}: <strong>{{ comparacion.visitas_sin_tecnico }}</strong> · {{ comparacion.tecnicos }} técnico(s). Se simulan las dos formas de repartirlas y se miden los km con la ruta óptima de cada día.</p>
      <div class="grid gap-4 sm:grid-cols-3">
        <div class="rounded-lg bg-white p-4 shadow-sm"><p class="text-xs uppercase text-slate-500">Por carga (método anterior)</p><p class="mt-1 text-3xl font-bold text-slate-700">{{ num(comparacion.por_carga.km) }} km</p><p class="text-xs text-slate-500">al técnico con menos visitas</p></div>
        <div class="rounded-lg bg-white p-4 shadow-sm"><p class="text-xs uppercase text-slate-500">Por cercanía (costo de inserción)</p><p class="mt-1 text-3xl font-bold text-marca-900">{{ num(comparacion.por_cercania.km) }} km</p><p class="text-xs text-slate-500">al técnico al que menos le alarga la ruta</p></div>
        <div class="rounded-lg bg-white p-4 shadow-sm"><p class="text-xs uppercase text-slate-500">Ahorro</p><p class="mt-1 text-3xl font-bold text-emerald-700">{{ num(comparacion.ahorro_km) }} km</p><p class="text-xs text-slate-500">{{ num(comparacion.ahorro_pct) }}% · {{ bs(comparacion.ahorro_bs) }}</p></div>
      </div>
      <button v-if="puedeAplicar && comparacion.visitas_sin_tecnico" class="rounded bg-marca-900 px-4 py-2 text-sm font-medium text-white hover:bg-marca-700" @click="asignar">Asignar por cercanía</button>
    </section>

    <!-- CONFIABILIDAD -->
    <section v-if="pestana === 'confiabilidad' && confiabilidad" class="mt-5 space-y-4">
      <div class="grid gap-4 sm:grid-cols-4">
        <div class="rounded-lg bg-white p-4 shadow-sm"><p class="text-xs uppercase text-slate-500">MTBF promedio</p><p class="mt-1 text-2xl font-bold text-marca-900">{{ num(confiabilidad.resumen.mtbf_promedio_dias) }} días</p></div>
        <div class="rounded-lg bg-white p-4 shadow-sm"><p class="text-xs uppercase text-slate-500">MTTR promedio</p><p class="mt-1 text-2xl font-bold text-marca-900">{{ num(confiabilidad.resumen.mttr_promedio_horas) }} h</p></div>
        <div class="rounded-lg bg-white p-4 shadow-sm"><p class="text-xs uppercase text-slate-500">Disponibilidad</p><p class="mt-1 text-2xl font-bold text-marca-900">{{ num(confiabilidad.resumen.disponibilidad_promedio, 2) }}%</p></div>
        <div class="rounded-lg bg-white p-4 shadow-sm"><p class="text-xs uppercase text-slate-500">Riesgo alto / medio</p><p class="mt-1 text-2xl font-bold text-rose-700">{{ confiabilidad.resumen.alto }} / {{ confiabilidad.resumen.medio }}</p></div>
      </div>
      <div class="overflow-x-auto rounded-lg bg-white shadow-sm">
        <table class="min-w-full text-left text-sm">
          <thead class="bg-slate-50 text-xs uppercase text-slate-500">
            <tr><th class="px-3 py-2">Edificio</th><th class="px-3 py-2 text-right">Fallas</th><th class="px-3 py-2 text-right">λ (fallas/año)</th><th class="px-3 py-2 text-right">MTBF (d)</th><th class="px-3 py-2 text-right">MTTR (h)</th><th class="px-3 py-2">Próx. preventiva</th><th class="px-3 py-2 text-right">P(falla)</th><th class="px-3 py-2">Recomendación</th><th class="px-3 py-2"></th></tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-for="e in confiabilidad.edificios" :key="e.edificio_id">
              <td class="px-3 py-2"><span class="font-medium text-marca-900">{{ e.edificio }}</span><div class="text-xs text-slate-400">{{ e.cliente }} · {{ e.equipos }} equipo(s)</div></td>
              <td class="px-3 py-2 text-right">{{ e.fallas }}</td>
              <td class="px-3 py-2 text-right">{{ num(e.tasa_fallas_anual, 2) }}</td>
              <td class="px-3 py-2 text-right">{{ num(e.mtbf_dias) }}</td>
              <td class="px-3 py-2 text-right">{{ num(e.mttr_horas) }}</td>
              <td class="px-3 py-2">{{ fecha(e.proxima_preventiva) }}<span v-if="e.dias_a_proxima != null" class="text-xs text-slate-400"> ({{ e.dias_a_proxima }} d)</span><div v-if="e.preventivas_atrasadas" class="text-xs font-semibold text-rose-700">{{ e.preventivas_atrasadas }} atrasada(s)</div></td>
              <td class="px-3 py-2 text-right"><span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="colorRiesgo[e.riesgo]">{{ num(e.probabilidad_falla) }}%</span></td>
              <td class="px-3 py-2 text-xs">{{ e.recomendacion }}</td>
              <td class="px-3 py-2"><button v-if="puedeAplicar && e.riesgo !== 'BAJO' && e.proxima_visita_id && e.dias_a_proxima > 1" class="whitespace-nowrap rounded border border-marca-900 px-2 py-1 text-xs text-marca-900 hover:bg-slate-50" @click="adelantar(e)">Adelantar</button></td>
            </tr>
          </tbody>
        </table>
      </div>
      <p class="text-xs text-slate-400">Fallas = visitas correctivas del último año. λ con suavizado (fallas + 0.5)/días. P = 1 − e<sup>−λt</sup> con t = días hasta la próxima preventiva; cada preventiva atrasada sube λ un 50%. Disponibilidad = MTBF / (MTBF + MTTR).</p>
    </section>

    <!-- NIVEL DE SERVICIO -->
    <section v-if="pestana === 'servicio'" class="mt-5 space-y-4">
      <div class="flex flex-wrap items-end gap-3">
        <label class="text-xs text-slate-500">Desde<input v-model="periodo.desde" type="date" class="mt-1 block rounded border border-slate-300 px-3 py-2 text-sm text-slate-800" /></label>
        <label class="text-xs text-slate-500">Hasta<input v-model="periodo.hasta" type="date" class="mt-1 block rounded border border-slate-300 px-3 py-2 text-sm text-slate-800" /></label>
        <button class="rounded bg-marca-900 px-4 py-2 text-sm font-medium text-white" @click="cargarServicio">Calcular</button>
      </div>
      <template v-if="servicio">
        <div class="grid gap-4 sm:grid-cols-3">
          <div class="rounded-lg bg-white p-4 shadow-sm"><p class="text-xs uppercase text-slate-500">Preventivas a tiempo</p><p class="mt-1 text-3xl font-bold text-marca-900">{{ num(servicio.preventivas.cumplimiento_pct) }}%</p><p class="text-xs text-slate-500">{{ servicio.preventivas.a_tiempo }} / {{ servicio.preventivas.evaluadas }}</p></div>
          <div class="rounded-lg bg-white p-4 shadow-sm"><p class="text-xs uppercase text-slate-500">Respuesta a fallas</p><p class="mt-1 text-3xl font-bold text-marca-900">{{ num(servicio.correctivas.respuesta_promedio_horas) }} h</p><p class="text-xs text-slate-500">{{ servicio.correctivas.atendidas }} falla(s) atendidas</p></div>
          <div class="rounded-lg bg-white p-4 shadow-sm"><p class="text-xs uppercase text-slate-500">Satisfacción del cliente</p><p class="mt-1 text-3xl font-bold text-marca-900">{{ num(servicio.satisfaccion.promedio, 2) }} / 5</p><p class="text-xs text-slate-500">{{ servicio.satisfaccion.calificadas }} calificación(es)</p></div>
        </div>
        <div class="overflow-x-auto rounded-lg bg-white shadow-sm">
          <table class="min-w-full text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-3 py-2">Prioridad</th><th class="px-3 py-2 text-right">Objetivo</th><th class="px-3 py-2 text-right">Atendidas</th><th class="px-3 py-2 text-right">Respuesta promedio</th><th class="px-3 py-2 text-right">Dentro del objetivo</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="p in servicio.correctivas.por_prioridad" :key="p.prioridad">
                <td class="px-3 py-2"><span class="rounded px-2 py-0.5 text-xs font-bold" :class="colorPrioridad[p.prioridad]">{{ p.prioridad }}</span></td>
                <td class="px-3 py-2 text-right">{{ p.objetivo_horas }} h</td><td class="px-3 py-2 text-right">{{ p.atendidas }}</td>
                <td class="px-3 py-2 text-right">{{ num(p.respuesta_promedio_horas) }} h</td><td class="px-3 py-2 text-right">{{ p.dentro_objetivo_pct == null ? '—' : num(p.dentro_objetivo_pct) + '%' }}</td>
              </tr>
              <tr class="bg-slate-50 text-slate-500"><td class="px-3 py-2">Sin clasificar (antes)</td><td class="px-3 py-2 text-right">—</td><td class="px-3 py-2 text-right">{{ servicio.correctivas.sin_clasificar.atendidas }}</td><td class="px-3 py-2 text-right">{{ num(servicio.correctivas.sin_clasificar.respuesta_promedio_horas) }} h</td><td class="px-3 py-2 text-right">—</td></tr>
            </tbody>
          </table>
        </div>
      </template>
    </section>

    <!-- CLASIFICADOR -->
    <section v-if="pestana === 'fallas'" class="mt-5 grid gap-4 lg:grid-cols-2">
      <form class="rounded-lg bg-white p-4 shadow-sm" @submit.prevent="clasificar">
        <p class="text-sm text-slate-600">Escribe una falla como la reportaría un cliente. Es el mismo clasificador que usa el portal cuando el cliente pide una visita.</p>
        <textarea v-model="prueba.descripcion" rows="4" class="mt-3 w-full rounded border border-slate-300 px-3 py-2 text-sm"></textarea>
        <label class="mt-2 flex items-center gap-2 text-sm"><input v-model="prueba.urgente" type="checkbox" /> El cliente la marcó como urgente</label>
        <button class="mt-3 rounded bg-marca-900 px-4 py-2 text-sm font-medium text-white">Clasificar</button>
      </form>
      <div v-if="clase" class="rounded-lg bg-white p-4 shadow-sm">
        <span class="rounded px-3 py-1 text-lg font-bold" :class="colorPrioridad[clase.prioridad]">{{ clase.prioridad }} · {{ clase.nombre }}</span>
        <p class="mt-3 text-sm">Tiempo máximo de respuesta: <strong>{{ clase.objetivo_horas }} h</strong></p>
        <p class="mt-1 text-sm">Puntaje: <strong>{{ clase.puntaje }}</strong> (≥10 P1 · ≥6 P2 · ≥3 P3)</p>
        <p class="mt-1 text-sm">Señales detectadas: <span v-if="!clase.palabras.length" class="text-slate-400">ninguna</span><span v-for="p in clase.palabras" :key="p" class="mr-1 rounded bg-slate-100 px-1.5 py-0.5 text-xs">{{ p }}</span></p>
        <p class="mt-3 text-xs text-slate-500">P1 y P2 se programan para hoy y se asignan solas al técnico más cercano (según su posición y su carga del día).</p>
      </div>
    </section>
  </div>
</template>
