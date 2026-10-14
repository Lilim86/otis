import { crearServicioCrud } from './crud'
import { http } from './http'

// Seguridad
export const usuarios = crearServicioCrud('usuarios')

// Planificación (geografía)
export const paises = crearServicioCrud('paises')
export const departamentos = crearServicioCrud('departamentos')
export const provincias = crearServicioCrud('provincias')
export const municipios = crearServicioCrud('municipios')
export const zonas = crearServicioCrud('zonas')
export const viasAcceso = crearServicioCrud('vias-acceso')

// Técnico
export const clasificadoresTecnicos = crearServicioCrud('clasificadores-tecnicos')
export const mantenimientos = crearServicioCrud('mantenimientos')
export const ascensores = crearServicioCrud('ascensores')
export const emergencias = crearServicioCrud('emergencias')
export const equipos = crearServicioCrud('equipos')
export const repuestos = crearServicioCrud('repuestos')

export const ordenesMantenimiento = {
  ...crearServicioCrud('ordenes-mantenimiento'),
  /** Finaliza la orden y descuenta del inventario el repuesto utilizado. */
  async finalizar(id) {
    const { data } = await http.post(`/api/ordenes-mantenimiento/${id}/finalizar`)
    return data.data
  },
}

// Recursos humanos
export const cargos = crearServicioCrud('cargos')
export const profesiones = crearServicioCrud('profesiones')
export const personal = crearServicioCrud('personal')

// Comercial
export const empresas = crearServicioCrud('empresas')
export const edificios = crearServicioCrud('edificios')
export const contratos = crearServicioCrud('contratos')

// Almacenes
export const insumos = crearServicioCrud('insumos')

// Financiero
export const bancos = crearServicioCrud('bancos')
export const cuentasBancarias = crearServicioCrud('cuentas-bancarias')
export const cobranzas = crearServicioCrud('cobranzas')

// ---- Módulos equivalentes al sistema SOFIA ----

// Planificación y comercial
export const personas = {
  ...crearServicioCrud('personas'),
  /** Flujo REG -> APR / ANL del SOFIA. */
  aprobar: (id) => http.post(`/api/personas/${id}/aprobar`).then((r) => r.data.data),
  anular: (id) => http.post(`/api/personas/${id}/anular`).then((r) => r.data.data),
}

// Fábrica para los módulos con flujo de aprobación (Aprobar / Anular del SOFIA).
function conAprobacion(recurso) {
  return {
    ...crearServicioCrud(recurso),
    aprobar: (id) => http.post(`/api/${recurso}/${id}/aprobar`).then((r) => r.data.data),
    anular: (id) => http.post(`/api/${recurso}/${id}/anular`).then((r) => r.data.data),
  }
}

export const gruposBienes = crearServicioCrud('grupos-bienes')
export const cuotasContrato = crearServicioCrud('cuotas-contrato')

export const equiposSofia = {
  ...conAprobacion('equipos'),
  /** Botón "Cálculos": precios de venta a partir del precio de compra FOB. */
  calcularPrecios: (datos) => http.post('/api/equipos/calcular-precios', datos).then((r) => r.data.data),
}

// Almacenes y compras
export const almacenes = {
  ...crearServicioCrud('almacenes'),
  inventario: (id) => http.get(`/api/almacenes/${id}/inventario`).then((r) => r.data.data),
}
export const movimientosAlmacen = conAprobacion('movimientos-almacen')
export const compras = conAprobacion('compras')
export const facturasProveedor = conAprobacion('facturas-proveedor')
export const ordenesCancelacion = conAprobacion('ordenes-cancelacion')

// Tesorería, fondos a rendir y RRHH
export const ingresosTesoreria = conAprobacion('ingresos-tesoreria')
export const traspasosCuentas = conAprobacion('traspasos-cuentas')
export const fondosRendir = conAprobacion('fondos-rendir')
export const rendicionesFondo = conAprobacion('rendiciones-fondo')
export const planillas = conAprobacion('planillas')
export const contratosTecnicos = conAprobacion('contratos-tecnicos')

export const conciliacion = (params) => http.get('/api/tesoreria/conciliacion', { params }).then((r) => r.data.data)

// Seguridad
export const roles = crearServicioCrud('seguridad/roles')
export const permisos = () => http.get('/api/seguridad/permisos').then((r) => r.data.data)
export const asignarRoles = (usuarioId, nombres) =>
  http.put(`/api/seguridad/usuarios/${usuarioId}/roles`, { roles: nombres }).then((r) => r.data.data)
export const accesos = (params) => http.get('/api/seguridad/accesos', { params }).then((r) => r.data)
