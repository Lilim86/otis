<script setup>
import { ref, onMounted } from 'vue'

/**
 * Lienzo para firmar con el dedo o el mouse. Entrega los trazos como listas de puntos
 * (no una imagen): el servidor arma el dibujo y así no se guarda nada ajeno.
 */
const props = defineProps({ ancho: { type: Number, default: 480 }, alto: { type: Number, default: 180 } })
const emit = defineEmits(['cambio'])

const lienzo = ref(null)
let trazos = []
let actual = null

function contexto() {
  const ctx = lienzo.value.getContext('2d')
  ctx.lineWidth = 2.2
  ctx.lineCap = 'round'
  ctx.lineJoin = 'round'
  ctx.strokeStyle = '#0D1638'
  return ctx
}

function punto(e) {
  const r = lienzo.value.getBoundingClientRect()
  return [Math.round(((e.clientX - r.left) * props.ancho) / r.width * 10) / 10, Math.round(((e.clientY - r.top) * props.alto) / r.height * 10) / 10]
}

function empezar(e) {
  lienzo.value.setPointerCapture(e.pointerId)
  actual = [punto(e)]
}
function mover(e) {
  if (!actual) return
  const p = punto(e)
  const ctx = contexto()
  const ultimo = actual[actual.length - 1]
  ctx.beginPath()
  ctx.moveTo(ultimo[0], ultimo[1])
  ctx.lineTo(p[0], p[1])
  ctx.stroke()
  actual.push(p)
}
function terminar() {
  if (!actual) return
  if (actual.length > 1) trazos.push(actual)
  actual = null
  emit('cambio', trazos.length ? { ancho: props.ancho, alto: props.alto, trazos } : null)
}

function limpiar() {
  lienzo.value.getContext('2d').clearRect(0, 0, props.ancho, props.alto)
  trazos = []
  emit('cambio', null)
}

onMounted(() => contexto())
defineExpose({ limpiar })
</script>

<template>
  <div>
    <canvas
      ref="lienzo"
      :width="ancho"
      :height="alto"
      class="w-full touch-none rounded border-2 border-dashed border-oro-400 bg-white"
      :style="{ aspectRatio: `${ancho} / ${alto}` }"
      @pointerdown.prevent="empezar"
      @pointermove.prevent="mover"
      @pointerup="terminar"
      @pointerleave="terminar"
    ></canvas>
    <button type="button" class="mt-1 text-xs text-marca-600 underline hover:text-oro-600" @click="limpiar">Borrar y empezar de nuevo</button>
  </div>
</template>
