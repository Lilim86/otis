<script setup>
import { ref, computed, reactive } from 'vue'
import { cobrador, leerError } from '@/services'
import { bs, fecha } from '@/modulos/comun'

/**
 * Hoja para registrar (o corregir) un cobro hecho en el edificio.
 * Nuevo: recibe `parada` y `cuota`. Corregir: recibe `cobro` (lo que devuelve la API).
 */
const props = defineProps({
  parada: { type: Object, default: null },
  cuota: { type: Object, default: null },
  cobro: { type: Object, default: null },
  cuentas: { type: Array, default: () => [] },
})
const emit = defineEmits(['cerrar', 'guardado'])

const hoy = new Date().toLocaleDateString('sv') // AAAA-MM-DD en la hora del celular
const c = props.cobro

const form = reactive({
  tipo_cobro: c?.tipo_cobro ?? props.cuota?.tipo_sugerido ?? 'MANTENIMIENTO',
  metodo_pago: c?.metodo_pago ?? 'EFECTIVO',
  monto: c ? Number(c.cobrado_bs) : props.cuota?.saldo,
  fecha_cobro: c?.fecha_cobro?.slice(0, 10) ?? hoy,
  nro_recibo: c?.nro_recibo ?? '',
  nro_cheque: c?.nro_cheque ?? '',
  banco_cheque: c?.banco_cheque ?? '',
  nro_transferencia: c?.comprobante_deposito ?? '',
  cuenta_bancaria_id: c?.cuenta_bancaria_id ?? props.cuentas[0]?.id ?? null,
  factura_a_nombre: c?.factura_a_nombre ?? props.parada?.cliente ?? '',
  nit_facturado: c?.nit_facturado ?? props.parada?.nit ?? '',
})

const errores = ref({})
const mensaje = ref('')
const guardando = ref(false)
const listo = ref(null) // cobro guardado: se muestran sus comprobantes

const titulo = computed(() => (c ? 'Corregir cobro' : 'Registrar cobro'))
const edificio = computed(() => c?.contrato?.edificio?.denominacion ?? props.parada?.edificio)
const cliente = computed(() => c?.contrato?.empresa?.denominacion ?? props.parada?.cliente)
const cuotaTexto = computed(() => {
  const q = c?.cuota ?? props.cuota
  return q ? `Cuota ${q.nro_cuota} · vence ${fecha(q.fecha_cuota ?? q.fecha)}` : ''
})

const TIPOS = [
  { codigo: 'MANTENIMIENTO', texto: 'Mantenimiento' },
  { codigo: 'CONTRATO', texto: 'Contrato y renovación' },
]
const METODOS = [
  { codigo: 'EFECTIVO', texto: 'Efectivo', icono: 'M3 7h18v10H3z M12 14.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z' },
  { codigo: 'CHEQUE', texto: 'Cheque', icono: 'M3 6h18v12H3z M6 14h7 M6 10h4 M15 14l3-3' },
  { codigo: 'TRANSFERENCIA', texto: 'Transferencia', icono: 'M4 8h13l-3-3 M20 16H7l3 3' },
]

async function guardar() {
  guardando.value = true
  errores.value = {}
  mensaje.value = ''
  const datos = { ...form }
  if (!c) datos.cuota_id = props.cuota.id
  try {
    const r = c ? { data: await cobrador.actualizar(c.id, datos) } : await cobrador.registrar(datos)
    listo.value = r.data
    emit('guardado', r.data)
  } catch (e) {
    const err = leerError(e)
    errores.value = err.campos
    mensaje.value = err.mensaje
  } finally {
    guardando.value = false
  }
}

const ver = (tipo) => cobrador.verComprobante(listo.value.id, tipo).catch((e) => (mensaje.value = leerError(e).mensaje))
</script>

