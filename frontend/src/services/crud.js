import { http } from './http'

/**
 * Crea el servicio CRUD de un recurso de la API.
 *
 *   const paises = crearServicioCrud('paises')
 *   paises.listar({ page: 1, q: 'bol', estado: true })  // paginado: { data, meta, links }
 *   paises.todos({ estado: true })                      // sin paginar, para listas desplegables
 *
 * Filtros de listar() y papelera(): q, estado, ordenar_por, direccion, per_page,
 * creado_por, creado_desde, creado_hasta y los propios de cada módulo (pais_id, etc.).
 */
export function crearServicioCrud(recurso) {
  const base = `/api/${recurso}`

  return {
    async listar(params = {}) {
      const { data } = await http.get(base, { params })
      return data
    },
    async todos(params = {}) {
      const { data } = await http.get(base, { params: { ...params, all: 1 } })
      return data.data
    },
    async obtener(id) {
      const { data } = await http.get(`${base}/${id}`)
      return data.data
    },
    async crear(datos) {
      const { data } = await http.post(base, datos)
      return data.data
    },
    async actualizar(id, datos) {
      const { data } = await http.put(`${base}/${id}`, datos)
      return data.data
    },
    /** Actualiza solo los campos enviados. */
    async modificar(id, datos) {
      const { data } = await http.patch(`${base}/${id}`, datos)
      return data.data
    },
    /** Eliminación lógica: el registro pasa a la papelera. */
    async eliminar(id) {
      await http.delete(`${base}/${id}`)
    },
    /** Registros eliminados. Acepta los mismos filtros que listar(). */
    async papelera(params = {}) {
      const { data } = await http.get(`${base}/papelera`, { params })
      return data
    },
    async restaurar(id) {
      const { data } = await http.post(`${base}/${id}/restaurar`)
      return data.data
    },
    /** Desactiva el registro sin eliminarlo. */
    async darDeBaja(id) {
      const { data } = await http.post(`${base}/${id}/baja`)
      return data.data
    },
    /** Reactiva un registro dado de baja. */
    async darDeAlta(id) {
      const { data } = await http.post(`${base}/${id}/alta`)
      return data.data
    },
  }
}
