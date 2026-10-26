<script setup>
import { ref, reactive, onMounted, watch } from 'vue'
import { portal, leerError } from '@/services'
import { fecha } from '@/modulos/comun'
import FirmaPad from '@/components/FirmaPad.vue'

const visitas = ref([])
const contratos = ref([])
const filtros = reactive({ contrato_id: '', situacion: '', tipo: '' })
const cargando = ref(false)
const aviso = ref({ tipo: '', texto: '' })

const colores = {
  PROGRAMADA: 'bg-marca-100 text-marca-800',
  SOLICITADA: 'bg-oro-100 text-oro-800',
  REALIZADA: 'bg-emerald-100 text-emerald-800',
  REPROGRAMADA: 'bg-amber-100 text-amber-800',
  CANCELADA: 'bg-slate-200 text-slate-600',
}
const nombres = { PROGRAMADA: 'Programada', SOLICITADA: 'Solicitada', REALIZADA: 'Realizada', REPROGRAMADA: 'Reprogramada', CANCELADA: 'Cancelada' }

async function cargar() {
  cargando.value = true
  try {
    visitas.value = await portal.mantenimientos(Object.fromEntries(Object.entries(filtros).filter(([, v]) => v)))
  } catch (e) {
    aviso.value = { tipo: 'error', texto: leerError(e).mensaje }
  } finally {
    cargando.value = false
  }
}
onMounted(async () => {
  contratos.value = await portal.contratos().catch(() => [])
  cargar()
})
watch(filtros, cargar)

// ---- solicitar un servicio -----------------------------------------------------
const modal = ref(false)
const form = reactive({ contrato_id: '', descripcion: '', urgente: false, fecha_deseada: '' })
const errores = ref({})
const enviando = ref(false)

function abrir() {
  Object.assign(form, { contrato_id: contratos.value.find((c) => c.vigente)?.id ?? '', descripcion: '', urgente: false, fecha_deseada: '' })
  errores.value = {}
  modal.value = true
}
async function solicitar() {
  enviando.value = true
  errores.value = {}
  try {
    await portal.solicitarMantenimiento({ ...form, fecha_deseada: form.fecha_deseada || null })
    modal.value = false
    aviso.value = { tipo: 'ok', texto: 'Solicitud enviada. Nos comunicaremos contigo para coordinar la visita.' }
    cargar()
  } catch (e) {
    const { mensaje, campos } = leerError(e)
    errores.value = campos
    if (!Object.keys(campos).length) aviso.value = { tipo: 'error', texto: mensaje }
  } finally {
    enviando.value = false
  }
}

// ---- calificar una visita --------------------------------------------------------
const calificando = ref(null)
const nota = reactive({ calificacion: 5, comentario: '' })
async function calificar() {
  try {
    await portal.calificar(calificando.value.id, { calificacion: nota.calificacion, comentario: nota.comentario || null })
    calificando.value = null
    aviso.value = { tipo: 'ok', texto: '¡Gracias por tu opinión!' }
    cargar()
  } catch (e) {
    aviso.value = { tipo: 'error', texto: leerError(e).mensaje }
  }
}

// ---- firmar la conformidad -----------------------------------------------------------
const firmando = ref(null)
const firma = ref(null)
const firmadoPor = ref('')
const errorFirma = ref('')
const pad = ref(null)

function abrirFirma(v) {
  firmando.value = v
  firma.value = null
  firmadoPor.value = ''
  errorFirma.value = ''
}
async function enviarFirma() {
  errorFirma.value = ''
  if (!firma.value) return (errorFirma.value = 'Dibuja tu firma en el recuadro.')
  if (firmadoPor.value.trim().length < 3) return (errorFirma.value = 'Escribe tu nombre completo.')
  try {
    await portal.firmar(firmando.value.id, firma.value, firmadoPor.value.trim())
    firmando.value = null
    aviso.value = { tipo: 'ok', texto: 'Gracias, registramos tu conformidad.' }
    cargar()
  } catch (e) {
    const { mensaje, campos } = leerError(e)
    errorFirma.value = Object.values(campos)[0] ?? mensaje
  }
}
async function verInforme(v) {
  try {
    await portal.verInforme(v.id)
  } catch (e) {
    aviso.value = { tipo: 'error', texto: leerError(e).mensaje }
  }
}
</script>

