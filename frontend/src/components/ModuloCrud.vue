<script setup>
import { ref, reactive, computed, watch, onMounted } from 'vue'
import * as servicios from '@/services'
import { leerError } from '@/services'
import { useSesionStore } from '@/stores/sesion'
import { useCatalogosStore } from '@/stores/catalogos'

const props = defineProps({ modulo: { type: Object, required: true } })

const sesion = useSesionStore()
const catalogos = useCatalogosStore()
const servicio = props.modulo.servicio

// ---- listado -----------------------------------------------------------------
const vista = ref('activos') // activos | papelera
const filas = ref([])
const meta = ref({ current_page: 1, last_page: 1, total: 0 })
const cargando = ref(false)
const aviso = ref({ tipo: '', texto: '' })

const consulta = reactive({ q: '', estado: '', estado_registro: '', filtros: {} })
for (const f of props.modulo.filtros ?? []) consulta.filtros[f.clave] = '' // así el combo arranca en "Todos"
let temporizador = null

const puede = (operacion) => sesion.puede(`${props.modulo.clave}.${operacion}`)

function avisar(tipo, texto) {
  aviso.value = { tipo, texto }
}

async function cargar(pagina = 1) {
  cargando.value = true
  try {
    const params = { page: pagina, per_page: 15 }
    if (consulta.q) params.q = consulta.q
    if (consulta.estado !== '') params.estado = consulta.estado
    if (consulta.estado_registro) params.estado_registro = consulta.estado_registro
    for (const [clave, valor] of Object.entries(consulta.filtros)) if (valor) params[clave] = valor

    const r = vista.value === 'papelera' ? await servicio.papelera(params) : await servicio.listar(params)
    filas.value = r.data
    meta.value = r.meta
  } catch (e) {
    avisar('error', leerError(e).mensaje)
  } finally {
    cargando.value = false
  }
}

// La búsqueda espera a que se deje de escribir para no consultar en cada tecla.
watch(() => consulta.q, () => {
  clearTimeout(temporizador)
  temporizador = setTimeout(() => cargar(1), 350)
})
watch([() => consulta.estado, () => consulta.estado_registro, () => consulta.filtros, vista], () => cargar(1), { deep: true })

onMounted(async () => {
  await catalogos.cargar().catch(() => {})
  cargar()
})

// ---- celdas --------------------------------------------------------------------
function celda(fila, columna) {
  if (columna.valor) return columna.valor(fila)
  const valor = fila[columna.clave]
  return columna.catalogo ? catalogos.etiqueta(columna.catalogo, valor) : (valor ?? '—')
}

const colorRegistro = { REG: 'bg-amber-100 text-amber-800', APR: 'bg-emerald-100 text-emerald-800', ANL: 'bg-red-100 text-red-800' }

// ---- acciones sobre una fila ---------------------------------------------------
async function ejecutar(accion, confirmacion, exito) {
  if (confirmacion && !window.confirm(confirmacion)) return
  try {
    await accion()
    avisar('ok', exito)
    await cargar(meta.value.current_page)
  } catch (e) {
    const { mensaje, campos } = leerError(e)
    avisar('error', Object.values(campos)[0] ?? mensaje)
  }
}

// Acciones propias de un módulo (ej.: imprimir el recibo de una cobranza).
const accionesVisibles = (fila) => (props.modulo.accionesFila ?? []).filter((a) => (!a.visible || a.visible(fila)) && (!a.permiso || puede(a.permiso)))
async function accionFila(accion, fila) {
  try {
    const mensaje = await accion.ejecutar(fila)
    if (mensaje) avisar('ok', mensaje)
  } catch (e) {
    const { mensaje, campos } = leerError(e)
    avisar('error', Object.values(campos)[0] ?? mensaje)
  }
}

const etiqueta = (clave, defecto) => props.modulo.etiquetas?.[clave] ?? defecto
const aprobar = (f) => ejecutar(() => servicio.aprobar(f.id), `¿${etiqueta('aprobar', 'Aprobar')} este registro?`, 'Listo.')
function anular(f) {
  // Rechazar un pago, por ejemplo, exige decir el motivo.
  if (props.modulo.pedirMotivo) {
    const motivo = window.prompt(props.modulo.pedirMotivo)
    if (!motivo?.trim()) return
    return ejecutar(() => servicio.rechazar(f.id, motivo.trim()), null, 'Listo.')
  }
  return ejecutar(() => servicio.anular(f.id), '¿Anular este registro? Quedará visible pero ya no se podrá modificar.', 'Registro anulado.')
}
const eliminar = (f) => ejecutar(() => servicio.eliminar(f.id), '¿Enviar este registro a la papelera?', 'Enviado a la papelera.')
const restaurar = (f) => ejecutar(() => servicio.restaurar(f.id), null, 'Registro restaurado.')
const cambiarEstado = (f) =>
  f.estado
    ? ejecutar(() => servicio.darDeBaja(f.id), '¿Dar de baja este registro?', 'Registro dado de baja.')
    : ejecutar(() => servicio.darDeAlta(f.id), null, 'Registro dado de alta.')

