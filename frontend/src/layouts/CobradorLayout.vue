<script setup>
import { computed, onMounted } from 'vue'
import { RouterLink, RouterView, useRouter } from 'vue-router'
import { useSesionStore } from '@/stores/sesion'
import { useCobradorStore } from '@/stores/cobrador'
import { useCatalogosStore } from '@/stores/catalogos'
import { bs } from '@/modulos/comun'

const sesion = useSesionStore()
const datos = useCobradorStore()
const router = useRouter()

onMounted(() => {
  useCatalogosStore().cargar().catch(() => {})
  datos.cargarCobros().catch(() => {})
})

// Lo que lleva encima hoy: lo cobrado que todavía no entregó a Tesorería.
const enMano = computed(() => datos.porEnviar.reduce((s, c) => s + Number(c.cobrado_bs || 0), 0))

const pestanas = [
  { nombre: 'cobrador-ruta', texto: 'Mi ruta', icono: 'M12 21s-6-5.3-6-10a6 6 0 1 1 12 0c0 4.7-6 10-6 10Z M12 13a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z' },
  { nombre: 'cobrador-recibos', texto: 'Recibos', icono: 'M6 3h12v18l-3-2-3 2-3-2-3 2V3Z M9 8h6 M9 12h6 M9 16h3' },
]

async function salir() {
  await sesion.salir()
  router.push({ name: 'login' })
}
</script>

<template>
  <div class="min-h-screen bg-slate-100 pb-20 md:pb-0">
    <header class="sticky top-0 z-20 bg-marca-950 text-white shadow-md">
      <div class="mx-auto flex max-w-5xl items-center justify-between gap-3 px-4 py-3">
        <div class="flex items-center gap-3">
          <div class="grid size-10 place-items-center rounded-xl bg-emerald-500/15 text-emerald-300 ring-1 ring-emerald-400/40">
            <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <rect x="3" y="6" width="18" height="13" rx="2" /><path d="M3 10h18" /><path d="M16 15h2" />
            </svg>
          </div>
          <div class="leading-tight">
            <div class="text-sm font-semibold">{{ sesion.usuario?.name }}</div>
            <div class="text-[11px] uppercase tracking-[0.25em] text-emerald-300/80">Cobranza en campo</div>
          </div>
        </div>

        <nav class="hidden gap-1 rounded-full bg-white/5 p-1 md:flex">
          <RouterLink v-for="p in pestanas" :key="p.nombre" :to="{ name: p.nombre }" class="rounded-full px-4 py-1.5 text-sm text-slate-300 hover:text-white" active-class="!bg-emerald-500 !text-marca-950 font-semibold">
            {{ p.texto }}
          </RouterLink>
        </nav>

        <div class="flex items-center gap-3">
          <div class="hidden text-right sm:block">
            <div class="text-[10px] uppercase tracking-wider text-slate-400">Por entregar</div>
            <div class="font-semibold tabular-nums text-emerald-300">{{ bs(enMano) }}</div>
          </div>
          <button class="rounded-lg border border-white/20 px-3 py-1.5 text-sm hover:bg-white/10" @click="salir">Salir</button>
        </div>
      </div>
    </header>

    <main class="mx-auto max-w-5xl p-4">
      <RouterView />
    </main>

    <!-- En el celular, las pestañas van abajo, al alcance del pulgar. -->
    <nav class="fixed inset-x-0 bottom-0 z-20 grid grid-cols-2 border-t border-slate-200 bg-white md:hidden">
      <RouterLink v-for="p in pestanas" :key="p.nombre" :to="{ name: p.nombre }" class="relative flex flex-col items-center gap-0.5 py-2 text-xs text-slate-500" active-class="!text-emerald-700 font-semibold">
        <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path :d="p.icono" /></svg>
        {{ p.texto }}
        <span v-if="p.nombre === 'cobrador-recibos' && datos.porEnviar.length" class="absolute right-[calc(50%-1.75rem)] top-1 min-w-5 rounded-full bg-rose-600 px-1 text-center text-[10px] font-bold leading-5 text-white">{{ datos.porEnviar.length }}</span>
      </RouterLink>
    </nav>
  </div>
</template>
