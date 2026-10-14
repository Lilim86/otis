import personas from './personas'
import paises from './paises'

// Menú lateral agrupado como el del SOFIA. Para sumar un módulo: crear su archivo y agregarlo aquí.
export const modulos = [personas, paises]

export const menu = [
  { grupo: 'Comercial', items: [personas] },
  { grupo: 'Planificación', items: [paises] },
]

export const modulosPorClave = Object.fromEntries(modulos.map((m) => [m.clave, m]))
