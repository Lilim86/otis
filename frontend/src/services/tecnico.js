import { http } from './http'

/** Vista del técnico: solo sus visitas, el material que usa y las herramientas que tiene prestadas. */
export const tecnico = {
  ruta: (params) => http.get('/api/tecnico/ruta', { params }).then((r) => r.data.data),
  iniciar: (id) => http.post(`/api/tecnico/visitas/${id}/iniciar`).then((r) => r.data.data),
  completar: (id, informe, materiales = []) => http.post(`/api/tecnico/visitas/${id}/completar`, { informe, materiales }).then((r) => r.data.data),
  materiales: () => http.get('/api/tecnico/materiales').then((r) => r.data.data),
  herramientas: () => http.get('/api/tecnico/herramientas').then((r) => r.data.data),
}
