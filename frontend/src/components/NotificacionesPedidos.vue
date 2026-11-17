<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { pedidosCatalogo, avisosAgenda } from '@/services'
import { fecha, fechaHora } from '@/modulos/comun'
import { useSesionStore } from '@/stores/sesion'

const CADA_MILISEGUNDOS = 60_000

const sesion = useSesionStore()
const route = useRoute()
const router = useRouter()
const pedidos = ref({ total: 0, data: [] })
const citas = ref({ total: 0, data: [] })
const abierto = ref(false)
let temporizador = null

const esGerente = computed(() => sesion.usuario?.es_super_admin || sesion.usuario?.roles?.includes('Administrador'))
const total = computed(() => pedidos.value.total + citas.value.total)

// Cada aviso se carga por separado: si uno falla, el otro se sigue viendo y se reintenta en la próxima vuelta.
async function cargarPedidos() {
  try {
    const r = await pedidosCatalogo.listar({ situacion: 'PENDIENTE', per_page: 5, ordenar_por: 'id', direccion: 'desc' })
    pedidos.value = { total: r.meta.total, data: r.data }
  } catch {
    // sin aviso de error: la campana no debe estorbar
  }
}

async function cargarCitas() {
  if (!esGerente.value) return
  try {
    citas.value = await avisosAgenda.listar()
  } catch {
    // sin aviso de error: la campana no debe estorbar
  }
}

function cargar() {
  cargarPedidos()
  cargarCitas()
}

onMounted(() => {
  cargar()
  temporizador = setInterval(cargar, CADA_MILISEGUNDOS)
})
onBeforeUnmount(() => clearInterval(temporizador))
watch(() => route.fullPath, () => {
  abierto.value = false
  cargar()
})

/** Abrir el aviso lo da por visto y lleva a la agenda. */
async function verCita(cita) {
  abierto.value = false
  await avisosAgenda.marcarVista(cita.id).catch(() => {})
  await cargarCitas()
  router.push({ name: 'modulo', params: { clave: 'citas' } })
}

const reunion = (p) => (p.cita ? fechaHora(p.cita.fecha_inicio) : fecha(p.fecha_compromiso))
</script>

<template>
  <div class="relative">
    <button class="relative rounded p-2 hover:bg-slate-500/20" :aria-label="`Notificaciones: ${total} pendiente(s)`" :aria-expanded="abierto" @click="abierto = !abierto">
      <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M6 9a6 6 0 1 1 12 0c0 6 2.5 7.5 2.5 7.5h-17S6 15 6 9" />
        <path d="M10 20a2.2 2.2 0 0 0 4 0" />
      </svg>
      <span v-if="total" class="absolute -right-0.5 -top-0.5 min-w-5 rounded-full bg-red-600 px-1 text-center text-xs font-bold leading-5 text-white">{{ total > 99 ? '99+' : total }}</span>
    </button>

    <div v-if="abierto" class="absolute right-0 z-30 mt-2 max-h-[80vh] w-80 max-w-[calc(100vw-2rem)] overflow-y-auto rounded-lg border border-slate-200 bg-white text-slate-800 shadow-xl">
      <p v-if="!total" class="px-4 py-6 text-center text-sm text-slate-400">No tienes notificaciones.</p>

      <template v-if="citas.data.length">
        <p class="border-b border-slate-100 bg-slate-50 px-4 py-2 text-sm font-semibold text-marca-900">Citas nuevas de Secretaría ({{ citas.total }})</p>
        <button v-for="c in citas.data" :key="c.id" class="block w-full border-b border-slate-100 px-4 py-3 text-left text-sm hover:bg-slate-50" @click="verCita(c)">
          <span class="font-semibold text-marca-900">{{ c.titulo }}</span>
          <span v-if="c.con_quien || c.empresa" class="block text-xs text-slate-500">{{ [c.con_quien, c.empresa?.denominacion].filter(Boolean).join(' · ') }}</span>
          <span class="mt-1 block text-xs font-medium text-oro-700">{{ fechaHora(c.fecha_inicio) }}{{ c.lugar ? ` · ${c.lugar}` : '' }}</span>
        </button>
      </template>

      <template v-if="pedidos.data.length">
        <p class="border-b border-slate-100 bg-slate-50 px-4 py-2 text-sm font-semibold text-marca-900">Pedidos del catálogo por atender ({{ pedidos.total }})</p>
        <RouterLink v-for="p in pedidos.data" :key="p.id" :to="{ name: 'modulo', params: { clave: 'pedidos-catalogo' } }" class="block border-b border-slate-100 px-4 py-3 text-sm hover:bg-slate-50">
          <span class="font-semibold text-marca-900">{{ p.empresa?.denominacion }}</span>
          quiere comprar {{ p.cantidad }} × {{ p.producto?.nombre }}
          <span v-if="p.edificio" class="block text-xs text-slate-500">{{ p.edificio.denominacion }}</span>
          <span class="mt-1 block text-xs font-medium text-oro-700">Reunión: {{ reunion(p) }} · {{ p.codigo }}</span>
        </RouterLink>
      </template>

      <div class="flex justify-between gap-3 px-4 py-2 text-sm">
        <RouterLink :to="{ name: 'modulo', params: { clave: 'pedidos-catalogo' } }" class="text-marca-600 hover:underline">Ver pedidos</RouterLink>
        <RouterLink v-if="sesion.puede('citas.ver')" :to="{ name: 'modulo', params: { clave: 'citas' } }" class="text-marca-600 hover:underline">Ir a la agenda</RouterLink>
      </div>
    </div>
  </div>
</template>
