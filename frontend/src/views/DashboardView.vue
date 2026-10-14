<script setup>
import { ref, onMounted } from 'vue'
import { obtenerResumen, alertas } from '@/services'

const resumen = ref(null)
const avisos = ref({ contratos: null, repuestos: null, reparaciones: null })
const error = ref('')

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
  </div>
</template>
