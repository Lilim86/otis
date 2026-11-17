<script setup>
import { ref, computed, onMounted } from 'vue'
import { ingenieria, leerError } from '@/services'
import { bs, fecha } from '@/modulos/comun'

const datos = ref(null)
const error = ref('')
const hover = ref(null)
const num = (v, d = 1) => (v == null ? '—' : Number(v).toLocaleString('es-BO', { maximumFractionDigits: d }))

onMounted(async () => {
  try {
    datos.value = await ingenieria.cobranza()
  } catch (e) {
    error.value = leerError(e).mensaje
  }
})

const colorCategoria = { A: 'bg-emerald-100 text-emerald-800', B: 'bg-sky-100 text-sky-800', C: 'bg-amber-100 text-amber-800', D: 'bg-rose-100 text-rose-800' }
const SERIES = [
  { clave: 'programado', nombre: 'Programado (según contrato)', color: '#2a78d6' },
  { clave: 'esperado', nombre: 'Esperado (modelo)', color: '#eb6834' },
]

// Barras agrupadas por semana, un solo eje (Bs), base en cero.
const grafico = computed(() => {
  const semanas = datos.value?.proyeccion.semanas ?? []
  const W = 760
  const H = 240
  const m = { l: 64, r: 8, t: 10, b: 28 }
  const max = Math.max(1, ...semanas.flatMap((s) => [s.programado, s.esperado]))
  const paso = 10 ** Math.floor(Math.log10(max))
  const tope = Math.ceil(max / paso) * paso
  const ancho = (W - m.l - m.r) / Math.max(1, semanas.length)
  const barra = Math.min(18, (ancho - 10) / 2)
  const y = (v) => m.t + (H - m.t - m.b) * (1 - v / tope)
  const marcas = [0, 0.25, 0.5, 0.75, 1].map((f) => f * tope)
  return { W, H, m, ancho, barra, y, marcas, semanas, base: y(0) }
})

/** Barra con solo la punta superior redondeada (4px), anclada a la base. */
function barraPath(x, yTop, w, base) {
  const r = Math.min(4, w / 2, Math.max(0, base - yTop))
  return `M${x},${base} V${yTop + r} Q${x},${yTop} ${x + r},${yTop} H${x + w - r} Q${x + w},${yTop} ${x + w},${yTop + r} V${base} Z`
}
</script>

