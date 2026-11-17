<script setup>
import { ref, computed, onMounted, nextTick } from 'vue'
import { leerError } from '@/services'
import { useCobradorStore } from '@/stores/cobrador'
import { bs, fecha } from '@/modulos/comun'
import CobroFormulario from '@/components/CobroFormulario.vue'

const datos = useCobradorStore()
const cargando = ref(true)
const error = ref('')
const elegida = ref(null) // id del edificio resaltado en el mapa
const verAlDia = ref(false)
const cobrando = ref(null) // { parada, cuota }

async function cargar() {
  cargando.value = true
  try {
    await datos.cargarRuta()
    error.value = ''
  } catch (e) {
    error.value = leerError(e).mensaje
  } finally {
    cargando.value = false
  }
}
onMounted(cargar)

const ruta = computed(() => datos.ruta)
const paradas = computed(() => ruta.value?.paradas ?? [])
const r = computed(() => ruta.value?.resumen ?? {})
const horas = computed(() => {
  const m = Math.round(r.value.minutos ?? 0)
  return m >= 60 ? `${Math.floor(m / 60)} h ${m % 60} min` : `${m} min`
})

// ---- mapa: proyección simple (la longitud se achica con el coseno de la latitud) -------------
const ANCHO = 640
const ALTO = 380
const MARGEN = 34
const mapa = computed(() => {
  const base = ruta.value?.base
  const ubicadas = paradas.value.filter((p) => p.latitud !== null)
  if (!base || !ubicadas.length) return null

  const k = Math.cos((base[0] * Math.PI) / 180)
  const todos = [{ lat: base[0], lon: base[1] }, ...ubicadas.map((p) => ({ lat: p.latitud, lon: p.longitud }))]
  const xs = todos.map((p) => p.lon * k)
  const ys = todos.map((p) => p.lat)
  const [x0, x1, y0, y1] = [Math.min(...xs), Math.max(...xs), Math.min(...ys), Math.max(...ys)]
  const escala = Math.min((ANCHO - 2 * MARGEN) / (x1 - x0 || 1e-6), (ALTO - 2 * MARGEN) / (y1 - y0 || 1e-6))
  const dx = (ANCHO - (x1 - x0) * escala) / 2
  const dy = (ALTO - (y1 - y0) * escala) / 2
  const punto = (lat, lon) => ({ x: dx + (lon * k - x0) * escala, y: dy + (y1 - lat) * escala })

  const oficina = punto(base[0], base[1])
  const puntos = ubicadas.map((p) => ({ ...p, ...punto(p.latitud, p.longitud) }))
  const ida = [oficina, ...puntos].map((p) => `${p.x.toFixed(1)},${p.y.toFixed(1)}`).join(' ')
  const ultimo = puntos[puntos.length - 1]

  return { oficina, puntos, ida, vuelta: `${ultimo.x},${ultimo.y} ${oficina.x},${oficina.y}` }
})

async function elegir(id) {
  elegida.value = id
  await nextTick()
  document.getElementById(`parada-${id}`)?.scrollIntoView({ behavior: 'smooth', block: 'center' })
}

const comoLlegar = (p) => `https://www.google.com/maps/dir/?api=1&destination=${p.latitud},${p.longitud}`
const telefono = (t) => `tel:${String(t).replace(/[^\d+]/g, '')}`

function cobrar(parada, cuota) {
  cobrando.value = { parada, cuota }
}

async function alGuardar() {
  await Promise.all([datos.cargarRuta(), datos.cargarCobros()]).catch(() => {})
}
</script>