<template>
  <div class="fixed inset-0 z-40 flex items-end justify-center bg-marca-950/60 sm:items-center sm:p-4" @click.self="emit('cerrar')">
    <div class="max-h-[92vh] w-full max-w-lg overflow-y-auto rounded-t-2xl bg-white shadow-2xl sm:rounded-2xl" role="dialog" aria-modal="true" :aria-label="titulo">
      <div class="sticky top-0 z-10 border-b border-slate-100 bg-white px-5 pb-3 pt-4">
        <div class="mx-auto mb-3 h-1 w-10 rounded-full bg-slate-200 sm:hidden" />
        <div class="flex items-start justify-between gap-3">
          <div>
            <h2 class="text-lg font-bold text-marca-900">{{ listo ? 'Cobro registrado' : titulo }}</h2>
            <p class="text-sm text-slate-500">{{ edificio }} · {{ cliente }}</p>
            <p class="text-xs text-slate-400">{{ cuotaTexto }}</p>
          </div>
          <button class="rounded-lg p-1 text-2xl leading-none text-slate-400 hover:bg-slate-100" aria-label="Cerrar" @click="emit('cerrar')">×</button>
        </div>
      </div>

      <!-- Hecho: ya puede sacar la factura y el recibo -->
      <div v-if="listo" class="space-y-4 p-5">
        <div class="rounded-xl bg-emerald-50 p-4 text-center">
          <div class="text-3xl font-bold tabular-nums text-emerald-700">{{ bs(listo.cobrado_bs) }}</div>
          <div class="mt-1 text-sm text-emerald-800">Factura N° {{ listo.nro_factura }} · Recibo {{ listo.nro_recibo || 'N° asignado por el sistema' }}</div>
          <div class="mt-1 text-xs text-emerald-700/80">Queda en “Recibos” para que lo envíes a Tesorería.</div>
        </div>
        <p v-if="mensaje" class="rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-700">{{ mensaje }}</p>
        <div class="grid grid-cols-2 gap-3">
          <button class="rounded-xl border border-marca-200 py-3 font-semibold text-marca-800 hover:bg-marca-50" @click="ver('factura')">Ver factura</button>
          <button class="rounded-xl border border-marca-200 py-3 font-semibold text-marca-800 hover:bg-marca-50" @click="ver('recibo')">Ver recibo</button>
        </div>
        <button class="w-full rounded-xl bg-marca-900 py-3 font-semibold text-white hover:bg-marca-800" @click="emit('cerrar')">Listo</button>
      </div>

      <form v-else class="space-y-5 p-5" @submit.prevent="guardar">
        <p v-if="mensaje && !Object.keys(errores).length" class="rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-700">{{ mensaje }}</p>

        <fieldset>
          <legend class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-500">¿Qué cobra?</legend>
          <div class="grid grid-cols-2 gap-2">
            <button v-for="t in TIPOS" :key="t.codigo" type="button" class="rounded-xl border-2 px-3 py-2.5 text-sm font-medium" :class="form.tipo_cobro === t.codigo ? 'border-marca-700 bg-marca-50 text-marca-900' : 'border-slate-200 text-slate-600'" :aria-pressed="form.tipo_cobro === t.codigo" @click="form.tipo_cobro = t.codigo">
              {{ t.texto }}
            </button>
          </div>
        </fieldset>

        <fieldset>
          <legend class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-500">¿Cómo pagó el cliente?</legend>
          <div class="grid grid-cols-3 gap-2">
            <button v-for="m in METODOS" :key="m.codigo" type="button" class="flex flex-col items-center gap-1 rounded-xl border-2 px-2 py-3 text-xs font-semibold" :class="form.metodo_pago === m.codigo ? 'border-emerald-600 bg-emerald-50 text-emerald-800' : 'border-slate-200 text-slate-600'" :aria-pressed="form.metodo_pago === m.codigo" @click="form.metodo_pago = m.codigo">
              <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path :d="m.icono" /></svg>
              {{ m.texto }}
            </button>
          </div>
        </fieldset>

        <div v-if="form.metodo_pago === 'CHEQUE'" class="grid grid-cols-2 gap-3 rounded-xl bg-slate-50 p-3">
          <label class="text-sm">Nro. de cheque
            <input v-model.trim="form.nro_cheque" class="mt-1 w-full rounded-lg border-slate-300" inputmode="numeric" />
            <span v-if="errores.nro_cheque" class="text-xs text-rose-600">{{ errores.nro_cheque }}</span>
          </label>
          <label class="text-sm">Banco del cheque
            <input v-model.trim="form.banco_cheque" class="mt-1 w-full rounded-lg border-slate-300" placeholder="BNB, BCP, Unión…" />
            <span v-if="errores.banco_cheque" class="text-xs text-rose-600">{{ errores.banco_cheque }}</span>
          </label>
        </div>
        <div v-if="form.metodo_pago === 'TRANSFERENCIA'" class="grid gap-3 rounded-xl bg-slate-50 p-3 sm:grid-cols-2">
          <label class="text-sm">Nro. de transferencia
            <input v-model.trim="form.nro_transferencia" class="mt-1 w-full rounded-lg border-slate-300" />
            <span v-if="errores.nro_transferencia" class="text-xs text-rose-600">{{ errores.nro_transferencia }}</span>
          </label>
          <label class="text-sm">Cuenta de la empresa
            <select v-model="form.cuenta_bancaria_id" class="mt-1 w-full rounded-lg border-slate-300">
              <option v-for="cu in cuentas" :key="cu.id" :value="cu.id">{{ cu.texto }}</option>
            </select>
            <span v-if="errores.cuenta_bancaria_id" class="text-xs text-rose-600">{{ errores.cuenta_bancaria_id }}</span>
          </label>
        </div>

        <div class="grid grid-cols-2 gap-3">
          <label class="text-sm">Monto cobrado (Bs)
            <input v-model.number="form.monto" type="number" step="0.01" min="0.01" class="mt-1 w-full rounded-lg border-slate-300 text-lg font-semibold tabular-nums" />
            <span v-if="cuota && !c" class="text-xs text-slate-400">Debe {{ bs(cuota.saldo) }}</span>
            <span v-if="errores.monto" class="block text-xs text-rose-600">{{ errores.monto }}</span>
          </label>
          <label class="text-sm">Fecha del cobro
            <input v-model="form.fecha_cobro" type="date" :max="hoy" class="mt-1 w-full rounded-lg border-slate-300" />
            <span v-if="errores.fecha_cobro" class="text-xs text-rose-600">{{ errores.fecha_cobro }}</span>
          </label>
        </div>

        <label class="block text-sm">Nro. del recibo (talonario)
          <input v-model.trim="form.nro_recibo" class="mt-1 w-full rounded-lg border-slate-300" placeholder="Si lo dejas vacío, el sistema lo numera" />
          <span v-if="errores.nro_recibo" class="text-xs text-rose-600">{{ errores.nro_recibo }}</span>
        </label>

        <details class="rounded-xl border border-slate-200 p-3 text-sm">
          <summary class="cursor-pointer font-medium text-slate-700">Datos de la factura</summary>
          <div class="mt-3 grid gap-3 sm:grid-cols-2">
            <label>A nombre de
              <input v-model.trim="form.factura_a_nombre" class="mt-1 w-full rounded-lg border-slate-300" />
            </label>
            <label>NIT / CI
              <input v-model.trim="form.nit_facturado" class="mt-1 w-full rounded-lg border-slate-300" />
            </label>
          </div>
        </details>

        <button type="submit" :disabled="guardando" class="w-full rounded-xl bg-emerald-600 py-3.5 text-base font-bold text-white shadow-sm hover:bg-emerald-700 disabled:opacity-60">
          {{ guardando ? 'Guardando…' : c ? 'Guardar corrección' : `Cobrar ${bs(form.monto || 0)} y emitir factura` }}
        </button>
      </form>
    </div>
  </div>
</template>
