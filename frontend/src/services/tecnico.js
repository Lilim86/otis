import { http } from './http'

/** Vista del técnico: solo sus visitas. */
export const tecnico = {
  ruta: (params) => http.get('/api/tecnico/ruta', { params }).then((r) => r.data.data),
  completar: (id, informe) => http.post(`/api/tecnico/visitas/${id}/completar`, { informe }).then((r) => r.data.data),
}
