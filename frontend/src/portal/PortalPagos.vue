<script setup>
import { ref, reactive, onMounted } from 'vue'
import { portal, leerError } from '@/services'
import { bs, fecha } from '@/modulos/comun'

const cuenta = ref(null)
const cuotas = ref([])
const pagos = ref([])
const aviso = ref({ tipo: '', texto: '' })
const copiado = ref(false)

async function cargar() {
  try {
    ;[cuenta.value, cuotas.value, pagos.value] = await Promise.all([portal.cuentaDestino(), portal.cuotas(), portal.pagos()])
  } catch (e) {
    aviso.value = { tipo: 'error', texto: leerError(e).mensaje }
  }
}
onMounted(cargar)

async function abrir(accion) {
  try {
    await accion()
  } catch (e) {
    aviso.value = { tipo: 'error', texto: leerError(e).mensaje }
  }
}

async function copiar() {
  try {
    await navigator.clipboard.writeText(cuenta.value.cuenta_destino)
    copiado.value = true
    setTimeout(() => (copiado.value = false), 2000)
  } catch {
    /* sin portapapeles: el número igual está a la vista */
  }
}

// ---- declarar un pago ----------------------------------------------------------
const cuotaElegida = ref(null)
const form = reactive({ monto_bs: '', fecha_pago: '', nro_transaccion: '', banco_origen: '', observacion: '' })
const archivo = ref(null)
const LIMITE_MB = 4
const errores = ref({})
const enviando = ref(false)
const hoy = new Date().toISOString().slice(0, 10)

function pagar(c) {
  cuotaElegida.value = c
  Object.assign(form, { monto_bs: c.saldo, fecha_pago: hoy, nro_transaccion: '', banco_origen: '', observacion: '' })
  errores.value = {}
  archivo.value = null
}

function elegirArchivo(e) {
  archivo.value = e.target.files?.[0] ?? null
}

function validar() {
  const e = {}
  if (!(Number(form.monto_bs) > 0)) e.monto_bs = 'Escribe un monto mayor a cero.'
  else if (Number(form.monto_bs) > cuotaElegida.value.saldo) e.monto_bs = `El saldo de la cuota es ${bs(cuotaElegida.value.saldo)}.`
  if (!form.fecha_pago) e.fecha_pago = 'Indica la fecha de la transferencia.'
  else if (form.fecha_pago > hoy) e.fecha_pago = 'La fecha no puede ser futura.'
  if (!/^[A-Za-z0-9\-/ ]{4,60}$/.test(form.nro_transaccion)) e.nro_transaccion = 'Escribe el número de transacción (mínimo 4 caracteres, solo letras, números y guiones).'
  if (archivo.value) {
    if (!/\.(jpe?g|png|pdf)$/i.test(archivo.value.name)) e.comprobante = 'Sube una foto (JPG o PNG) o un PDF.'
    else if (archivo.value.size > LIMITE_MB * 1024 * 1024) e.comprobante = `El archivo no puede pesar más de ${LIMITE_MB} MB.`
  }
  errores.value = e
  return !Object.keys(e).length
}

async function enviar() {
  if (!validar()) return
  enviando.value = true
  try {
    const r = await portal.pagar({ ...form, cuota_id: cuotaElegida.value.id, banco_origen: form.banco_origen || null, observacion: form.observacion || null, comprobante: archivo.value })
    cuotaElegida.value = null
    aviso.value = { tipo: 'ok', texto: r.message }
    await cargar()
  } catch (e) {
    const { mensaje, campos } = leerError(e)
    errores.value = campos
    if (!Object.keys(campos).length) aviso.value = { tipo: 'error', texto: mensaje }
  } finally {
    enviando.value = false
  }
}

const chip = {
  EN_VERIFICACION: ['En verificación', 'bg-amber-100 text-amber-800'],
  CONFIRMADO: ['Confirmado', 'bg-emerald-100 text-emerald-800'],
  RECHAZADO: ['Rechazado', 'bg-red-100 text-red-800'],
}
</script>

