<script setup>
import { ref, onMounted, watch } from 'vue'
import { cobrosCobradores, cuentasBancarias, comprobantes, leerError } from '@/services'
import { useSesionStore } from '@/stores/sesion'
import { bs, fecha, fechaHora } from '@/modulos/comun'

const sesion = useSesionStore()
const puedeAprobar = sesion.puede('ingresos-tesoreria.aprobar')

const PESTANAS = [
  { codigo: 'ENVIADO', texto: 'Por aprobar' },
  { codigo: 'OBSERVADO', texto: 'Observados' },
  { codigo: 'APROBADO', texto: 'Aprobados' },
]
const METODO = { EFECTIVO: 'Efectivo', CHEQUE: 'Cheque', TRANSFERENCIA: 'Transferencia' }
const TIPO = { MANTENIMIENTO: 'Mantenimiento', CONTRATO: 'Contrato y renovación' }

const pestana = ref('ENVIADO')
const grupos = ref([])
const porAprobar = ref(0)
const cuentas = ref([])
const cargando = ref(false)
const aviso = ref({ tipo: '', texto: '' })
// Fila en revisión: { id, accion: 'aprobar' | 'observar', cuenta, motivo }
const revision = ref(null)
const ocupado = ref(false)

async function cargar() {
  cargando.value = true
  try {
    const r = await cobrosCobradores.listar(pestana.value)
    grupos.value = r.data
    porAprobar.value = r.por_aprobar
  } catch (e) {
    aviso.value = { tipo: 'error', texto: leerError(e).mensaje }
  } finally {
    cargando.value = false
  }
}

onMounted(async () => {
  await cargar()
  cuentas.value = await cuentasBancarias.todos({ estado: 1 }).catch(() => [])
})
watch(pestana, () => {
  revision.value = null
  cargar()
})

const cuentaTexto = (c) => `${c.banco?.sigla || c.banco?.nombre || 'Banco'} · ${c.numero_cuenta} (${c.moneda})`

function detalleMetodo(c) {
  if (c.metodo_pago === 'CHEQUE') return `Cheque N° ${c.nro_cheque} · ${c.banco_cheque}`
  if (c.metodo_pago === 'TRANSFERENCIA') return `Transf. N° ${c.comprobante_deposito}${c.cuenta_bancaria ? ` → ${cuentaTexto(c.cuenta_bancaria)}` : ''}`
  return 'Efectivo'
}

function abrir(c, accion) {
  revision.value = { id: c.id, accion, cuenta: c.cuenta_bancaria_id ?? cuentas.value[0]?.id ?? null, motivo: '' }
}

async function confirmar() {
  const r = revision.value
  ocupado.value = true
  try {
    const res = r.accion === 'aprobar' ? await cobrosCobradores.aprobar(r.id, r.cuenta) : await cobrosCobradores.observar(r.id, r.motivo)
    aviso.value = { tipo: 'ok', texto: res.message }
    revision.value = null
    await cargar()
  } catch (e) {
    const err = leerError(e)
    aviso.value = { tipo: 'error', texto: Object.values(err.campos)[0] ?? err.mensaje }
  } finally {
    ocupado.value = false
  }
}

const ver = (c, tipo) => comprobantes.ver(c.id, tipo).catch((e) => (aviso.value = { tipo: 'error', texto: leerError(e).mensaje }))

async function enviarAlCliente(c) {
  if (!confirm(`¿Enviar el recibo por correo a ${c.contrato?.empresa?.denominacion}?`)) return
  try {
    const r = await comprobantes.enviar(c.id, 'recibo')
    aviso.value = { tipo: 'ok', texto: r.message ?? 'Recibo enviado al cliente.' }
  } catch (e) {
    aviso.value = { tipo: 'error', texto: leerError(e).mensaje }
  }
}
</script>