<template>
  <div>
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h1 class="font-display text-2xl font-semibold text-marca-900">Mantenimiento</h1>
        <p class="text-sm text-slate-500">Historial de visitas, informes de los técnicos y tus solicitudes.</p>
      </div>
      <button class="rounded-lg bg-gradient-to-r from-oro-500 to-oro-400 px-5 py-2.5 text-xs font-bold uppercase tracking-widest text-marca-950 shadow" @click="abrir">Reportar una falla</button>
    </div>

    <p v-if="aviso.texto" class="mt-3 rounded px-3 py-2 text-sm" :class="aviso.tipo === 'ok' ? 'bg-emerald-50 text-emerald-800' : 'bg-red-50 text-red-700'">{{ aviso.texto }}</p>

    <div class="mt-4 flex flex-wrap gap-3 rounded-lg bg-white p-3 shadow-sm">
      <select v-model="filtros.contrato_id" class="rounded border border-slate-300 px-2 py-2 text-sm"><option value="">Todos los contratos</option><option v-for="c in contratos" :key="c.id" :value="c.id">{{ c.codigo }} · {{ c.edificio }}</option></select>
      <select v-model="filtros.tipo" class="rounded border border-slate-300 px-2 py-2 text-sm"><option value="">Preventivos y correctivos</option><option value="PREVENTIVO">Preventivos</option><option value="CORRECTIVO">Correctivos</option></select>
      <select v-model="filtros.situacion" class="rounded border border-slate-300 px-2 py-2 text-sm"><option value="">Todas las situaciones</option><option v-for="(n, k) in nombres" :key="k" :value="k">{{ n }}</option></select>
    </div>

    <p v-if="cargando" class="mt-6 text-slate-400">Cargando…</p>
    <p v-else-if="!visitas.length" class="mt-6 rounded-lg bg-white p-8 text-center text-slate-400 shadow-sm">Aún no hay visitas registradas.</p>
    <ul v-else class="mt-4 space-y-3">
      <li v-for="v in visitas" :key="v.id" class="rounded-xl bg-white p-4 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-2">
          <div class="text-sm font-semibold text-marca-900">
            {{ v.tipo === 'PREVENTIVO' ? 'Mantenimiento preventivo' : 'Servicio correctivo' }}<span v-if="v.nro_visita"> #{{ v.nro_visita }}</span>
            <span class="font-normal text-slate-500"> · {{ v.edificio }}</span>
          </div>
          <span class="rounded-full px-2.5 py-0.5 text-xs font-medium" :class="colores[v.situacion]">{{ nombres[v.situacion] ?? v.situacion }}</span>
        </div>
        <p class="mt-1 text-xs text-slate-500">
          Programada {{ fecha(v.fecha_programada) }}<span v-if="v.fecha_realizada"> · realizada {{ fecha(v.fecha_realizada) }}</span><span v-if="v.tecnico"> · técnico {{ v.tecnico }}</span>
        </p>
        <p v-if="v.descripcion" class="mt-2 text-sm text-slate-700">{{ v.descripcion }}</p>
        <p v-if="v.informe" class="mt-2 rounded bg-slate-50 p-3 text-sm text-slate-600"><strong>Informe técnico:</strong> {{ v.informe }}</p>
        <div v-if="v.situacion === 'REALIZADA'" class="mt-2 text-sm">
          <span v-if="v.calificacion" class="text-oro-600">{{ '★'.repeat(v.calificacion) }}<span class="text-slate-300">{{ '★'.repeat(5 - v.calificacion) }}</span> <span class="text-slate-500">{{ v.comentario_cliente }}</span></span>
          <button v-else class="font-medium text-marca-600 underline hover:text-oro-600" @click="calificando = v">Calificar esta visita</button>
        </div>
        <p v-if="v.situacion === 'REALIZADA'" class="mt-2 flex flex-wrap items-center gap-4 text-sm">
          <span v-if="v.firmada" class="text-emerald-700">✔ Conformidad firmada por {{ v.firmado_por }}</span>
          <button v-else class="font-medium text-marca-600 underline hover:text-oro-600" @click="abrirFirma(v)">Firmar conformidad</button>
          <button class="text-marca-600 underline hover:text-oro-600" @click="verInforme(v)">Informe en PDF</button>
        </p>
      </li>
    </ul>

    <!-- Solicitud -->
    <div v-if="modal" class="fixed inset-0 z-20 flex items-start justify-center overflow-y-auto bg-black/50 p-4" @mousedown.self="modal = false">
      <form class="my-8 w-full max-w-lg rounded-xl bg-white p-6 shadow-xl" novalidate @submit.prevent="solicitar">
        <h2 class="font-display text-xl font-semibold text-marca-900">Reportar una falla</h2>
        <label class="mt-4 block text-sm"><span class="text-slate-600">Contrato <span class="text-red-600">*</span></span>
          <select v-model="form.contrato_id" class="mt-1 w-full rounded border border-slate-300 px-3 py-2"><option value="">— Elegir —</option><option v-for="c in contratos.filter((x) => x.vigente)" :key="c.id" :value="c.id">{{ c.codigo }} · {{ c.edificio }}</option></select>
          <span v-if="errores.contrato_id" class="text-xs text-red-600">{{ errores.contrato_id }}</span>
        </label>
        <label class="mt-3 block text-sm"><span class="text-slate-600">¿Qué está pasando? <span class="text-red-600">*</span></span>
          <textarea v-model="form.descripcion" rows="4" placeholder="Ej: La puerta de la cabina no cierra bien en el piso 3" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 focus:border-oro-500 focus:outline-none"></textarea>
          <span v-if="errores.descripcion" class="text-xs text-red-600">{{ errores.descripcion }}</span>
        </label>
        <label class="mt-3 block text-sm"><span class="text-slate-600">Fecha en que prefieres la visita (opcional)</span>
          <input v-model="form.fecha_deseada" type="date" class="mt-1 w-full rounded border border-slate-300 px-3 py-2" />
          <span v-if="errores.fecha_deseada" class="text-xs text-red-600">{{ errores.fecha_deseada }}</span>
        </label>
        <label class="mt-3 flex items-center gap-2 text-sm text-slate-700"><input v-model="form.urgente" type="checkbox" /> Es urgente (ascensor detenido o con personas atrapadas)</label>
        <div class="mt-5 flex justify-end gap-3">
          <button type="button" class="rounded border border-slate-300 px-4 py-2 text-sm" @click="modal = false">Cancelar</button>
          <button :disabled="enviando" class="rounded bg-marca-900 px-4 py-2 text-sm font-medium text-white hover:bg-marca-700 disabled:opacity-60">{{ enviando ? 'Enviando…' : 'Enviar solicitud' }}</button>
        </div>
      </form>
    </div>

    <!-- Firma -->
    <div v-if="firmando" class="fixed inset-0 z-20 flex items-center justify-center bg-black/50 p-4" @mousedown.self="firmando = null">
      <form class="w-full max-w-lg rounded-xl bg-white p-6 shadow-xl" novalidate @submit.prevent="enviarFirma">
        <h2 class="font-display text-xl font-semibold text-marca-900">Firmar conformidad</h2>
        <p class="text-sm text-slate-500">Visita del {{ fecha(firmando.fecha_realizada) }} · {{ firmando.edificio }}. Con tu firma confirmas que el trabajo se realizó.</p>
        <div class="mt-4"><FirmaPad ref="pad" @cambio="firma = $event" /></div>
        <label class="mt-3 block text-sm"><span class="text-slate-600">Tu nombre completo <span class="text-red-600">*</span></span><input v-model="firmadoPor" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 focus:border-oro-500 focus:outline-none" /></label>
        <p v-if="errorFirma" class="mt-2 text-xs text-red-600">{{ errorFirma }}</p>
        <div class="mt-4 flex justify-end gap-3">
          <button type="button" class="rounded border border-slate-300 px-4 py-2 text-sm" @click="firmando = null">Cancelar</button>
          <button class="rounded bg-marca-900 px-4 py-2 text-sm font-medium text-white hover:bg-marca-700">Firmar</button>
        </div>
      </form>
    </div>

    <!-- Calificación -->
    <div v-if="calificando" class="fixed inset-0 z-20 flex items-center justify-center bg-black/50 p-4" @mousedown.self="calificando = null">
      <form class="w-full max-w-sm rounded-xl bg-white p-6 shadow-xl" @submit.prevent="calificar">
        <h2 class="font-display text-xl font-semibold text-marca-900">¿Cómo fue el servicio?</h2>
        <div class="mt-4 flex justify-center gap-1 text-3xl">
          <button v-for="n in 5" :key="n" type="button" :class="n <= nota.calificacion ? 'text-oro-500' : 'text-slate-300'" :aria-label="`${n} estrellas`" @click="nota.calificacion = n">★</button>
        </div>
        <textarea v-model="nota.comentario" rows="3" placeholder="Comentario (opcional)" class="mt-4 w-full rounded border border-slate-300 px-3 py-2 text-sm"></textarea>
        <div class="mt-4 flex justify-end gap-3">
          <button type="button" class="rounded border border-slate-300 px-4 py-2 text-sm" @click="calificando = null">Cancelar</button>
          <button class="rounded bg-marca-900 px-4 py-2 text-sm font-medium text-white hover:bg-marca-700">Enviar</button>
        </div>
      </form>
    </div>
  </div>
</template>