<template>
  <div class="space-y-4">
    <p v-if="error" class="rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-700">{{ error }}</p>
    <p v-if="cargando && !ruta" class="py-16 text-center text-slate-400">Armando tu ruta…</p>

    <template v-if="ruta">
      <!-- Lo que hay que cobrar hoy -->
      <section class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <div class="col-span-2 rounded-2xl bg-gradient-to-br from-emerald-600 to-emerald-800 p-4 text-white shadow-sm lg:col-span-1">
          <div class="text-xs uppercase tracking-wider text-emerald-100">Por cobrar</div>
          <div class="text-3xl font-bold tabular-nums">{{ bs(r.por_cobrar) }}</div>
          <div class="text-xs text-emerald-100">en {{ r.por_visitar }} de tus {{ r.edificios }} edificios</div>
        </div>
        <div class="rounded-2xl bg-white p-4 shadow-sm">
          <div class="text-xs uppercase tracking-wider text-slate-500">Vencido</div>
          <div class="text-xl font-bold tabular-nums text-rose-700">{{ bs(r.vencido) }}</div>
        </div>
        <div class="rounded-2xl bg-white p-4 shadow-sm">
          <div class="text-xs uppercase tracking-wider text-slate-500">Recorrido</div>
          <div class="text-xl font-bold tabular-nums text-marca-900">{{ r.km }} km</div>
          <div class="text-xs text-slate-500">≈ {{ horas }} con las paradas</div>
        </div>
        <div class="col-span-2 flex items-center rounded-2xl bg-white p-4 text-xs text-slate-500 shadow-sm lg:col-span-1">
          Orden más corto saliendo de la oficina. Toca un número del mapa para ir a ese edificio.
        </div>
      </section>

      <!-- Mapa de la ruta en la ciudad -->
      <section v-if="mapa" class="overflow-hidden rounded-2xl bg-marca-950 shadow-sm">
        <svg :viewBox="`0 0 ${ANCHO} ${ALTO}`" class="block h-auto w-full" role="img" :aria-label="`Mapa de la ruta con ${mapa.puntos.length} edificios`">
          <defs>
            <pattern id="cuadras" width="32" height="32" patternUnits="userSpaceOnUse">
              <path d="M32 0H0V32" fill="none" stroke="#ffffff" stroke-opacity="0.06" />
            </pattern>
          </defs>
          <rect :width="ANCHO" :height="ALTO" fill="url(#cuadras)" />
          <polyline :points="mapa.ida" fill="none" stroke="#34d399" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round" />
          <polyline :points="mapa.vuelta" fill="none" stroke="#34d399" stroke-opacity="0.45" stroke-width="2" stroke-dasharray="5 6" />

          <g :transform="`translate(${mapa.oficina.x} ${mapa.oficina.y})`">
            <rect x="-9" y="-9" width="18" height="18" rx="4" fill="#e2b13b" />
            <text y="24" text-anchor="middle" font-size="11" fill="#f4de9b" font-weight="600">Oficina</text>
          </g>

          <g v-for="p in mapa.puntos" :key="p.id" :transform="`translate(${p.x} ${p.y})`" class="cursor-pointer" role="button" :aria-label="`${p.orden}. ${p.edificio}`" tabindex="0" @click="elegir(p.id)" @keydown.enter="elegir(p.id)">
            <circle :r="elegida === p.id ? 15 : 12" :fill="p.vencido > 0 ? '#e11d48' : '#059669'" stroke="#fff" :stroke-width="elegida === p.id ? 3 : 1.5" />
            <text y="4" text-anchor="middle" font-size="11" font-weight="700" fill="#fff">{{ p.orden }}</text>
            <text v-if="elegida === p.id" y="-20" text-anchor="middle" font-size="11" font-weight="600" fill="#fff">{{ p.edificio }}</text>
          </g>
        </svg>
        <div class="flex flex-wrap gap-4 border-t border-white/10 px-4 py-2 text-xs text-slate-300">
          <span class="flex items-center gap-1.5"><span class="size-3 rounded-full bg-rose-600" /> Con deuda vencida</span>
          <span class="flex items-center gap-1.5"><span class="size-3 rounded-full bg-emerald-600" /> Por vencer</span>
          <span class="flex items-center gap-1.5"><span class="h-0.5 w-5 bg-emerald-400" /> Ida · <span class="h-0.5 w-5 border-t-2 border-dashed border-emerald-400/50" /> Regreso</span>
        </div>
      </section>

      <p v-if="!paradas.length" class="rounded-2xl bg-white p-8 text-center text-slate-500 shadow-sm">
        No tienes cobros pendientes en tus edificios. ¡Todo al día!
      </p>

      <!-- Paradas en orden -->
      <ol class="space-y-3">
        <li v-for="p in paradas" :id="`parada-${p.id}`" :key="p.id" class="overflow-hidden rounded-2xl bg-white shadow-sm ring-2 transition" :class="elegida === p.id ? 'ring-emerald-500' : 'ring-transparent'" @click="elegida = p.id">
          <div class="flex gap-3 p-4">
            <div class="grid size-10 shrink-0 place-items-center rounded-full text-sm font-bold text-white" :class="p.vencido > 0 ? 'bg-rose-600' : 'bg-emerald-600'">{{ p.orden }}</div>
            <div class="min-w-0 flex-1">
              <div class="flex flex-wrap items-start justify-between gap-2">
                <div class="min-w-0">
                  <h3 class="font-bold text-marca-900">{{ p.edificio }}</h3>
                  <p class="text-sm text-slate-600">{{ p.cliente }}</p>
                </div>
                <div class="text-right">
                  <div class="text-lg font-bold tabular-nums text-marca-900">{{ bs(p.por_cobrar) }}</div>
                  <div v-if="p.vencido > 0" class="text-xs font-medium text-rose-700">{{ bs(p.vencido) }} vencido</div>
                </div>
              </div>
              <p v-if="p.direccion || p.referencia" class="mt-1 text-xs text-slate-500">{{ [p.direccion, p.referencia].filter(Boolean).join(' · ') }}</p>
              <div class="mt-2 flex flex-wrap gap-2 text-xs">
                <a v-if="p.latitud !== null" :href="comoLlegar(p)" target="_blank" rel="noopener" class="rounded-full bg-marca-50 px-3 py-1 font-medium text-marca-700 hover:bg-marca-100">Cómo llegar ↗</a>
                <a v-if="p.contacto?.telefono" :href="telefono(p.contacto.telefono)" class="rounded-full bg-marca-50 px-3 py-1 font-medium text-marca-700 hover:bg-marca-100">Llamar a {{ p.contacto.nombre }}</a>
                <span v-for="(e, i) in p.equipos" :key="i" class="rounded-full bg-slate-100 px-3 py-1 text-slate-600">{{ e }}</span>
              </div>
            </div>
          </div>

          <ul class="divide-y divide-slate-100 border-t border-slate-100 bg-slate-50/60">
            <li v-for="q in p.cuotas" :key="q.id" class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
              <div class="text-sm">
                <div class="font-medium text-slate-800">Cuota {{ q.nro_cuota }} <span class="font-normal text-slate-500">· {{ q.contrato }}</span></div>
                <div class="text-xs" :class="q.vencida ? 'font-medium text-rose-700' : 'text-slate-500'">{{ q.vencida ? 'Venció' : 'Vence' }} el {{ fecha(q.fecha) }}{{ q.concepto ? ` · ${q.concepto}` : '' }}</div>
                <div v-if="q.saldo < q.monto" class="text-xs text-slate-500">Pagado en parte: falta {{ bs(q.saldo) }} de {{ bs(q.monto) }}</div>
              </div>
              <button class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-emerald-700" @click.stop="cobrar(p, q)">Cobrar {{ bs(q.saldo) }}</button>
            </li>
          </ul>
        </li>
      </ol>

      <section v-if="ruta.al_dia.length" class="rounded-2xl bg-white p-4 shadow-sm">
        <button class="flex w-full items-center justify-between text-sm font-semibold text-slate-700" :aria-expanded="verAlDia" @click="verAlDia = !verAlDia">
          Edificios al día ({{ ruta.al_dia.length }}) <span>{{ verAlDia ? '−' : '+' }}</span>
        </button>
        <ul v-if="verAlDia" class="mt-3 divide-y divide-slate-100 text-sm">
          <li v-for="p in ruta.al_dia" :key="p.id" class="flex justify-between py-2"><span class="text-slate-800">{{ p.edificio }}</span><span class="text-slate-500">{{ p.cliente }}</span></li>
        </ul>
      </section>
    </template>

    <CobroFormulario v-if="cobrando" :parada="cobrando.parada" :cuota="cobrando.cuota" :cuentas="datos.cuentas" @guardado="alGuardar" @cerrar="cobrando = null" />
  </div>
</template>
