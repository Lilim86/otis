<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { portal, leerError } from '@/services'
import { fecha } from '@/modulos/comun'

const productos = ref([])
const edificios = ref([])
const diasHabiles = ref(3)
const fechaEstimada = ref('')
const pedidos = ref([])
const categoria = ref('')
const cargando = ref(true)
const aviso = ref({ tipo: '', texto: '' })

const categorias = computed(() => [...new Set(productos.value.map((p) => p.categoria))])
const visibles = computed(() => productos.value.filter((p) => !categoria.value || p.categoria === categoria.value))

const colores = { PENDIENTE: 'bg-oro-100 text-oro-800', ATENDIDO: 'bg-emerald-100 text-emerald-800', CANCELADO: 'bg-slate-200 text-slate-600' }
const nombres = { PENDIENTE: 'Pendiente', ATENDIDO: 'Atendido', CANCELADO: 'Cancelado' }

async function cargar() {
  cargando.value = true
  try {
    const [catalogo, misPedidos] = await Promise.all([portal.catalogo(), portal.pedidos()])
    productos.value = catalogo.data
    edificios.value = catalogo.edificios
    diasHabiles.value = catalogo.dias_habiles
    fechaEstimada.value = catalogo.fecha_estimada
    pedidos.value = misPedidos
  } catch (e) {
    aviso.value = { tipo: 'error', texto: leerError(e).mensaje }
  } finally {
    cargando.value = false
  }
}
onMounted(cargar)

// ---- comprar: agenda un pedido ---------------------------------------------------
const elegido = ref(null)
const form = reactive({ cantidad: 1, edificio_id: '', observaciones: '' })
const errores = ref({})
const enviando = ref(false)

function comprar(producto) {
  Object.assign(form, { cantidad: 1, edificio_id: edificios.value.length === 1 ? edificios.value[0].id : '', observaciones: '' })
  errores.value = {}
  elegido.value = producto
}

async function confirmar() {
  enviando.value = true
  errores.value = {}
  try {
    const r = await portal.pedirProducto({ producto_id: elegido.value.id, cantidad: form.cantidad, edificio_id: form.edificio_id || null, observaciones: form.observaciones || null })
    aviso.value = { tipo: 'ok', texto: r.message }
    elegido.value = null
    pedidos.value = await portal.pedidos()
  } catch (e) {
    const { mensaje, campos } = leerError(e)
    errores.value = campos
    if (!Object.keys(campos).length) aviso.value = { tipo: 'error', texto: mensaje }
  } finally {
    enviando.value = false
  }
}
</script>

