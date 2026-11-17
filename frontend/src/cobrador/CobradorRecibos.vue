<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { cobrador, leerError } from '@/services'
import { useCobradorStore } from '@/stores/cobrador'
import { bs, fecha } from '@/modulos/comun'
import CobroFormulario from '@/components/CobroFormulario.vue'

const datos = useCobradorStore()
const cargando = ref(true)
const aviso = ref({ tipo: '', texto: '' })
const filtro = ref('ENTREGAR')
const elegidos = ref(new Set())
const corrigiendo = ref(null)
const enviando = ref(false)

const FILTROS = [
  { codigo: 'ENTREGAR', texto: 'Por entregar', situaciones: ['PENDIENTE', 'OBSERVADO'] },
  { codigo: 'ENVIADO', texto: 'En Tesorería', situaciones: ['ENVIADO'] },
  { codigo: 'APROBADO', texto: 'Aprobados', situaciones: ['APROBADO'] },
]
const SITUACION = {
  PENDIENTE: { texto: 'Por enviar', clase: 'bg-amber-100 text-amber-800' },
  OBSERVADO: { texto: 'Observado', clase: 'bg-rose-100 text-rose-800' },
  ENVIADO: { texto: 'En Tesorería', clase: 'bg-sky-100 text-sky-800' },
  APROBADO: { texto: 'Aprobado', clase: 'bg-emerald-100 text-emerald-800' },
}
const METODO = { EFECTIVO: 'Efectivo', CHEQUE: 'Cheque', TRANSFERENCIA: 'Transferencia' }
const TIPO = { MANTENIMIENTO: 'Mantenimiento', CONTRATO: 'Contrato y renovación' }

async function cargar() {
  cargando.value = true
  try {
    await Promise.all([datos.cargarCobros(), datos.cuentas.length ? null : datos.cargarRuta()])
  } catch (e) {
    aviso.value = { tipo: 'error', texto: leerError(e).mensaje }
  } finally {
    cargando.value = false
  }
}
onMounted(cargar)

const actual = computed(() => FILTROS.find((f) => f.codigo === filtro.value))
const lista = computed(() => datos.cobros.filter((c) => actual.value.situaciones.includes(c.situacion_tesoreria)))
const cuenta = (f) => datos.cobros.filter((c) => f.situaciones.includes(c.situacion_tesoreria)).length
const porEntregar = computed(() => filtro.value === 'ENTREGAR')

// Por defecto se entrega todo lo pendiente.
watch(
  () => datos.porEnviar.map((c) => c.id).join(),
  () => (elegidos.value = new Set(datos.porEnviar.map((c) => c.id))),
  { immediate: true },
)

const seleccion = computed(() => datos.porEnviar.filter((c) => elegidos.value.has(c.id)))
const totales = computed(() =>
  Object.keys(METODO).map((m) => {
    const de = seleccion.value.filter((c) => c.metodo_pago === m)
    return { metodo: m, cantidad: de.length, total: de.reduce((s, c) => s + Number(c.cobrado_bs), 0) }
  }),
)
const totalSeleccion = computed(() => totales.value.reduce((s, t) => s + t.total, 0))

function alternar(id) {
  const s = new Set(elegidos.value)
  s.has(id) ? s.delete(id) : s.add(id)
  elegidos.value = s
}

async function enviar() {
  if (!seleccion.value.length) return
  enviando.value = true
  try {
    const r = await cobrador.enviar(seleccion.value.map((c) => c.id))
    aviso.value = { tipo: 'ok', texto: r.message }
    await datos.cargarCobros()
  } catch (e) {
    aviso.value = { tipo: 'error', texto: leerError(e).mensaje }
  } finally {
    enviando.value = false
  }
}

async function borrar(c) {
  if (!confirm(`¿Borrar el cobro de ${bs(c.cobrado_bs)} en ${c.contrato?.edificio?.denominacion}? La cuota vuelve a quedar por cobrar.`)) return
  try {
    await cobrador.eliminar(c.id)
    aviso.value = { tipo: 'ok', texto: 'Cobro borrado.' }
    await datos.recargar()
  } catch (e) {
    aviso.value = { tipo: 'error', texto: leerError(e).mensaje }
  }
}

const ver = (c, tipo) => cobrador.verComprobante(c.id, tipo).catch((e) => (aviso.value = { tipo: 'error', texto: leerError(e).mensaje }))

function detalleMetodo(c) {
  if (c.metodo_pago === 'CHEQUE') return `Cheque N° ${c.nro_cheque} · ${c.banco_cheque}`
  if (c.metodo_pago === 'TRANSFERENCIA') return `Transferencia N° ${c.comprobante_deposito}`
  return METODO[c.metodo_pago] ?? '—'
}

async function alCorregir() {
  aviso.value = { tipo: 'ok', texto: 'Cobro corregido. Recuerda enviarlo de nuevo a Tesorería.' }
  await datos.recargar().catch(() => {})
}
</script>

