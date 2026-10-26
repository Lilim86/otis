import personas from './personas'
import paises from './paises'
import { geografia } from './geografia'
import { rrhh } from './rrhh'
import { comercial } from './comercial'
import { tecnico } from './tecnico'
import { almacenesModulos } from './almacenes'
import { financiero } from './financiero'
import { secretaria } from './secretaria'
import { seguridad } from './seguridad'

const porClave = (lista) => Object.fromEntries(lista.map((m) => [m.clave, m]))
const todos = [personas, paises, ...geografia, ...rrhh, ...comercial, ...tecnico, ...almacenesModulos, ...financiero, ...secretaria, ...seguridad]

// Menú lateral agrupado como el del SOFIA. Para sumar un módulo: crear su archivo y agregarlo aquí.
export const modulos = todos
export const modulosPorClave = porClave(todos)

const de = (...claves) => claves.map((c) => modulosPorClave[c])

export const menu = [
  { grupo: 'Comercial', items: de('empresas', 'edificios', 'contratos', 'cuotas-contrato', 'personas') },
  { grupo: 'Técnico', items: de('equipos', 'visitas-mantenimiento') },
  { grupo: 'Almacenes', items: de('grupos-bienes', 'almacenes', 'insumos') },
  { grupo: 'Financiero', items: de('cobranzas', 'pagos-clientes', 'cuentas-bancarias', 'bancos') },
  { grupo: 'Recursos humanos', items: de('personal', 'cargos', 'profesiones') },
  { grupo: 'Secretaría', items: de('citas') },
  { grupo: 'Planificación', items: de('paises', 'departamentos', 'provincias', 'municipios', 'zonas', 'vias-acceso') },
  { grupo: 'Seguridad', items: de('usuarios') },
]