<template>
  <div>
    <h1 class="font-display text-2xl font-bold text-marca-900">Catálogo</h1>
    <p class="mt-1 text-sm text-slate-500">Elige tu equipo y agenda un pedido: el gerente lo atenderá en un plazo de {{ diasHabiles }} días hábiles.</p>

    <p v-if="aviso.texto" class="mt-3 flex items-start justify-between gap-3 rounded px-3 py-2 text-sm" :class="aviso.tipo === 'ok' ? 'bg-emerald-50 text-emerald-800' : 'bg-red-50 text-red-700'" role="status">
      <span>{{ aviso.texto }}</span>
      <button class="font-bold" aria-label="Cerrar aviso" @click="aviso = { tipo: '', texto: '' }">×</button>
    </p>

    <div v-if="categorias.length" class="mt-4 flex flex-wrap gap-2">
      <button class="rounded-full border px-3 py-1 text-sm" :class="!categoria ? 'border-marca-900 bg-marca-900 text-white' : 'border-slate-300 bg-white text-slate-700'" @click="categoria = ''">Todos</button>
      <button v-for="c in categorias" :key="c" class="rounded-full border px-3 py-1 text-sm" :class="categoria === c ? 'border-marca-900 bg-marca-900 text-white' : 'border-slate-300 bg-white text-slate-700'" @click="categoria = c">{{ c }}</button>
    </div>

    <p v-if="cargando" class="mt-6 text-slate-400">Cargando…</p>
    <p v-else-if="!productos.length" class="mt-6 text-slate-500">El catálogo todavía no tiene productos.</p>

    <div class="mt-4 grid gap-5 md:grid-cols-2 lg:grid-cols-3">
      <article v-for="p in visibles" :key="p.id" class="flex flex-col overflow-hidden rounded-lg bg-white shadow-sm">
        <div class="flex h-52 items-center justify-center bg-slate-50">
          <img v-if="p.imagen_url" :src="p.imagen_url" :alt="p.nombre" loading="lazy" referrerpolicy="no-referrer" class="h-full w-full object-contain" @error="$event.target.style.display = 'none'" />
        </div>
        <div class="flex flex-1 flex-col p-4">
          <p class="text-xs uppercase tracking-wide text-oro-700">{{ p.categoria }}</p>
          <h2 class="mt-1 text-lg font-bold text-marca-900">{{ p.nombre }}</h2>
          <p class="mt-2 text-sm text-slate-600">{{ p.descripcion }}</p>
          <dl class="mt-3 flex-1 divide-y divide-slate-100 text-sm">
            <div v-for="e in p.especificaciones" :key="e.etiqueta" class="flex justify-between gap-3 py-1.5">
              <dt class="text-slate-500">{{ e.etiqueta }}</dt>
              <dd class="text-right font-medium text-slate-800">{{ e.valor }}</dd>
            </div>
          </dl>
          <div class="mt-4 flex items-center justify-between gap-3">
            <a v-if="p.catalogo_url" :href="p.catalogo_url" target="_blank" rel="noopener noreferrer" class="text-sm text-marca-600 hover:underline">Ficha completa (PDF)</a>
            <button class="ml-auto rounded bg-marca-900 px-5 py-2 text-sm font-medium text-white hover:bg-marca-700" @click="comprar(p)">Comprar</button>
          </div>
        </div>
      </article>
    </div>

    <section v-if="pedidos.length" class="mt-8">
      <h2 class="font-display text-xl font-bold text-marca-900">Mis pedidos</h2>
      <div class="mt-3 overflow-x-auto rounded-lg bg-white shadow-sm">
        <table class="min-w-full text-left text-sm">
          <thead class="bg-slate-50 text-xs uppercase text-slate-500">
            <tr>
              <th class="px-4 py-3">Pedido</th>
              <th class="px-4 py-3">Producto</th>
              <th class="px-4 py-3">Edificio</th>
              <th class="px-4 py-3">Cant.</th>
              <th class="px-4 py-3">Solicitado</th>
              <th class="px-4 py-3">Atención hasta</th>
              <th class="px-4 py-3">Situación</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100">
            <tr v-for="p in pedidos" :key="p.id">
              <td class="whitespace-nowrap px-4 py-2 font-medium">{{ p.codigo }}</td>
              <td class="px-4 py-2">{{ p.producto }}</td>
              <td class="px-4 py-2">{{ p.edificio ?? '—' }}</td>
              <td class="px-4 py-2">{{ p.cantidad }}</td>
              <td class="whitespace-nowrap px-4 py-2">{{ fecha(p.fecha_pedido) }}</td>
              <td class="whitespace-nowrap px-4 py-2">{{ fecha(p.fecha_compromiso) }}</td>
              <td class="px-4 py-2"><span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="colores[p.situacion]">{{ nombres[p.situacion] }}</span></td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <div v-if="elegido" class="fixed inset-0 z-20 flex items-start justify-center overflow-y-auto bg-black/50 p-4" @mousedown.self="elegido = null">
      <form class="my-8 w-full max-w-lg rounded-lg bg-white p-6 shadow-xl" @submit.prevent="confirmar">
        <h2 class="text-lg font-bold text-marca-900">Agendar pedido</h2>
        <p class="mt-1 text-sm text-slate-600">{{ elegido.nombre }} · {{ elegido.categoria }}</p>

        <label class="mt-4 block text-sm">
          <span class="text-slate-600">Edificio</span>
          <select v-model="form.edificio_id" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 focus:border-oro-500 focus:outline-none">
            <option value="">Sin edificio / proyecto nuevo</option>
            <option v-for="e in edificios" :key="e.id" :value="e.id">{{ e.denominacion }}</option>
          </select>
          <span v-if="errores.edificio_id" class="text-xs text-red-600">{{ errores.edificio_id }}</span>
        </label>

        <label class="mt-3 block text-sm">
          <span class="text-slate-600">Cantidad</span>
          <input v-model.number="form.cantidad" type="number" min="1" max="50" required class="mt-1 w-full rounded border border-slate-300 px-3 py-2 focus:border-oro-500 focus:outline-none" />
          <span v-if="errores.cantidad" class="text-xs text-red-600">{{ errores.cantidad }}</span>
        </label>

        <label class="mt-3 block text-sm">
          <span class="text-slate-600">Observaciones</span>
          <textarea v-model="form.observaciones" rows="3" maxlength="1000" placeholder="Pisos, capacidad requerida, plazos…" class="mt-1 w-full rounded border border-slate-300 px-3 py-2 focus:border-oro-500 focus:outline-none"></textarea>
          <span v-if="errores.observaciones" class="text-xs text-red-600">{{ errores.observaciones }}</span>
        </label>

        <p class="mt-4 rounded bg-marca-50 px-3 py-2 text-sm text-marca-900">
          Tu pedido quedará agendado para el <strong>{{ fecha(fechaEstimada) }}</strong> ({{ diasHabiles }} días hábiles) y se notificará al gerente. El precio se coordina con él.
        </p>

        <div class="mt-5 flex justify-end gap-3">
          <button type="button" class="rounded border border-slate-300 px-4 py-2 text-sm" @click="elegido = null">Cancelar</button>
          <button :disabled="enviando" class="rounded bg-marca-900 px-4 py-2 text-sm font-medium text-white hover:bg-marca-700 disabled:opacity-60">{{ enviando ? 'Agendando…' : 'Confirmar pedido' }}</button>
        </div>
      </form>
    </div>
  </div>
</template>