<template>
  <div class="space-y-4">
    <div>
      <h1 class="text-xl font-bold text-marca-900">Mis recibos</h1>
      <p class="text-sm text-slate-500">Lo que cobraste en los edificios. Al final de la jornada envíalo a Tesorería; ellos lo aprueban y Finanzas manda el recibo al cliente.</p>
    </div>

    <p v-if="aviso.texto" class="rounded-lg px-3 py-2 text-sm" :class="aviso.tipo === 'error' ? 'bg-rose-50 text-rose-700' : 'bg-emerald-50 text-emerald-800'" role="status">{{ aviso.texto }}</p>

    <div class="flex gap-1 overflow-x-auto rounded-xl bg-white p-1 shadow-sm">
      <button v-for="f in FILTROS" :key="f.codigo" class="flex-1 whitespace-nowrap rounded-lg px-3 py-2 text-sm" :class="filtro === f.codigo ? 'bg-marca-900 font-semibold text-white' : 'text-slate-600 hover:bg-slate-100'" @click="filtro = f.codigo">
        {{ f.texto }} <span class="ml-1 rounded-full px-1.5 text-xs" :class="filtro === f.codigo ? 'bg-white/20' : 'bg-slate-100'">{{ cuenta(f) }}</span>
      </button>
    </div>

    <!-- Rendición del día -->
    <section v-if="porEntregar && datos.porEnviar.length" class="rounded-2xl border-2 border-dashed border-emerald-300 bg-emerald-50/60 p-4">
      <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
          <div class="text-xs font-semibold uppercase tracking-wider text-emerald-800">Entregar a Tesorería</div>
          <div class="text-2xl font-bold tabular-nums text-emerald-900">{{ bs(totalSeleccion) }}</div>
          <div class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-xs text-emerald-900/80">
            <span v-for="t in totales" :key="t.metodo">{{ METODO[t.metodo] }}: <b>{{ bs(t.total) }}</b> ({{ t.cantidad }})</span>
          </div>
        </div>
        <button class="rounded-xl bg-emerald-600 px-5 py-3 font-bold text-white shadow-sm hover:bg-emerald-700 disabled:opacity-50" :disabled="!seleccion.length || enviando" @click="enviar">
          {{ enviando ? 'Enviando…' : `Enviar ${seleccion.length} a Tesorería` }}
        </button>
      </div>
    </section>

    <p v-if="cargando && !datos.cobros.length" class="py-10 text-center text-slate-400">Cargando…</p>
    <p v-else-if="!lista.length" class="rounded-2xl bg-white p-8 text-center text-slate-500 shadow-sm">
      {{ porEntregar ? 'No tienes cobros por entregar. Registra uno desde “Mi ruta”.' : 'No hay cobros aquí todavía.' }}
    </p>

    <ul class="space-y-3">
      <li v-for="c in lista" :key="c.id" class="rounded-2xl bg-white p-4 shadow-sm">
        <div class="flex gap-3">
          <input v-if="porEntregar" type="checkbox" class="mt-1 size-5 rounded border-slate-300 text-emerald-600" :checked="elegidos.has(c.id)" :aria-label="`Incluir el cobro de ${c.contrato?.edificio?.denominacion}`" @change="alternar(c.id)" />
          <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-start justify-between gap-2">
              <div class="min-w-0">
                <div class="font-bold text-marca-900">{{ c.contrato?.edificio?.denominacion }}</div>
                <div class="text-sm text-slate-600">{{ c.contrato?.empresa?.denominacion }}</div>
              </div>
              <div class="text-right">
                <div class="text-lg font-bold tabular-nums text-marca-900">{{ bs(c.cobrado_bs) }}</div>
                <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="SITUACION[c.situacion_tesoreria]?.clase">{{ SITUACION[c.situacion_tesoreria]?.texto }}</span>
              </div>
            </div>
            <div class="mt-2 grid gap-x-4 gap-y-0.5 text-xs text-slate-500 sm:grid-cols-2">
              <span>{{ TIPO[c.tipo_cobro] ?? c.concepto }} · Cuota {{ c.cuota?.nro_cuota }}</span>
              <span>{{ detalleMetodo(c) }}</span>
              <span>Cobrado el {{ fecha(c.fecha_cobro) }}</span>
              <span>Factura N° {{ c.nro_factura }} · Recibo {{ c.nro_recibo || `N° ${String(c.id).padStart(8, '0')}` }}</span>
            </div>
            <p v-if="c.situacion_tesoreria === 'OBSERVADO'" class="mt-2 rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-800"><b>Tesorería observa:</b> {{ c.observacion_tesoreria }}</p>

            <div class="mt-3 flex flex-wrap gap-2 text-sm">
              <button class="rounded-lg border border-slate-200 px-3 py-1.5 hover:bg-slate-50" @click="ver(c, 'factura')">Factura</button>
              <button class="rounded-lg border border-slate-200 px-3 py-1.5 hover:bg-slate-50" @click="ver(c, 'recibo')">Recibo</button>
              <template v-if="porEntregar">
                <button class="rounded-lg border border-marca-200 px-3 py-1.5 text-marca-700 hover:bg-marca-50" @click="corrigiendo = c">Corregir</button>
                <button class="rounded-lg px-3 py-1.5 text-rose-700 hover:bg-rose-50" @click="borrar(c)">Borrar</button>
              </template>
            </div>
          </div>
        </div>
      </li>
    </ul>

    <CobroFormulario v-if="corrigiendo" :cobro="corrigiendo" :cuentas="datos.cuentas" @guardado="alCorregir" @cerrar="corrigiendo = null" />
  </div>
</template>
