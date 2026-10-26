<script setup>
import { ref } from 'vue'
import { RouterLink, RouterView, useRouter } from 'vue-router'
import { useSesionStore } from '@/stores/sesion'

const sesion = useSesionStore()
const router = useRouter()
const abierto = ref(false)

const enlaces = [
  { nombre: 'portal-inicio', texto: 'Inicio' },
  { nombre: 'portal-mantenimiento', texto: 'Mantenimiento' },
  { nombre: 'portal-cronograma', texto: 'Cronograma' },
  { nombre: 'portal-pagos', texto: 'Pagos' },
  { nombre: 'portal-contratos', texto: 'Contratos y equipos' },
]

async function salir() {
  await sesion.salir()
  router.push({ name: 'login' })
}
</script>

<template>
  <div class="min-h-screen bg-marfil">
    <header class="bg-gradient-to-r from-marca-950 via-marca-900 to-marca-800 text-white shadow-lg">
      <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3">
        <div>
          <div class="font-display text-2xl font-extrabold tracking-[0.3em] text-oro-300">OTIS</div>
          <div class="text-[10px] uppercase tracking-[0.35em] text-oro-200/70">Portal del cliente</div>
        </div>
        <div class="hidden text-right leading-tight sm:block">
          <div class="text-sm font-semibold">{{ sesion.usuario?.empresa?.denominacion }}</div>
          <div class="text-xs text-marca-200">{{ sesion.usuario?.name || sesion.usuario?.username }}</div>
        </div>
        <div class="flex items-center gap-2">
          <button class="rounded border border-oro-400/60 px-3 py-1 text-sm text-oro-200 hover:bg-oro-400 hover:text-marca-950" @click="salir">Salir</button>
          <button class="rounded p-2 hover:bg-white/10 md:hidden" aria-label="Abrir menú" @click="abierto = !abierto">☰</button>
        </div>
      </div>
      <nav class="border-t border-oro-500/30" :class="abierto ? 'block' : 'hidden md:block'">
        <div class="mx-auto flex max-w-6xl flex-col px-4 md:flex-row md:gap-1">
          <RouterLink
            v-for="e in enlaces"
            :key="e.nombre"
            :to="{ name: e.nombre }"
            class="rounded-t px-4 py-2.5 text-sm text-marca-100 hover:bg-white/10"
            active-class="!bg-marfil !text-marca-900 font-semibold"
            @click="abierto = false"
          >
            {{ e.texto }}
          </RouterLink>
        </div>
      </nav>
    </header>

    <main class="mx-auto max-w-6xl p-4 lg:p-6">
      <RouterView />
    </main>
  </div>
</template>
