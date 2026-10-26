<script setup>
import { comprobantes } from '@/services'
import { useSesionStore } from '@/stores/sesion'

const sesion = useSesionStore()
</script>

<template>
  <div v-if="sesion.puede('comprobantes.ver')" class="flex items-center gap-2 text-sm">
    <span class="text-slate-500">Ver modelo:</span>
    <button class="rounded border border-oro-500 px-3 py-2 font-medium text-marca-900 hover:bg-oro-50" @click="comprobantes.modelo('factura')">Factura</button>
    <button class="rounded border border-oro-500 px-3 py-2 font-medium text-marca-900 hover:bg-oro-50" @click="comprobantes.modelo('recibo')">Recibo</button>
  </div>
</template>