// ---- formulario ----------------------------------------------------------------
const campos = computed(() => props.modulo.secciones.flatMap((s) => s.campos))
const form = reactive({})
const errores = ref({})
const modal = ref(false)
const editando = ref(null)
const guardando = ref(false)
const opciones = reactive({}) // opciones de los selects remotos, por campo

function vaciarFormulario(fila = null) {
  for (const c of campos.value) {
    let valor = fila ? (fila[c.clave] ?? '') : (c.defecto ?? '')
    if (c.arreglo && Array.isArray(valor)) valor = valor[0] ?? '' // un solo valor que la API recibe como lista
    // datetime-local necesita AAAA-MM-DDTHH:mm
    form[c.clave] = c.tipo === 'fechahora' && valor ? String(valor).replace(' ', 'T').slice(0, 16) : valor
  }
}

async function cargarOpciones(campo) {
  const { servicio: nombre, depende } = campo.remoto
  if (depende && !form[depende]) {
    opciones[campo.clave] = []
    return
  }
  try {
    opciones[campo.clave] = await servicios[nombre].todos({ ...campo.remoto.params, ...(depende ? { [depende]: form[depende] } : {}) })
  } catch {
    opciones[campo.clave] = []
  }
}

/** Al cambiar un campo, los que dependen de él (en cadena) se vacían y recargan. */
async function alCambiar(campo) {
  for (const hijo of campos.value.filter((c) => c.remoto?.depende === campo.clave)) {
    form[hijo.clave] = ''
    await cargarOpciones(hijo)
    await alCambiar(hijo)
  }
}

async function abrir(fila = null) {
  errores.value = {}
  editando.value = fila?.id ?? null
  vaciarFormulario(fila)
  modal.value = true
  await Promise.all(campos.value.filter((c) => c.remoto).map(cargarOpciones))
}

const opcionesDe = (campo) =>
  campo.opciones
    ? campo.opciones
    : campo.catalogo
    ? (catalogos.datos[campo.catalogo] ?? []).map((o) => ({ valor: o.codigo, texto: o.etiqueta }))
    : (opciones[campo.clave] ?? []).map((o) => ({ valor: o[campo.remoto.valor ?? 'id'], texto: o[campo.remoto.texto] }))

async function guardar() {
  guardando.value = true
  errores.value = {}
  try {
    const datos = Object.fromEntries(
      campos.value.map((c) => [c.clave, c.arreglo ? (form[c.clave] === '' ? [] : [form[c.clave]]) : form[c.clave] === '' ? null : form[c.clave]]),
    )
    if (editando.value) await servicio.actualizar(editando.value, datos)
    else await servicio.crear(datos)
    modal.value = false
    avisar('ok', editando.value ? 'Cambios guardados.' : 'Registro creado.')
    await cargar(editando.value ? meta.value.current_page : 1)
  } catch (e) {
    const { mensaje, campos: porCampo } = leerError(e)
    errores.value = porCampo
    if (!Object.keys(porCampo).length) avisar('error', mensaje)
  } finally {
    guardando.value = false
  }
}

const tipoInput = (campo) => ({ email: 'email', fecha: 'date', fechahora: 'datetime-local', numero: 'number' })[campo.tipo] ?? 'text'
</script>

