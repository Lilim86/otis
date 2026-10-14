<script setup>
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useSesionStore } from '@/stores/sesion'
import { leerError } from '@/services'

const sesion = useSesionStore()
const router = useRouter()

const paso = ref(1) // 1: usuario y contraseña, 2: código del correo
const username = ref('')
const password = ref('')
const codigo = ref('')
const correo = ref('')
const error = ref('')
const errores = ref({})
const cargando = ref(false)
const verClave = ref(false)
const intento = ref(false)

const titulo = computed(() => (paso.value === 1 ? 'Iniciar sesión' : 'Verificación en dos pasos'))

async function ejecutar(accion) {
  error.value = ''
  errores.value = {}
  cargando.value = true
  try {
    await accion()
  } catch (e) {
    const { mensaje, campos } = leerError(e)
    errores.value = campos
    error.value = Object.keys(campos).length ? '' : mensaje
  } finally {
    cargando.value = false
  }
}

const usuarioVacio = computed(() => intento.value && !username.value.trim())
const claveVacia = computed(() => intento.value && !password.value)
const codigoInvalido = computed(() => intento.value && !/^\d{6}$/.test(codigo.value))

const pedirCodigo = () => {
  intento.value = true
  if (paso.value === 1 && (!username.value.trim() || !password.value)) return
  return ejecutar(async () => {
    const r = await sesion.pedirCodigo({ username: username.value, password: password.value })
    correo.value = r.correo
    paso.value = 2
    intento.value = false
  })
}

const verificar = () => {
  intento.value = true
  if (!/^\d{6}$/.test(codigo.value)) return
  return ejecutar(async () => {
    await sesion.confirmarCodigo({ username: username.value, codigo: codigo.value })
    router.push({ name: 'dashboard' })
  })
}

function volver() {
  paso.value = 1
  intento.value = false
  codigo.value = ''
  error.value = ''
  errores.value = {}
}
</script>

