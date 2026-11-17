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

// Reporte combinado (con sus tablas relacionadas) que exportan los botones PDF y Excel de cada módulo.
const REPORTE_DE_MODULO = {
  empresas: 'empresas',
  edificios: 'edificios',
  contratos: 'contratos',
  'cuotas-contrato': 'cuotas_pendientes',
  personas: 'personas',
  'pedidos-catalogo': 'pedidos_catalogo',
  equipos: 'equipos',
  'visitas-mantenimiento': 'visitas_mantenimiento',
  almacenes: 'inventario_almacen',
  insumos: 'kardex',
  'movimientos-almacen': 'movimientos',
  cobranzas: 'cobranzas',
  'pagos-clientes': 'pagos_clientes',
  'cuentas-bancarias': 'cuentas_bancarias',
  personal: 'personal',
  citas: 'agenda',
  paises: 'ubicaciones',
  departamentos: 'ubicaciones',
  provincias: 'ubicaciones',
  municipios: 'ubicaciones',
  zonas: 'ubicaciones',
  'vias-acceso': 'ubicaciones',
}
for (const [clave, reporte] of Object.entries(REPORTE_DE_MODULO)) {
  if (modulosPorClave[clave]) modulosPorClave[clave].reporte = reporte
}

const de = (...claves) => claves.map((c) => modulosPorClave[c])

// `enlaces` son pantallas propias (no CRUD) que viven dentro del mismo grupo del menú.
export const menu = [
  { grupo: 'Comercial', items: de('empresas', 'edificios', 'contratos', 'cuotas-contrato', 'pedidos-catalogo', 'personas') },
  {
    grupo: 'Técnico',
    items: de('equipos', 'visitas-mantenimiento'),
    enlaces: [
      { titulo: 'Mi ruta', ruta: 'tecnico-ruta', soloTecnico: true },
      { titulo: 'Cronograma y técnicos', ruta: 'cronograma', permiso: 'visitas-mantenimiento.ver' },
      { titulo: 'Ingeniería: rutas y confiabilidad', ruta: 'ingenieria', permiso: 'ingenieria.ver' },
    ],
  },
  {
    grupo: 'Almacenes',
    items: de('grupos-bienes', 'almacenes', 'insumos', 'movimientos-almacen'),
    enlaces: [{ titulo: 'Herramientas en poder', ruta: 'herramientas', permiso: 'movimientos-almacen.ver' }],
  },
  {
    grupo: 'Financiero',
    items: de('cobranzas', 'pagos-clientes', 'cuentas-bancarias', 'bancos'),
    enlaces: [
      { titulo: 'Tesorería: cobros de cobradores', ruta: 'tesoreria-cobros', permiso: 'ingresos-tesoreria.ver' },
      { titulo: 'Análisis de cobranza', ruta: 'cobranza-analisis', permiso: 'cobranzas.ver' },
    ],
  },
  { grupo: 'Recursos humanos', items: de('personal', 'cargos', 'profesiones') },
  { grupo: 'Secretaría', items: de('citas') },
  { grupo: 'Planificación', items: de('paises', 'departamentos', 'provincias', 'municipios', 'zonas', 'vias-acceso') },
  { grupo: 'Seguridad', items: de('usuarios') },
]
