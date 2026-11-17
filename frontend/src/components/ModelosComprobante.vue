<script setup>
import { ref, reactive } from 'vue'
import { comprobantes, qrCobro, leerError } from '@/services'
import { useSesionStore } from '@/stores/sesion'
import QrCobro from './QrCobro.vue'

const sesion = useSesionStore()

// ---- QR de cobro: Cobranzas lo ve y lo reemplaza; el cliente lo ve en su portal ----
const abierto = ref(false)
const editando = ref(false)
const tarjeta = ref(null)
const form = reactive({ banco: '', cuenta: '', titular: '', glosa: '', monto: '', vence: '', uso_unico: false, prueba: false })
const archivo = ref(null)
const errores = ref({})
const aviso = ref('')
const guardando = ref(false)

async function editar() {
  const d = await qrCobro.datos()
  Object.assign(form, { banco: d.banco ?? '', cuenta: d.cuenta ?? '', titular: d.titular ?? '', glosa: d.glosa ?? '', monto: d.monto ?? '', vence: d.vence ?? '', uso_unico: !!d.uso_unico, prueba: !!d.prueba })
  archivo.value = null
  errores.value = {}
  editando.value = true
}

async function guardar() {
  guardando.value = true
  errores.value = {}
  try {
    aviso.value = (await qrCobro.guardar({ ...form, qr: archivo.value })).message
    editando.value = false
    await tarjeta.value?.cargar()
  } catch (e) {
    const { mensaje, campos } = leerError(e)
    errores.value = Object.keys(campos).length ? campos : { general: mensaje }
  } finally {
    guardando.value = false
  }
}

async function restablecer() {
  if (!window.confirm('¿Volver al QR de prueba?')) return
  aviso.value = (await qrCobro.restablecer()).message
  await tarjeta.value?.cargar()
}
</script>

<template>
  <div class="flex flex-wrap items-center gap-2 text-sm">
    <template v-if="sesion.puede('comprobantes.ver')">
      <span class="text-slate-500">Formatos:</span>
      <button class="rounded border border-oro-500 px-3 py-2 font-medium text-marca-900 hover:bg-oro-50" @click="comprobantes.modelo('factura')">Factura (modelo)</button>
      <button class="rounded border border-oro-500 px-3 py-2 font-medium text-marca-900 hover:bg-oro-50" @click="comprobantes.modelo('recibo')">Recibo en blanco</button>
    </template>
    <button class="rounded bg-marca-900 px-3 py-2 font-medium text-white hover:bg-marca-700" @click="abierto = true; aviso = ''">QR de cobro</button>
  </div>

  <div v-if="abierto" class="fixed inset-0 z-30 flex items-start justify-center overflow-y-auto bg-black/50 p-4" @mousedown.self="abierto = false">
    <div class="my-8 w-full max-w-xl rounded-xl bg-white p-6 shadow-xl">
      <div class="flex items-start justify-between">
        <div>
          <h2 class="text-lg font-semibold text-marca-900">QR de cobro</h2>
          <p class="text-xs text-slate-500">Es el que ven los clientes en su portal, en la pantalla de Pagos.</p>
        </div>
        <button class="text-slate-400 hover:text-slate-700" aria-label="Cerrar" @click="abierto = false">✕</button>
      </div>
      <p v-if="aviso" class="mt-3 rounded bg-emerald-50 px-3 py-2 text-sm text-emerald-800">{{ aviso }}</p>

      <div class="mt-4"><QrCobro ref="tarjeta" compacto /></div>

      <div v-if="sesion.puede('cobranzas.crear') && !editando" class="mt-5 flex gap-2 border-t border-slate-100 pt-4">
        <button class="rounded bg-marca-900 px-4 py-2 text-sm font-medium text-white hover:bg-marca-700" @click="editar">Cambiar QR</button>
        <button v-if="sesion.puede('cobranzas.eliminar')" class="rounded border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50" @click="restablecer">Volver al de prueba</button>
      </div>

      <form v-if="editando" class="mt-5 grid gap-3 border-t border-slate-100 pt-4 sm:grid-cols-2" @submit.prevent="guardar">
        <p v-if="errores.general" class="text-sm text-red-700 sm:col-span-2">{{ errores.general }}</p>
        <label class="block text-sm sm:col-span-2"><span class="text-slate-600">Imagen del QR que da el banco (PNG o JPG, máx. 2 MB). Déjalo vacío para conservar la actual.</span>
          <input type="file" accept="image/png,image/jpeg" class="mt-1 w-full text-sm file:mr-3 file:rounded file:border-0 file:bg-marca-900 file:px-3 file:py-2 file:text-white" @change="archivo = $event.target.files?.[0] ?? null" />
          <span v-if="errores.qr" class="text-xs text-red-600">{{ errores.qr }}</span>
        </label>
        <label v-for="c in [['banco', 'Banco'], ['cuenta', 'Número de cuenta'], ['titular', 'Destinatario'], ['glosa', 'Glosa']]" :key="c[0]" class="block text-sm">
          <span class="text-slate-600">{{ c[1] }}</span>
          <input v-model="form[c[0]]" class="mt-1 w-full rounded border border-slate-300 px-3 py-2" />
          <span v-if="errores[c[0]]" class="text-xs text-red-600">{{ errores[c[0]] }}</span>
        </label>
        <label class="block text-sm"><span class="text-slate-600">Monto fijo (vacío si el cliente escribe el monto)</span><input v-model="form.monto" type="number" step="0.01" min="0" class="mt-1 w-full rounded border border-slate-300 px-3 py-2" /></label>
        <label class="block text-sm"><span class="text-slate-600">Vence</span><input v-model="form.vence" type="date" class="mt-1 w-full rounded border border-slate-300 px-3 py-2" /></label>
        <label class="flex items-center gap-2 text-sm"><input v-model="form.uso_unico" type="checkbox" /> Uso único</label>
        <label class="flex items-center gap-2 text-sm"><input v-model="form.prueba" type="checkbox" /> Es un QR de prueba</label>
        <div class="flex justify-end gap-2 sm:col-span-2">
          <button type="button" class="rounded border border-slate-300 px-4 py-2 text-sm" @click="editando = false">Cancelar</button>
          <button :disabled="guardando" class="rounded bg-marca-900 px-4 py-2 text-sm font-medium text-white disabled:opacity-60">{{ guardando ? 'Guardando…' : 'Guardar' }}</button>
        </div>
      </form>
    </div>
  </div>
</template>