<template>
  <div class="grid min-h-screen bg-marfil lg:grid-cols-2">
    <aside class="relative hidden flex-col justify-between overflow-hidden bg-gradient-to-br from-marca-950 via-marca-900 to-marca-700 p-14 text-white lg:flex">
      <div class="pointer-events-none absolute -right-32 -top-32 size-96 rounded-full border border-oro-400/30"></div>
      <div class="pointer-events-none absolute -right-16 -top-16 size-64 rounded-full border border-oro-400/20"></div>
      <div class="pointer-events-none absolute -bottom-40 -left-24 size-[28rem] rounded-full bg-oro-500/10 blur-3xl"></div>
      <div class="relative">
        <div class="font-display text-5xl font-extrabold tracking-[0.3em] text-oro-300">OTIS</div>
        <div class="mt-1 text-xs uppercase tracking-[0.5em] text-oro-200/70">LILIM</div>
      </div>
      <div class="relative max-w-md">
        <div class="mb-8 h-px w-16 bg-oro-400"></div>
        <h1 class="font-display text-4xl font-semibold leading-tight">Gestión integral de ascensores y servicios.</h1>
        <p class="mt-5 leading-relaxed text-marca-100/80">Contratos, mantenimientos, almacenes, cobranzas y agenda gerencial en un solo lugar.</p>
      </div>
      <div class="relative text-xs text-marca-200/60">© {{ new Date().getFullYear() }} Compañía Comercial General Industrial Ltda.</div>
    </aside>

    <main class="flex items-center justify-center px-6 py-10">
      <div class="w-full max-w-md rounded-2xl border border-oro-200/60 bg-white px-8 py-9 shadow-xl shadow-marca-900/10">
        <h2 class="font-display text-3xl font-semibold text-marca-900">{{ titulo }}</h2>
        <div class="mt-2 h-0.5 w-10 bg-oro-500"></div>

        <p v-if="error" class="mt-5 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">{{ error }}</p>

        <form v-if="paso === 1" class="mt-6 space-y-5" novalidate @submit.prevent="pedirCodigo">
          <label class="block text-sm">
            <span class="font-medium text-slate-700">Usuario o correo electrónico</span>
            <input
              v-model="username"
              autocomplete="username"
              placeholder="Ej: jperez o jperez@empresa.com"
              class="mt-1 w-full rounded-md border px-3 py-2 shadow-sm focus:outline-none focus:ring-2"
              :class="usuarioVacio || errores.username ? 'border-rose-400 focus:ring-rose-300' : 'border-slate-300 focus:border-oro-500 focus:ring-oro-300'"
            />
            <span v-if="usuarioVacio" class="text-xs text-rose-600">Escribe tu usuario o correo.</span>
            <span v-else-if="errores.username" class="text-xs text-rose-600">{{ errores.username }}</span>
          </label>

          <label class="block text-sm">
            <span class="font-medium text-slate-700">Contraseña</span>
            <div class="relative mt-1">
              <input
                v-model="password"
                :type="verClave ? 'text' : 'password'"
                autocomplete="current-password"
                placeholder="Tu contraseña"
                class="w-full rounded-md border px-3 py-2 pr-11 shadow-sm focus:outline-none focus:ring-2"
                :class="claveVacia || errores.password ? 'border-rose-400 focus:ring-rose-300' : 'border-slate-300 focus:border-oro-500 focus:ring-oro-300'"
              />
              <button
                type="button"
                tabindex="-1"
                class="absolute inset-y-0 right-0 flex items-center px-3 text-slate-400 hover:text-oro-600"
                :title="verClave ? 'Ocultar contraseña' : 'Mostrar contraseña'"
                :aria-label="verClave ? 'Ocultar contraseña' : 'Mostrar contraseña'"
                @click="verClave = !verClave"
              >
                <svg v-if="!verClave" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" class="size-5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M2.04 12.32a1 1 0 0 1 0-.64C3.42 7.51 7.36 4.5 12 4.5c4.64 0 8.57 3.01 9.96 7.18.07.21.07.43 0 .64C20.58 16.49 16.64 19.5 12 19.5c-4.64 0-8.57-3.01-9.96-7.18Z" />
                  <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                </svg>
                <svg v-else xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" class="size-5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.22A10.48 10.48 0 0 0 1.93 12c1.29 3.9 4.94 7.5 10.07 7.5 1.77 0 3.4-.43 4.83-1.17M6.23 6.23A10.45 10.45 0 0 1 12 4.5c5.13 0 8.78 3.6 10.07 7.5a10.5 10.5 0 0 1-4.3 5.54M6.23 6.23 3 3m3.23 3.23 3.65 3.65m7.89 7.89L21 21m-3.23-3.23-3.65-3.65m0 0a3 3 0 1 0-4.24-4.24m4.24 4.24L9.88 9.88" />
                </svg>
              </button>
            </div>
            <span v-if="claveVacia" class="text-xs text-rose-600">Escribe tu contraseña.</span>
            <span v-else-if="errores.password" class="text-xs text-rose-600">{{ errores.password }}</span>
          </label>

          <button
            :disabled="cargando"
            class="w-full rounded-lg bg-gradient-to-r from-oro-500 to-oro-400 py-3 text-xs font-semibold uppercase tracking-widest text-marca-950 shadow-md shadow-oro-500/30 hover:from-oro-400 hover:to-oro-300 disabled:opacity-60"
          >
            {{ cargando ? 'Enviando código…' : 'Continuar' }}
          </button>
        </form>

        <form v-else class="mt-6 space-y-5" novalidate @submit.prevent="verificar">
          <p class="text-sm text-slate-600">
            Te enviamos un código de 6 dígitos a <strong>{{ correo }}</strong>. Vence en 5 minutos.
          </p>
          <label class="block text-sm">
            <span class="font-medium text-slate-700">Código</span>
            <input
              v-model="codigo"
              inputmode="numeric"
              maxlength="6"
              autocomplete="one-time-code"
              class="mt-1 w-full rounded-md border px-3 py-2 text-center text-2xl tracking-[0.5em] shadow-sm focus:outline-none focus:ring-2"
              :class="codigoInvalido || errores.codigo ? 'border-rose-400 focus:ring-rose-300' : 'border-slate-300 focus:border-oro-500 focus:ring-oro-300'"
            />
            <span v-if="codigoInvalido" class="text-xs text-rose-600">El código tiene 6 dígitos.</span>
            <span v-else-if="errores.codigo" class="text-xs text-rose-600">{{ errores.codigo }}</span>
          </label>
          <button
            :disabled="cargando"
            class="w-full rounded-lg bg-gradient-to-r from-oro-500 to-oro-400 py-3 text-xs font-semibold uppercase tracking-widest text-marca-950 shadow-md shadow-oro-500/30 hover:from-oro-400 hover:to-oro-300 disabled:opacity-60"
          >
            {{ cargando ? 'Verificando…' : 'Ingresar' }}
          </button>
          <div class="flex justify-between text-sm">
            <button type="button" class="text-marca-600 underline hover:text-oro-600" @click="volver">Volver</button>
            <button type="button" class="text-marca-600 underline hover:text-oro-600" :disabled="cargando" @click="pedirCodigo">Reenviar código</button>
          </div>
        </form>
      </div>
    </main>
  </div>
</template>
