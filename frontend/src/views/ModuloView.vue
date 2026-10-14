<script setup>
import { computed } from 'vue'
import { modulosPorClave } from '@/modulos'
import ModuloCrud from '@/components/ModuloCrud.vue'

const props = defineProps({ clave: { type: String, required: true } })
const modulo = computed(() => modulosPorClave[props.clave])
</script>

<template>
  <!-- key: al cambiar de módulo se reinicia todo el estado de la pantalla -->
  <ModuloCrud v-if="modulo" :key="clave" :modulo="modulo" />
  <p v-else class="text-slate-600">Ese módulo no existe.</p>
</template>