<template>
  <div>
    <div class="flex flex-wrap items-center justify-between gap-3">
      <h1 class="text-xl font-bold text-marca-900">{{ modulo.titulo }}</h1>
      <component :is="modulo.cabecera" v-if="modulo.cabecera" @hecho="cargar(1)" />
      <button
        v-if="vista === 'activos' && puede('crear') && !modulo.sinFormulario"
        class="rounded bg-marca-900 px-4 py-2 text-sm font-medium text-white hover:bg-marca-700"
        @click="abrir()"
      >
        Nuevo
      </button>
    </div>

    <p
      v-if="aviso.texto"
      class="mt-3 flex items-start justify-between gap-3 rounded px-3 py-2 text-sm"
      :class="aviso.tipo === 'ok' ? 'bg-emerald-50 text-emerald-800' : 'bg-red-50 text-red-700'"
      role="status"
    >
      <span>{{ aviso.texto }}</span>
      <button class="font-bold" aria-label="Cerrar aviso" @click="avisar('', '')">×</button>
    </p>

    <!-- Filtros -->
    <div class="mt-4 flex flex-wrap items-end gap-3 rounded-lg bg-white p-3 shadow-sm">
      <input v-model="consulta.q" :placeholder="modulo.busqueda ?? 'Buscar'" class="min-w-56 flex-1 rounded border border-slate-300 px-3 py-2 text-sm focus:border-oro-500 focus:outline-none" />

      <label v-for="f in modulo.filtros" :key="f.clave" class="text-xs text-slate-500">
        {{ f.etiqueta }}
        <select v-model="consulta.filtros[f.clave]" class="mt-1 block rounded border border-slate-300 px-2 py-2 text-sm text-slate-800">
          <option value="">Todos</option>
          <option v-for="o in catalogos.datos[f.catalogo] ?? []" :key="o.codigo" :value="o.codigo">{{ o.etiqueta }}</option>
        </select>
      </label>

      <label v-if="modulo.aprobacion" class="text-xs text-slate-500">
        Estado del registro
        <select v-model="consulta.estado_registro" class="mt-1 block rounded border border-slate-300 px-2 py-2 text-sm text-slate-800">
          <option value="">Todos</option>
          <option value="REG">Registrado</option>
          <option value="APR">Aprobado</option>
          <option value="ANL">Anulado</option>
        </select>
      </label>

      <label class="text-xs text-slate-500">
        Alta / baja
        <select v-model="consulta.estado" class="mt-1 block rounded border border-slate-300 px-2 py-2 text-sm text-slate-800">
          <option value="">Todos</option>
          <option value="1">Activos</option>
          <option value="0">Dados de baja</option>
        </select>
      </label>

      <div class="ml-auto flex overflow-hidden rounded border border-slate-300 text-sm">
        <button class="px-3 py-2" :class="vista === 'activos' ? 'bg-marca-900 text-white' : 'bg-white'" @click="vista = 'activos'">Registros</button>
        <button class="px-3 py-2" :class="vista === 'papelera' ? 'bg-marca-900 text-white' : 'bg-white'" @click="vista = 'papelera'">Papelera</button>
      </div>
    </div>

    <!-- Tabla: en celular se desplaza horizontalmente -->
    <div class="mt-4 overflow-x-auto rounded-lg bg-white shadow-sm">
      <table class="min-w-full text-left text-sm">
        <thead class="bg-slate-50 text-xs uppercase text-slate-500">
          <tr>
            <th v-for="c in modulo.columnas" :key="c.clave" class="px-4 py-3">{{ c.etiqueta }}</th>
            <th v-if="modulo.aprobacion" class="px-4 py-3">Registro</th>
            <th class="px-4 py-3">Estado</th>
            <th class="px-4 py-3 text-right">Acciones</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-if="cargando"><td :colspan="modulo.columnas.length + 3" class="px-4 py-8 text-center text-slate-400">Cargando…</td></tr>
          <tr v-else-if="!filas.length">
            <td :colspan="modulo.columnas.length + 3" class="px-4 py-8 text-center text-slate-400">
              {{ vista === 'papelera' ? 'La papelera está vacía.' : 'No hay registros.' }}
            </td>
          </tr>
          <tr v-for="fila in filas" v-else :key="fila.id" class="hover:bg-slate-50">
            <td v-for="c in modulo.columnas" :key="c.clave" class="px-4 py-2">{{ celda(fila, c) }}</td>
            <td v-if="modulo.aprobacion" class="px-4 py-2">
              <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="colorRegistro[fila.estado_registro]">{{ fila.estado_registro }}</span>
            </td>
            <td class="px-4 py-2">
              <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="fila.estado ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-600'">
                {{ fila.estado ? 'Activo' : 'Baja' }}
              </span>
            </td>
            <td class="whitespace-nowrap px-4 py-2 text-right">
              <template v-if="vista === 'papelera'">
                <button v-if="puede('restaurar')" class="mr-2 text-marca-600 hover:underline" @click="restaurar(fila)">Restaurar</button>
              </template>
              <template v-else>
                <button v-for="a in accionesVisibles(fila)" :key="a.etiqueta" class="mr-2 text-oro-700 hover:underline" @click="accionFila(a, fila)">{{ a.etiqueta }}</button>
                <button v-if="puede('editar') && fila.estado_registro !== 'ANL' && !modulo.sinFormulario" class="mr-2 text-marca-600 hover:underline" @click="abrir(fila)">Editar</button>
                <button v-if="modulo.aprobacion && fila.estado_registro === 'REG' && puede('aprobar')" class="mr-2 text-emerald-700 hover:underline" @click="aprobar(fila)">{{ etiqueta('aprobar', 'Aprobar') }}</button>
                <button v-if="modulo.aprobacion && fila.estado_registro !== 'ANL' && puede('anular')" class="mr-2 text-amber-700 hover:underline" @click="anular(fila)">{{ etiqueta('anular', 'Anular') }}</button>
                <button v-if="puede('editar') && !modulo.sinFormulario" class="mr-2 text-slate-600 hover:underline" @click="cambiarEstado(fila)">{{ fila.estado ? 'Dar de baja' : 'Dar de alta' }}</button>
                <button v-if="puede('eliminar') && !modulo.sinFormulario" class="text-red-600 hover:underline" @click="eliminar(fila)">Eliminar</button>
              </template>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="mt-3 flex items-center justify-between text-sm text-slate-600">
      <span>{{ meta.total }} registro(s)</span>
      <div class="flex items-center gap-2">
        <button class="rounded border border-slate-300 bg-white px-3 py-1 disabled:opacity-40" :disabled="meta.current_page <= 1" @click="cargar(meta.current_page - 1)">Anterior</button>
        <span>Página {{ meta.current_page }} de {{ meta.last_page }}</span>
        <button class="rounded border border-slate-300 bg-white px-3 py-1 disabled:opacity-40" :disabled="meta.current_page >= meta.last_page" @click="cargar(meta.current_page + 1)">Siguiente</button>
      </div>
    </div>

    <!-- Formulario -->
    <div v-if="modal" class="fixed inset-0 z-20 flex items-start justify-center overflow-y-auto bg-black/50 p-4" @mousedown.self="modal = false">
      <form class="my-8 w-full max-w-3xl rounded-lg bg-white p-6 shadow-xl" @submit.prevent="guardar">
        <h2 class="text-lg font-bold text-marca-900">{{ editando ? 'Modificar' : 'Nuevo' }} · {{ modulo.titulo }}</h2>

        <section v-for="seccion in modulo.secciones" :key="seccion.titulo" class="mt-5">
          <h3 class="border-b border-slate-200 pb-1 text-sm font-semibold uppercase tracking-wide text-slate-500">{{ seccion.titulo }}</h3>
          <div class="mt-3 grid gap-4 sm:grid-cols-2">
            <label v-for="campo in seccion.campos" :key="campo.clave" class="block text-sm" :class="campo.tipo === 'textarea' ? 'sm:col-span-2' : ''">
              <span class="text-slate-600">{{ campo.etiqueta }}<span v-if="campo.requerido" class="text-red-600"> *</span></span>

              <select
                v-if="campo.tipo === 'select'"
                v-model="form[campo.clave]"
                :disabled="campo.remoto?.depende && !form[campo.remoto.depende]"
                class="mt-1 w-full rounded border border-slate-300 px-3 py-2 focus:border-oro-500 focus:outline-none disabled:bg-slate-100"
                @change="alCambiar(campo)"
              >
                <option value="">— Elegir —</option>
                <option v-for="o in opcionesDe(campo)" :key="o.valor" :value="o.valor">{{ o.texto }}</option>
              </select>
              <textarea v-else-if="campo.tipo === 'textarea'" v-model="form[campo.clave]" rows="3" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 focus:border-oro-500 focus:outline-none" />
              <input v-else v-model="form[campo.clave]" :type="tipoInput(campo)" :step="campo.tipo === 'numero' ? 'any' : undefined" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 focus:border-oro-500 focus:outline-none" />

              <span v-if="errores[campo.clave]" class="text-xs text-red-600">{{ errores[campo.clave] }}</span>
            </label>
          </div>
        </section>

        <div class="mt-6 flex justify-end gap-3">
          <button type="button" class="rounded border border-slate-300 px-4 py-2 text-sm" @click="modal = false">Cancelar</button>
          <button :disabled="guardando" class="rounded bg-marca-900 px-4 py-2 text-sm font-medium text-white hover:bg-marca-700 disabled:opacity-60">
            {{ guardando ? 'Guardando…' : 'Guardar' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>
