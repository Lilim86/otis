<script setup>
import { ref, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { obtenerResumen, alertas } from '@/services'

const resumen = ref(null)
const avisos = ref({ contratos: null, repuestos: null, reparaciones: null })
const error = ref('')

const fechaCorta = (v) => (v ? String(v).slice(0, 10).split('-').reverse().join('/') : '—')
const bs = (n) => new Intl.NumberFormat('es-BO', { style: 'currency', currency: 'BOB' }).format(n ?? 0)

onMounted(async () => {
  try {
    resumen.value = await obtenerResumen()
    const [contratos, repuestos, reparaciones] = await Promise.all([
      alertas({ tipo: 'contratos', dias: 30 }),
      alertas({ tipo: 'repuestos' }),
      alertas({ tipo: 'reparaciones', dias: 30 }),
    ])
    avisos.value = { contratos: contratos.length, repuestos: repuestos.length, reparaciones: reparaciones.length }
  } catch {
    error.value = 'No se pudo cargar el resumen. Revisa que el backend esté corriendo.'
  }
})
</script>

<template>
  <div>
    <h1 class="text-xl font-bold text-marca-900">Panel principal</h1>
    <p v-if="error" class="mt-4 rounded bg-red-50 px-3 py-2 text-sm text-red-700">{{ error }}</p>

    <div v-if="resumen" class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
      <div class="rounded-lg bg-white p-4 shadow-sm">
        <p class="text-sm text-slate-500">Edificios</p>
        <p class="text-3xl font-bold text-marca-900">{{ resumen.total_edificios }}</p>
      </div>
      <div class="rounded-lg bg-white p-4 shadow-sm">
        <p class="text-sm text-slate-500">Equipos</p>
        <p class="text-3xl font-bold text-marca-900">{{ resumen.total_equipos }}</p>
      </div>
      <div class="rounded-lg bg-white p-4 shadow-sm">
        <p class="text-sm text-slate-500">Contratos</p>
        <p class="text-3xl font-bold text-marca-900">{{ resumen.total_contratos }}</p>
        <p class="text-xs text-slate-500">{{ bs(resumen.monto_contratos) }}</p>
      </div>
      <div class="rounded-lg bg-white p-4 shadow-sm">
        <p class="text-sm text-slate-500">Dólar (Bs)</p>
        <p class="text-3xl font-bold text-marca-900">{{ resumen.dolar_bob ?? 's/d' }}</p>
        <p v-if="resumen.dolar_bob === null" class="text-xs text-slate-500">Servicio externo sin respuesta</p>
      </div>
    </div>

    <h2 class="mt-8 text-lg font-semibold text-marca-900">Alertas</h2>
    <div class="mt-3 grid gap-4 sm:grid-cols-3">
      <div v-for="(titulo, clave) in { contratos: 'Contratos vencidos o por vencer (30 días)', repuestos: 'Repuestos con stock bajo', reparaciones: 'Reparaciones abiertas hace más de 30 días' }" :key="clave" class="rounded-lg bg-white p-4 shadow-sm">
        <p class="text-sm text-slate-500">{{ titulo }}</p>
        <p class="text-3xl font-bold" :class="avisos[clave] ? 'text-amber-600' : 'text-emerald-600'">{{ avisos[clave] ?? '…' }}</p>
      </div>
    </div>

    <!-- Cronograma: quién está en campo -->
    <template v-if="resumen?.tecnicos">
      <div class="mt-8 flex items-center justify-between">
        <h2 class="text-lg font-semibold text-marca-900">Técnicos y visitas</h2>
        <RouterLink :to="{ name: 'cronograma' }" class="text-sm text-marca-600 underline hover:text-oro-600">Ver cronograma completo</RouterLink>
      </div>
      <div class="mt-3 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-lg bg-white p-4 shadow-sm"><p class="text-sm text-slate-500">Visitas hoy</p><p class="text-3xl font-bold text-marca-900">{{ resumen.visitas.hoy }}</p></div>
        <div class="rounded-lg bg-white p-4 shadow-sm"><p class="text-sm text-slate-500">En curso ahora</p><p class="text-3xl font-bold text-emerald-600">{{ resumen.visitas.en_curso }}</p></div>
        <div class="rounded-lg bg-white p-4 shadow-sm"><p class="text-sm text-slate-500">Sin técnico (próx. 7 días)</p><p class="text-3xl font-bold" :class="resumen.visitas.sin_tecnico ? 'text-amber-600' : 'text-emerald-600'">{{ resumen.visitas.sin_tecnico }}</p></div>
        <div class="rounded-lg bg-white p-4 shadow-sm"><p class="text-sm text-slate-500">Atrasadas</p><p class="text-3xl font-bold" :class="resumen.visitas.atrasadas ? 'text-rose-700' : 'text-emerald-600'">{{ resumen.visitas.atrasadas }}</p></div>
      </div>
      <ul v-if="resumen.tecnicos.length" class="mt-3 divide-y divide-slate-100 rounded-lg bg-white shadow-sm">
        <li v-for="t in resumen.tecnicos" :key="t.id" class="flex flex-wrap items-center justify-between gap-2 px-4 py-2 text-sm">
          <span class="font-medium text-marca-900">{{ t.nombre }}</span>
          <span v-if="t.en_curso" class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs text-emerald-800">🛗 En {{ t.en_curso.edificio }} ({{ t.en_curso.cliente }})</span>
          <span v-else class="text-xs text-slate-500">{{ t.hoy }} visita(s) hoy · {{ t.pendientes }} pendientes</span>
        </li>
      </ul>
    </template>

    <!-- Clientes -->
    <template v-if="resumen?.clientes">
      <div class="mt-8 flex items-center justify-between">
        <h2 class="text-lg font-semibold text-marca-900">Clientes ({{ resumen.total_clientes }})</h2>
        <RouterLink :to="{ name: 'modulo', params: { clave: 'empresas' } }" class="text-sm text-marca-600 underline hover:text-oro-600">Ver todos</RouterLink>
      </div>
      <div class="mt-3 overflow-x-auto rounded-lg bg-white shadow-sm">
        <table class="min-w-full text-left text-sm">
          <thead class="bg-slate-50 text-xs uppercase text-slate-500">
            <tr><th class="px-4 py-3">Cliente</th><th class="px-4 py-3">Contratos vigentes</th><th class="px-4 py-3">Equipos</th><th class="px-4 py-3">Por cobrar</th><th class="px-4 py-3">Próxima visita</th></tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-if="!resumen.clientes.length"><td colspan="5" class="px-4 py-6 text-center text-slate-400">Aún no hay clientes registrados.</td></tr>
            <tr v-for="c in resumen.clientes" :key="c.id">
              <td class="px-4 py-2 font-medium text-marca-900">{{ c.cliente }}</td>
              <td class="px-4 py-2">{{ c.contratos }}</td>
              <td class="px-4 py-2">{{ c.equipos }}</td>
              <td class="px-4 py-2" :class="c.por_cobrar_bs > 0 ? 'font-semibold text-amber-700' : 'text-slate-500'">{{ bs(c.por_cobrar_bs) }}</td>
              <td class="px-4 py-2">{{ fechaCorta(c.proxima_visita) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>

    <!-- Almacén -->
    <div v-if="resumen?.stock_bajo || resumen?.herramientas_en_poder" class="mt-8 grid gap-4 lg:grid-cols-2">
      <div v-if="resumen.stock_bajo" class="rounded-lg bg-white p-4 shadow-sm">
        <h2 class="font-semibold text-marca-900">Stock bajo el mínimo <span class="text-amber-600">({{ resumen.stock_bajo.length }})</span></h2>
        <p v-if="!resumen.stock_bajo.length" class="mt-2 text-sm text-emerald-700">Todo el inventario está sobre su mínimo.</p>
        <ul class="mt-2 divide-y divide-slate-100 text-sm">
          <li v-for="i in resumen.stock_bajo.slice(0, 6)" :key="i.id" class="flex justify-between py-1.5"><span>{{ i.descripcion }} <span class="text-xs text-slate-400">{{ i.codigo }}</span></span><span class="font-semibold text-rose-700">{{ i.stock_actual }} / {{ i.stock_minimo }}</span></li>
        </ul>
      </div>
      <div v-if="resumen.herramientas_en_poder" class="rounded-lg bg-white p-4 shadow-sm">
        <div class="flex justify-between"><h2 class="font-semibold text-marca-900">Herramientas fuera del almacén</h2><RouterLink :to="{ name: 'herramientas' }" class="text-sm text-marca-600 underline">Ver</RouterLink></div>
        <p v-if="!resumen.herramientas_en_poder.length" class="mt-2 text-sm text-emerald-700">Todas están guardadas.</p>
        <ul class="mt-2 divide-y divide-slate-100 text-sm">
          <li v-for="h in resumen.herramientas_en_poder" :key="`${h.personal_id}-${h.insumo_id}`" class="flex justify-between py-1.5"><span>{{ h.herramienta }} × {{ h.cantidad }}</span><span class="text-slate-500">{{ h.persona }} · {{ h.dias }} d</span></li>
        </ul>
      </div>
    </div>
  </div>
</template>
