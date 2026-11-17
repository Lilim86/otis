<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue'
import { qrCobro, leerError } from '@/services'
import { bs, fecha } from '@/modulos/comun'

/** Tarjeta con el QR de cobro y sus datos. La usan el portal del cliente y Cobranzas. */
defineProps({ compacto: { type: Boolean, default: false } })
const datos = ref(null)
const imagen = ref('')
const error = ref('')

async function cargar() {
  try {
    const [d, url] = await Promise.all([qrCobro.datos(), qrCobro.imagen()])
    if (imagen.value) URL.revokeObjectURL(imagen.value)
    datos.value = d
    imagen.value = url
  } catch (e) {
    error.value = leerError(e).mensaje
  }
}
onMounted(cargar)
onBeforeUnmount(() => imagen.value && URL.revokeObjectURL(imagen.value))
defineExpose({ cargar })
</script>

<template>
  <p v-if="error" class="rounded bg-red-50 px-3 py-2 text-sm text-red-700">{{ error }}</p>
  <div v-else-if="datos" class="flex flex-col items-center gap-4 sm:flex-row sm:items-start">
    <img v-if="imagen" :src="imagen" alt="QR de cobro" class="rounded-xl border border-slate-200 bg-white p-1 shadow-sm" :class="compacto ? 'w-40' : 'w-56'" />
    <div class="text-sm">
      <p class="text-xs uppercase tracking-[0.25em] text-oro-600">Paga con QR</p>
      <p class="mt-1 font-semibold text-marca-900">{{ datos.banco }}</p>
      <dl class="mt-2 grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-slate-600">
        <dt class="text-slate-400">Cuenta</dt><dd class="font-mono">{{ datos.cuenta }}</dd>
        <dt class="text-slate-400">Destinatario</dt><dd>{{ datos.titular }}</dd>
        <template v-if="datos.glosa"><dt class="text-slate-400">Glosa</dt><dd>{{ datos.glosa }}</dd></template>
        <template v-if="datos.monto"><dt class="text-slate-400">Monto</dt><dd>{{ bs(datos.monto) }} (fijo)</dd></template>
        <template v-if="datos.vence"><dt class="text-slate-400">Vence</dt><dd>{{ fecha(datos.vence) }}</dd></template>
        <dt class="text-slate-400">Uso único</dt><dd>{{ datos.uso_unico ? 'Sí' : 'No' }}</dd>
      </dl>
      <p v-if="datos.prueba" class="mt-2 rounded bg-amber-50 px-2 py-1 text-xs text-amber-800">QR de prueba: no pagues con él hasta que la empresa publique el definitivo.</p>
      <button class="mt-3 rounded border border-marca-900 px-3 py-1.5 text-xs font-medium text-marca-900 hover:bg-slate-50" @click="qrCobro.descargar()">Descargar QR</button>
    </div>
  </div>
  <p v-else class="text-sm text-slate-400">Cargando QR…</p>
</template>