<template>
  <div>
    <h1 class="text-xl font-bold text-marca-900">Análisis de cobranza</h1>
    <p class="mt-1 text-sm text-slate-500">Riesgo de mora por cliente según su historial de pago y proyección del flujo de caja a 13 semanas. El modelo se valida comparándolo con lo que de verdad se cobró.</p>
    <p v-if="error" class="mt-3 rounded bg-red-50 px-3 py-2 text-sm text-red-700">{{ error }}</p>
    <p v-if="!datos && !error" class="mt-4 text-slate-400">Calculando…</p>

    <template v-if="datos">
      <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-lg bg-white p-4 shadow-sm"><p class="text-xs uppercase text-slate-500">Saldo vencido</p><p class="mt-1 text-2xl font-bold text-marca-900">{{ bs(datos.resumen.saldo_vencido) }}</p><p class="text-xs text-slate-500">{{ datos.resumen.clientes }} cliente(s) analizados</p></div>
        <div class="rounded-lg bg-white p-4 shadow-sm"><p class="text-xs uppercase text-slate-500">Puntualidad de la cartera</p><p class="mt-1 text-2xl font-bold text-marca-900">{{ num(datos.resumen.puntualidad_cartera) }}%</p><p class="text-xs text-slate-500">cuotas pagadas dentro de la gracia</p></div>
        <div class="rounded-lg bg-white p-4 shadow-sm"><p class="text-xs uppercase text-slate-500">Brecha del trimestre</p><p class="mt-1 text-2xl font-bold text-marca-900">{{ bs(datos.proyeccion.brecha) }}</p><p class="text-xs text-slate-500">programado que probablemente no entra</p></div>
        <div v-if="datos.validacion" class="rounded-lg bg-white p-4 shadow-sm">
          <p class="text-xs uppercase text-slate-500">Precisión (backtest 90 días)</p>
          <p class="mt-1 text-2xl font-bold text-marca-900">{{ num(datos.validacion.error_modelo_pct) }}% de error</p>
          <p class="text-xs text-slate-500">el método tradicional: {{ num(datos.validacion.error_tradicional_pct) }}%</p>
        </div>
      </div>

      <!-- Proyección -->
      <div class="mt-6 rounded-lg bg-white p-4 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-2">
          <h2 class="font-semibold text-marca-900">Flujo de caja: próximas 13 semanas (Bs)</h2>
          <div class="flex gap-4 text-xs text-slate-600">
            <span v-for="s in SERIES" :key="s.clave" class="flex items-center gap-1.5"><span class="inline-block h-2.5 w-2.5 rounded-sm" :style="{ background: s.color }"></span>{{ s.nombre }}</span>
          </div>
        </div>
        <div class="relative mt-3 overflow-x-auto">
          <svg :viewBox="`0 0 ${grafico.W} ${grafico.H}`" class="w-full min-w-[560px]" role="img" aria-label="Flujo de caja programado contra esperado por semana">
            <g v-for="v in grafico.marcas" :key="v">
              <line :x1="grafico.m.l" :x2="grafico.W - grafico.m.r" :y1="grafico.y(v)" :y2="grafico.y(v)" stroke="#e2e8f0" stroke-width="1" />
              <text :x="grafico.m.l - 6" :y="grafico.y(v) + 3" text-anchor="end" font-size="10" fill="#64748b">{{ num(v / 1000, 0) }} mil</text>
            </g>
            <g v-for="(s, i) in grafico.semanas" :key="s.semana" @mouseenter="hover = i" @mouseleave="hover = null">
              <rect :x="grafico.m.l + i * grafico.ancho" :y="grafico.m.t" :width="grafico.ancho" :height="grafico.base - grafico.m.t" :fill="hover === i ? '#f1f5f9' : 'transparent'" />
              <path
                v-for="(serie, j) in SERIES"
                :key="serie.clave"
                :d="barraPath(grafico.m.l + i * grafico.ancho + grafico.ancho / 2 - grafico.barra - 1 + j * (grafico.barra + 2), grafico.y(s[serie.clave]), grafico.barra, grafico.base)"
                :fill="serie.color"
              />
              <text :x="grafico.m.l + i * grafico.ancho + grafico.ancho / 2" :y="grafico.H - 10" text-anchor="middle" font-size="10" fill="#64748b">S{{ s.semana }}</text>
            </g>
            <line :x1="grafico.m.l" :x2="grafico.W - grafico.m.r" :y1="grafico.base" :y2="grafico.base" stroke="#94a3b8" />
          </svg>
          <div v-if="hover != null" class="pointer-events-none absolute top-2 rounded border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 shadow" :style="{ left: `${Math.min(70, (hover / grafico.semanas.length) * 100)}%` }">
            <p class="font-semibold text-slate-900">Semana {{ grafico.semanas[hover].semana }} · {{ fecha(grafico.semanas[hover].desde) }} – {{ fecha(grafico.semanas[hover].hasta) }}</p>
            <p v-for="s in SERIES" :key="s.clave" class="flex items-center gap-1.5"><span class="inline-block h-2 w-2 rounded-sm" :style="{ background: s.color }"></span>{{ s.nombre }}: <strong>{{ bs(grafico.semanas[hover][s.clave]) }}</strong></p>
          </div>
        </div>
        <p class="mt-2 text-xs text-slate-500">Total programado {{ bs(datos.proyeccion.total_programado) }} · esperado {{ bs(datos.proyeccion.total_esperado) }}. La semana 1 incluye lo ya vencido. Cada saldo se ubica en la semana en que el cliente suele pagar y se pondera por su probabilidad de pago.</p>
        <details class="mt-2 text-sm">
          <summary class="cursor-pointer text-xs text-marca-600">Ver como tabla</summary>
          <table class="mt-2 min-w-full text-left text-xs">
            <thead class="bg-slate-50 uppercase text-slate-500"><tr><th class="px-3 py-1.5">Semana</th><th class="px-3 py-1.5">Del</th><th class="px-3 py-1.5">Al</th><th class="px-3 py-1.5 text-right">Programado</th><th class="px-3 py-1.5 text-right">Esperado</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="s in datos.proyeccion.semanas" :key="s.semana"><td class="px-3 py-1.5">{{ s.semana }}</td><td class="px-3 py-1.5">{{ fecha(s.desde) }}</td><td class="px-3 py-1.5">{{ fecha(s.hasta) }}</td><td class="px-3 py-1.5 text-right">{{ bs(s.programado) }}</td><td class="px-3 py-1.5 text-right">{{ bs(s.esperado) }}</td></tr>
            </tbody>
          </table>
        </details>
      </div>

      <!-- Validación -->
      <div v-if="datos.validacion" class="mt-6 rounded-lg bg-white p-4 shadow-sm text-sm">
        <h2 class="font-semibold text-marca-900">Validación del modelo</h2>
        <p class="mt-1 text-slate-600">Con lo que se sabía el {{ fecha(datos.validacion.corte) }} se proyectó lo que se cobraría hasta el {{ fecha(datos.validacion.hasta) }}:</p>
        <ul class="mt-2 grid gap-2 sm:grid-cols-3">
          <li class="rounded bg-slate-50 p-3"><p class="text-xs text-slate-500">Cobrado de verdad</p><p class="text-lg font-bold text-marca-900">{{ bs(datos.validacion.cobrado_real) }}</p></li>
          <li class="rounded bg-slate-50 p-3"><p class="text-xs text-slate-500">Modelo</p><p class="text-lg font-bold text-marca-900">{{ bs(datos.validacion.proyectado_modelo) }} <span class="text-xs font-normal text-slate-500">({{ num(datos.validacion.error_modelo_pct) }}% error)</span></p></li>
          <li class="rounded bg-slate-50 p-3"><p class="text-xs text-slate-500">Tradicional (todo al vencimiento)</p><p class="text-lg font-bold text-slate-600">{{ bs(datos.validacion.proyectado_tradicional) }} <span class="text-xs font-normal text-slate-500">({{ num(datos.validacion.error_tradicional_pct) }}% error)</span></p></li>
        </ul>
      </div>

      <!-- Clientes -->
      <h2 class="mt-6 font-semibold text-marca-900">Riesgo de mora por cliente</h2>
      <div class="mt-2 overflow-x-auto rounded-lg bg-white shadow-sm">
        <table class="min-w-full text-left text-sm">
          <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-3 py-2">Cliente</th><th class="px-3 py-2">Categoría</th><th class="px-3 py-2 text-right">Puntaje</th><th class="px-3 py-2 text-right">Pago puntual</th><th class="px-3 py-2 text-right">Atraso prom.</th><th class="px-3 py-2 text-right">Saldo vencido</th><th class="px-3 py-2 text-right">Por vencer</th><th class="px-3 py-2">Acción sugerida</th></tr></thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-if="!datos.clientes.length"><td colspan="8" class="px-3 py-6 text-center text-slate-400">Todavía no hay cuotas vencidas para analizar.</td></tr>
            <tr v-for="c in datos.clientes" :key="c.empresa_id">
              <td class="px-3 py-2 font-medium text-marca-900">{{ c.cliente }}<div class="text-xs font-normal text-slate-400">{{ c.puntuales }} de {{ c.cuotas_vencidas }} cuotas a tiempo</div></td>
              <td class="px-3 py-2"><span class="rounded-full px-2.5 py-0.5 text-xs font-bold" :class="colorCategoria[c.categoria]">{{ c.categoria }}</span></td>
              <td class="px-3 py-2 text-right">{{ num(c.puntaje) }}</td>
              <td class="px-3 py-2 text-right">{{ num(c.prob_puntual) }}%</td>
              <td class="px-3 py-2 text-right">{{ num(c.atraso_promedio_dias) }} d</td>
              <td class="px-3 py-2 text-right" :class="c.saldo_vencido > 0 ? 'font-semibold text-rose-700' : ''">{{ bs(c.saldo_vencido) }}</td>
              <td class="px-3 py-2 text-right">{{ bs(c.por_vencer) }}</td>
              <td class="px-3 py-2 text-xs">{{ c.accion }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <p class="mt-2 text-xs text-slate-400">Puntaje = 45·(1 − p) + 30·min(atraso/60, 1) + 25·min(saldo vencido / 3 cuotas, 1), con p = (puntuales + 1)/(cuotas + 2). A &lt; 25 · B &lt; 50 · C &lt; 75 · D.</p>
    </template>
  </div>
</template>
