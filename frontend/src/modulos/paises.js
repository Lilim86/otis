import { paises } from '@/services'

export default {
  clave: 'paises',
  titulo: 'Países',
  servicio: paises,
  aprobacion: false,
  busqueda: 'Buscar por nombre o código',
  columnas: [
    { clave: 'nombre', etiqueta: 'Nombre' },
    { clave: 'codigo_iso', etiqueta: 'Código ISO' },
  ],
  filtros: [],
  secciones: [
    {
      titulo: 'Datos',
      campos: [
        { clave: 'nombre', etiqueta: 'Nombre', requerido: true },
        { clave: 'codigo_iso', etiqueta: 'Código ISO' },
      ],
    },
  ],
}
