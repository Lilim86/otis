<script setup>
import { ref, computed } from 'vue'
import { RouterLink, RouterView, useRouter } from 'vue-router'
import { useSesionStore } from '@/stores/sesion'
import { menu } from '@/modulos'

const sesion = useSesionStore()
const router = useRouter()
const menuAbierto = ref(false)

// Cada persona ve solo los módulos que su rol puede consultar.
const menuVisible = computed(() =>
  menu.map((g) => ({ ...g, items: g.items.filter((m) => sesion.puede(`${m.clave}.ver`)) })).filter((g) => g.items.length),
)
const rol = computed(() => (sesion.usuario?.es_super_admin ? 'Super Administrador' : sesion.usuario?.roles?.join(', ') || 'Sin rol'))

async function salir() {
  await sesion.salir()
  router.push({ name: 'login' })
}
</script>

<template>
  <div class="min-h-screen lg:flex">
    <!-- Barra superior en celular: los técnicos usan el sistema en campo -->
    <header class="flex items-center justify-between bg-marca-900 px-4 py-3 text-oro-200 lg:hidden">
      <span class="font-bold tracking-wide">LILIM · OTIS</span>
      <button class="rounded p-2 hover:bg-marca-700" aria-label="Abrir menú" @click="menuAbierto = !menuAbierto">☰</button>
    </header>

    <aside
      class="w-full shrink-0 bg-gradient-to-b from-marca-950 via-marca-900 to-marca-800 text-marca-100 lg:block lg:w-64"
      :class="menuAbierto ? 'block' : 'hidden'"
    >
      <div class="hidden border-b border-oro-500/40 px-5 py-5 text-center lg:block"><div class="font-display text-2xl font-extrabold tracking-[0.3em] text-oro-300">OTIS</div><div class="text-[10px] uppercase tracking-[0.4em] text-oro-200/70">LILIM</div></div>

      <nav class="space-y-4 px-3 pb-6 text-sm">
        <RouterLink
          :to="{ name: 'dashboard' }"
          class="block rounded px-3 py-2 hover:bg-white/10"
          active-class="bg-oro-500/20 text-oro-200"
          exact-active-class="bg-oro-500/20 text-oro-200"
          @click="menuAbierto = false"
        >
          Inicio
        </RouterLink>

        <RouterLink
          v-if="sesion.usuario?.es_tecnico"
          :to="{ name: 'tecnico-ruta' }"
          class="block rounded px-3 py-2 hover:bg-white/10"
          active-class="bg-oro-500/20 text-oro-200"
          @click="menuAbierto = false"
        >
          Mi ruta
        </RouterLink>

        <RouterLink
          :to="{ name: 'reportes' }"
          class="block rounded px-3 py-2 hover:bg-white/10"
          active-class="bg-oro-500/20 text-oro-200"
          @click="menuAbierto = false"
        >
          Reportes
        </RouterLink>

        <div v-for="grupo in menuVisible" :key="grupo.grupo">
          <p class="px-3 pb-1 text-xs font-semibold uppercase tracking-[0.2em] text-oro-400/80">{{ grupo.grupo }}</p>
          <RouterLink
            v-for="modulo in grupo.items"
            :key="modulo.clave"
            :to="{ name: 'modulo', params: { clave: modulo.clave } }"
            class="block rounded px-3 py-2 hover:bg-white/10"
            active-class="bg-oro-500/20 text-oro-200"
            @click="menuAbierto = false"
          >
            {{ modulo.titulo }}
          </RouterLink>
        </div>
      </nav>
    </aside>

    <div class="flex min-w-0 flex-1 flex-col">
      <header class="hidden items-center justify-end gap-4 border-b-2 border-oro-400 bg-white px-6 py-3 lg:flex">
        <div class="text-right leading-tight">
          <div class="text-sm font-semibold text-marca-900">{{ sesion.usuario?.name || sesion.usuario?.username }}</div>
          <div class="text-xs text-slate-500">{{ rol }}</div>
        </div>
        <button class="rounded border border-slate-300 px-3 py-1 text-sm hover:bg-slate-50" @click="salir">Salir</button>
      </header>

      <main class="flex-1 p-4 lg:p-6">
        <RouterView />
      </main>

      <footer class="flex items-center justify-between border-t border-slate-200 bg-white px-4 py-2 text-xs text-slate-500 lg:hidden">
        <span>{{ sesion.usuario?.name }}</span>
        <button class="underline" @click="salir">Salir</button>
      </footer>
    </div>
  </div>
</template>