<template>
  <div>
    <h1 class="text-xl font-bold text-marca-900">Tesorería: cobros de los cobradores</h1>
    <p class="text-sm text-slate-500">Lo que los cobradores cobraron en los edificios y te enviaron. Al aprobar se registra el ingreso (recibo oficial) y la cuota queda cobrada; si algo no cuadra, obsérvalo y vuelve al cobrador.</p>

    <div class="mt-4 flex gap-1 border-b border-slate-200">
      <button v-for="p in PESTANAS" :key="p.codigo" class="-mb-px border-b-2 px-4 py-2 text-sm" :class="pestana === p.codigo ? 'border-oro-500 font-semibold text-marca-900' : 'border-transparent text-slate-500 hover:text-marca-800'" @click="pestana = p.codigo">
        {{ p.texto }}
        <span v-if="p.codigo === 'ENVIADO' && porAprobar" class="ml-1 rounded-full bg-rose-600 px-1.5 text-xs font-bold text-white">{{ porAprobar }}</span>
      </button>
    </div>

    <p v-if="aviso.texto" class="mt-3 rounded px-3 py-2 text-sm" :class="aviso.tipo === 'error' ? 'bg-red-50 text-red-700' : 'bg-emerald-50 text-emerald-800'" role="status">{{ aviso.texto }}</p>
    <p v-if="cargando && !grupos.length" class="py-10 text-center text-slate-400">Cargando…</p>
    <p v-else-if="!grupos.length" class="mt-4 rounded-lg bg-white p-8 text-center text-slate-400 shadow-sm">No hay cobros en esta bandeja.</p>

    <section v-for="g in grupos" :key="g.cobrador" class="mt-4 overflow-hidden rounded-lg bg-white shadow-sm">
      <header class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 bg-slate-50 px-4 py-3">
        <div>
          <div class="font-semibold text-marca-900">{{ g.cobrador }}</div>
          <div class="flex flex-wrap gap-x-4 text-xs text-slate-500">
            <span v-for="m in g.por_metodo.filter((x) => x.cantidad)" :key="m.metodo">{{ m.etiqueta }}: <b class="text-slate-700">{{ bs(m.total) }}</b> ({{ m.cantidad }})</span>
          </div>
        </div>
        <div class="text-right">
          <div class="text-xs uppercase text-slate-400">Total</div>
          <div class="text-lg font-bold tabular-nums text-marca-900">{{ bs(g.total) }}</div>
        </div>
      </header>

      <div class="overflow-x-auto">
        <table class="min-w-full text-left text-sm">
          <thead class="text-xs uppercase text-slate-500">
            <tr>
              <th class="px-4 py-2">Cobrado</th><th class="px-4 py-2">Edificio / cliente</th><th class="px-4 py-2">Concepto</th>
              <th class="px-4 py-2">Forma de pago</th><th class="px-4 py-2">Factura / recibo</th><th class="px-4 py-2 text-right">Monto</th><th class="px-4 py-2" />
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <template v-for="c in g.cobros" :key="c.id">
              <tr class="align-top">
                <td class="whitespace-nowrap px-4 py-2">{{ fecha(c.fecha_cobro) }}<div class="text-xs text-slate-400">enviado {{ fechaHora(c.enviado_en) }}</div></td>
                <td class="px-4 py-2"><div class="font-medium text-marca-900">{{ c.contrato?.edificio?.denominacion }}</div><div class="text-xs text-slate-500">{{ c.contrato?.empresa?.denominacion }}</div></td>
                <td class="px-4 py-2">{{ TIPO[c.tipo_cobro] ?? '—' }}<div class="text-xs text-slate-500">Cuota {{ c.cuota?.nro_cuota }} · {{ c.contrato?.codigo_contrato }}</div></td>
                <td class="px-4 py-2">{{ detalleMetodo(c) }}</td>
                <td class="whitespace-nowrap px-4 py-2">F. {{ c.nro_factura }}<div class="text-xs text-slate-500">R. {{ c.nro_recibo || String(c.id).padStart(8, '0') }}</div></td>
                <td class="whitespace-nowrap px-4 py-2 text-right font-semibold tabular-nums">{{ bs(c.cobrado_bs) }}</td>
                <td class="whitespace-nowrap px-4 py-2 text-right">
                  <div class="flex justify-end gap-1">
                    <button class="rounded border border-slate-200 px-2 py-1 text-xs hover:bg-slate-50" @click="ver(c, 'factura')">Factura</button>
                    <button class="rounded border border-slate-200 px-2 py-1 text-xs hover:bg-slate-50" @click="ver(c, 'recibo')">Recibo</button>
                    <template v-if="pestana === 'ENVIADO' && puedeAprobar">
                      <button class="rounded bg-emerald-600 px-2 py-1 text-xs font-semibold text-white hover:bg-emerald-700" @click="abrir(c, 'aprobar')">Aprobar</button>
                      <button class="rounded border border-rose-200 px-2 py-1 text-xs text-rose-700 hover:bg-rose-50" @click="abrir(c, 'observar')">Observar</button>
                    </template>
                    <button v-if="pestana === 'APROBADO' && sesion.puede('comprobantes.crear')" class="rounded bg-marca-700 px-2 py-1 text-xs font-semibold text-white hover:bg-marca-800" @click="enviarAlCliente(c)">Enviar al cliente</button>
                  </div>
                  <div v-if="pestana === 'APROBADO'" class="mt-1 text-xs text-slate-400">por {{ c.revisor?.name }} · {{ fechaHora(c.revisado_en) }}</div>
                </td>
              </tr>
              <tr v-if="c.observacion_tesoreria && pestana === 'OBSERVADO'">
                <td colspan="7" class="bg-rose-50/60 px-4 py-2 text-sm text-rose-800"><b>Observación:</b> {{ c.observacion_tesoreria }} <span class="text-xs text-rose-600">({{ c.revisor?.name }}, {{ fechaHora(c.revisado_en) }})</span></td>
              </tr>
              <tr v-if="revision?.id === c.id">
                <td colspan="7" class="bg-slate-50 px-4 py-3">
                  <form class="flex flex-wrap items-end gap-3" @submit.prevent="confirmar">
                    <label v-if="revision.accion === 'aprobar'" class="text-sm">Cuenta donde entra el dinero
                      <select v-model="revision.cuenta" class="mt-1 block rounded border-slate-300 text-sm" required>
                        <option v-for="cu in cuentas" :key="cu.id" :value="cu.id">{{ cuentaTexto(cu) }}</option>
                      </select>
                    </label>
                    <label v-else class="min-w-64 flex-1 text-sm">¿Qué debe corregir el cobrador?
                      <input v-model.trim="revision.motivo" class="mt-1 block w-full rounded border-slate-300 text-sm" required minlength="5" maxlength="500" placeholder="Ej.: el número de recibo no coincide con el talonario" />
                    </label>
                    <button type="submit" :disabled="ocupado" class="rounded px-4 py-2 text-sm font-semibold text-white disabled:opacity-60" :class="revision.accion === 'aprobar' ? 'bg-emerald-600 hover:bg-emerald-700' : 'bg-rose-600 hover:bg-rose-700'">
                      {{ revision.accion === 'aprobar' ? `Aprobar ${bs(c.cobrado_bs)}` : 'Devolver al cobrador' }}
                    </button>
                    <button type="button" class="rounded px-3 py-2 text-sm text-slate-600 hover:bg-slate-200" @click="revision = null">Cancelar</button>
                  </form>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</template>
