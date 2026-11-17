<script setup>
import { ref, computed, watch } from 'vue'
import { RouterLink, RouterView, useRouter, useRoute } from 'vue-router'
import { useSesionStore } from '@/stores/sesion'
import { menu } from '@/modulos'
import NotificacionesPedidos from '@/components/NotificacionesPedidos.vue'

const sesion = useSesionStore()
const router = useRouter()
const route = useRoute()
const menuAbierto = ref(false)

// Cada persona ve solo los módulos y pantallas que su rol puede consultar.
const menuVisible = computed(() =>
  menu
    .map((g) => ({
      ...g,
      items: g.items.filter((m) => sesion.puede(`${m.clave}.ver`)),
      enlaces: (g.enlaces ?? []).filter((e) => (e.soloTecnico ? sesion.usuario?.es_tecnico : sesion.puede(e.permiso))),
    }))
    .filter((g) => g.items.length || g.enlaces.length),
)

// Los grupos se despliegan solos: se abre el que contiene la pantalla actual (p. ej. "Técnico" al entrar a Mi ruta).
const abiertos = ref(new Set())
const grupoActual = computed(() =>
  menuVisible.value.find((g) => g.items.some((m) => route.name === 'modulo' && route.params.clave === m.clave) || g.enlaces.some((e) => e.ruta === route.name))?.grupo,
)
watch(grupoActual, (g) => g && abiertos.value.add(g), { immediate: true })
const alternar = (g) => (abiertos.value.has(g) ? abiertos.value.delete(g) : abiertos.value.add(g))
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
      <div class="flex items-center gap-1">
        <NotificacionesPedidos v-if="sesion.puede('pedidos-catalogo.ver')" />
        <button class="rounded p-2 hover:bg-marca-700" aria-label="Abrir menú" @click="menuAbierto = !menuAbierto">☰</button>
      </div>
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
          :to="{ name: 'reportes' }"
          class="block rounded px-3 py-2 hover:bg-white/10"
          active-class="bg-oro-500/20 text-oro-200"
          @click="menuAbierto = false"
        >
          Reportes
        </RouterLink>

        <div v-for="grupo in menuVisible" :key="grupo.grupo">
          <button
            class="flex w-full items-center justify-between rounded px-3 pb-1 pt-2 text-left text-xs font-semibold uppercase tracking-[0.2em] text-oro-400/80 hover:text-oro-300"
            :aria-expanded="abiertos.has(grupo.grupo)"
            @click="alternar(grupo.grupo)"
          >
            {{ grupo.grupo }}
            <span class="transition-transform" :class="abiertos.has(grupo.grupo) ? 'rotate-90' : ''">›</span>
          </button>
          <div v-show="abiertos.has(grupo.grupo)">
            <RouterLink
              v-for="enlace in grupo.enlaces"
              :key="enlace.ruta"
              :to="{ name: enlace.ruta }"
              class="block rounded px-3 py-2 hover:bg-white/10"
              active-class="bg-oro-500/20 text-oro-200"
              @click="menuAbierto = false"
            >
              {{ enlace.titulo }}
            </RouterLink>
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
        </div>
      </nav>
    </aside>

    <div class="flex min-w-0 flex-1 flex-col">
      <header class="hidden items-center justify-end gap-4 border-b-2 border-oro-400 bg-white px-6 py-3 lg:flex">
        <NotificacionesPedidos v-if="sesion.puede('pedidos-catalogo.ver')" class="text-marca-900" />
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