<template>
  <div>
    <h1 class="font-display text-2xl font-semibold text-marca-900">Pagos</h1>
    <p class="text-sm text-slate-500">Paga tus cuotas por transferencia bancaria y avísanos aquí. Verificamos tu transferencia contra nuestro extracto y confirmamos el pago.</p>

    <p v-if="aviso.texto" class="mt-3 rounded px-3 py-2 text-sm" :class="aviso.tipo === 'ok' ? 'bg-emerald-50 text-emerald-800' : 'bg-red-50 text-red-700'">{{ aviso.texto }}</p>

    <!-- Datos para transferir -->
    <div v-if="cuenta?.cuenta_destino" class="mt-5 overflow-hidden rounded-2xl bg-gradient-to-br from-marca-950 via-marca-900 to-marca-700 p-6 text-white shadow-lg">
      <p class="text-xs uppercase tracking-[0.3em] text-oro-300">Transfiere a esta cuenta</p>
      <div class="mt-3 flex flex-wrap items-center gap-3">
        <span class="font-mono text-2xl tracking-wider">{{ cuenta.cuenta_destino }}</span>
        <button class="rounded border border-oro-400/60 px-3 py-1 text-xs text-oro-200 hover:bg-oro-400 hover:text-marca-950" @click="copiar">{{ copiado ? '¡Copiado!' : 'Copiar' }}</button>
      </div>
      <p class="mt-2 text-sm text-marca-100">{{ cuenta.banco }} · {{ cuenta.titular }} · {{ cuenta.moneda === 'BOB' ? 'Bolivianos' : cuenta.moneda }}</p>
      <p class="mt-3 text-xs text-marca-200/80">Cuenta de pruebas: no hagas transferencias reales hasta que la empresa confirme su cuenta definitiva.</p>
    </div>
    <p v-else class="mt-5 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-800">Los pagos por transferencia todavía no están habilitados.</p>

    <!-- Cuotas -->
    <h2 class="mt-8 text-sm font-semibold uppercase tracking-wide text-slate-500">Estado de cuenta</h2>
    <div class="mt-2 overflow-x-auto rounded-lg bg-white shadow-sm">
      <table class="min-w-full text-left text-sm">
        <thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-4 py-3">Contrato</th><th class="px-4 py-3">Cuota</th><th class="px-4 py-3">Vence</th><th class="px-4 py-3 text-right">Monto</th><th class="px-4 py-3 text-right">Cobrado</th><th class="px-4 py-3 text-right">Saldo</th><th class="px-4 py-3"></th></tr></thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-if="!cuotas.length"><td colspan="7" class="px-4 py-8 text-center text-slate-400">No tienes cuotas registradas.</td></tr>
          <tr v-for="c in cuotas" :key="c.id" :class="c.vencida ? 'bg-rose-50/50' : ''">
            <td class="px-4 py-2">{{ c.contrato }}<div class="text-xs text-slate-400">{{ c.edificio }}</div></td>
            <td class="px-4 py-2">{{ c.nro_cuota }}</td>
            <td class="px-4 py-2">{{ fecha(c.fecha_cuota) }}<span v-if="c.vencida" class="ml-1 text-xs font-semibold text-rose-700">vencida</span></td>
            <td class="px-4 py-2 text-right">{{ bs(c.monto_bs) }}</td>
            <td class="px-4 py-2 text-right">{{ bs(c.cobrado_bs) }}<div v-if="c.en_verificacion_bs" class="text-xs text-amber-700">+ {{ bs(c.en_verificacion_bs) }} en verificación</div></td>
            <td class="px-4 py-2 text-right font-semibold">{{ c.estado_cuota === 'COBRADA' ? 'Pagada' : bs(c.saldo) }}</td>
            <td class="px-4 py-2 text-right"><button v-if="c.saldo > 0 && cuenta?.cuenta_destino" class="rounded bg-marca-900 px-3 py-1 text-xs font-medium text-white hover:bg-marca-700" @click="pagar(c)">Ya transferí</button></td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Historial -->
    <h2 class="mt-8 text-sm font-semibold uppercase tracking-wide text-slate-500">Mis pagos declarados</h2>
    <ul class="mt-2 space-y-2">
      <li v-if="!pagos.length" class="rounded-lg bg-white p-6 text-center text-slate-400 shadow-sm">Todavía no declaraste ningún pago.</li>
      <li v-for="p in pagos" :key="p.id" class="rounded-xl bg-white p-4 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-2">
          <span class="text-sm font-semibold text-marca-900">{{ bs(p.monto_bs) }} <span class="font-normal text-slate-500">· {{ p.contrato }}<span v-if="p.nro_cuota"> · cuota {{ p.nro_cuota }}</span></span></span>
          <span class="rounded-full px-2.5 py-0.5 text-xs font-medium" :class="chip[p.situacion]?.[1]">{{ chip[p.situacion]?.[0] }}</span>
        </div>
        <p class="text-xs text-slate-500">{{ fecha(p.fecha_pago) }} · transacción {{ p.nro_transaccion }}<span v-if="p.banco_origen"> · {{ p.banco_origen }}</span></p>
        <p v-if="p.motivo_rechazo" class="mt-1 text-sm text-red-700">Motivo del rechazo: {{ p.motivo_rechazo }}</p>
        <p class="mt-1 flex gap-4 text-xs">
          <button v-if="p.recibo_disponible" class="font-medium text-marca-600 underline hover:text-oro-600" @click="abrir(() => portal.verRecibo(p.id))">Descargar recibo</button>
          <button v-if="p.tiene_comprobante" class="text-marca-600 underline hover:text-oro-600" @click="abrir(() => portal.verComprobante(p.id))">Ver mi comprobante</button>
        </p>
      </li>
    </ul>

    <!-- Declarar pago -->
    <div v-if="cuotaElegida" class="fixed inset-0 z-20 flex items-start justify-center overflow-y-auto bg-black/50 p-4" @mousedown.self="cuotaElegida = null">
      <form class="my-8 w-full max-w-lg rounded-xl bg-white p-6 shadow-xl" novalidate @submit.prevent="enviar">
        <h2 class="font-display text-xl font-semibold text-marca-900">Declarar transferencia</h2>
        <p class="text-sm text-slate-500">Cuota {{ cuotaElegida.nro_cuota }} · {{ cuotaElegida.contrato }} · saldo {{ bs(cuotaElegida.saldo) }}</p>
        <div class="mt-4 grid gap-4 sm:grid-cols-2">
          <label class="block text-sm"><span class="text-slate-600">Monto transferido (Bs) <span class="text-red-600">*</span></span><input v-model="form.monto_bs" type="number" step="0.01" class="mt-1 w-full rounded border px-3 py-2" :class="errores.monto_bs ? 'border-rose-400' : 'border-slate-300'" /><span v-if="errores.monto_bs" class="text-xs text-red-600">{{ errores.monto_bs }}</span></label>
          <label class="block text-sm"><span class="text-slate-600">Fecha <span class="text-red-600">*</span></span><input v-model="form.fecha_pago" type="date" :max="hoy" class="mt-1 w-full rounded border px-3 py-2" :class="errores.fecha_pago ? 'border-rose-400' : 'border-slate-300'" /><span v-if="errores.fecha_pago" class="text-xs text-red-600">{{ errores.fecha_pago }}</span></label>
          <label class="block text-sm sm:col-span-2"><span class="text-slate-600">Número de transacción / comprobante <span class="text-red-600">*</span></span><input v-model="form.nro_transaccion" class="mt-1 w-full rounded border px-3 py-2" :class="errores.nro_transaccion ? 'border-rose-400' : 'border-slate-300'" /><span v-if="errores.nro_transaccion" class="text-xs text-red-600">{{ errores.nro_transaccion }}</span></label>
          <label class="block text-sm sm:col-span-2"><span class="text-slate-600">Banco desde el que transferiste</span><input v-model="form.banco_origen" class="mt-1 w-full rounded border border-slate-300 px-3 py-2" /></label>
          <label class="block text-sm sm:col-span-2"><span class="text-slate-600">Foto o PDF del comprobante (opcional, máx. {{ LIMITE_MB }} MB)</span><input type="file" accept="image/png,image/jpeg,application/pdf" class="mt-1 w-full text-sm file:mr-3 file:rounded file:border-0 file:bg-marca-900 file:px-3 file:py-2 file:text-white" @change="elegirArchivo" /><span v-if="errores.comprobante" class="text-xs text-red-600">{{ errores.comprobante }}</span></label>
          <label class="block text-sm sm:col-span-2"><span class="text-slate-600">Observación</span><textarea v-model="form.observacion" rows="2" class="mt-1 w-full rounded border border-slate-300 px-3 py-2"></textarea></label>
        </div>
        <div class="mt-5 flex justify-end gap-3">
          <button type="button" class="rounded border border-slate-300 px-4 py-2 text-sm" @click="cuotaElegida = null">Cancelar</button>
          <button :disabled="enviando" class="rounded bg-gradient-to-r from-oro-500 to-oro-400 px-5 py-2 text-sm font-bold text-marca-950 disabled:opacity-60">{{ enviando ? 'Enviando…' : 'Enviar' }}</button>
        </div>
      </form>
    </div>
  </div>
</template>
