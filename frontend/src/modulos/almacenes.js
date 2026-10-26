import { gruposBienes, almacenes, insumos } from '@/services'
import { regional, bs } from './comun'

export const almacenesModulos = [
  {
    clave: 'grupos-bienes',
    titulo: 'Grupos de bienes',
    servicio: gruposBienes,
    aprobacion: false,
    busqueda: 'Buscar por código o nombre',
    columnas: [
      { clave: 'codigo', etiqueta: 'Código' },
      { clave: 'nombre', etiqueta: 'Nombre' },
      { clave: 'nivel', etiqueta: 'Nivel', valor: (f) => ['', 'Grupo', 'Subgrupo 1', 'Subgrupo 2'][f.nivel] ?? f.nivel },
      { clave: 'padre', etiqueta: 'Pertenece a', valor: (f) => f.padre?.nombre ?? '—' },
    ],
    filtros: [],
    secciones: [
      {
        titulo: 'Grupo',
        campos: [
          { clave: 'codigo', etiqueta: 'Código', requerido: true },
          { clave: 'nombre', etiqueta: 'Nombre', requerido: true },
          {
            clave: 'nivel', etiqueta: 'Nivel', tipo: 'select', requerido: true,
            opciones: [{ valor: 1, texto: 'Grupo' }, { valor: 2, texto: 'Subgrupo 1' }, { valor: 3, texto: 'Subgrupo 2' }],
          },
          { clave: 'padre_id', etiqueta: 'Pertenece a (solo subgrupos)', tipo: 'select', remoto: { servicio: 'gruposBienes', texto: 'nombre' } },
        ],
      },
    ],
  },
  {
    clave: 'almacenes',
    titulo: 'Almacenes',
    servicio: almacenes,
    aprobacion: false,
    busqueda: 'Buscar por código o nombre',
    columnas: [
      { clave: 'codigo', etiqueta: 'Código' },
      { clave: 'nombre', etiqueta: 'Almacén' },
      { clave: 'tipo_almacen', etiqueta: 'Tipo', catalogo: 'tipos_almacen' },
      { clave: 'regional', etiqueta: 'Regional', valor: (f) => f.regional?.nombre ?? '—' },
    ],
    filtros: [{ clave: 'tipo_almacen', etiqueta: 'Tipo', catalogo: 'tipos_almacen' }],
    secciones: [
      {
        titulo: 'Almacén',
        campos: [
          { clave: 'codigo', etiqueta: 'Código', requerido: true },
          { clave: 'nombre', etiqueta: 'Nombre', requerido: true },
          { clave: 'tipo_almacen', etiqueta: 'Tipo', tipo: 'select', catalogo: 'tipos_almacen', requerido: true },
          regional,
        ],
      },
    ],
  },
  {
    clave: 'insumos',
    titulo: 'Bienes e insumos (kardex)',
    servicio: insumos,
    aprobacion: true,
    busqueda: 'Buscar por código, descripción, marca o modelo',
    columnas: [
      { clave: 'codigo', etiqueta: 'Código' },
      { clave: 'descripcion', etiqueta: 'Descripción' },
      { clave: 'tipo', etiqueta: 'Tipo' },
      { clave: 'grupo', etiqueta: 'Grupo', valor: (f) => f.grupo?.nombre ?? '—' },
      { clave: 'stock_actual', etiqueta: 'Stock' },
      { clave: 'precio_compra_bs', etiqueta: 'Compra', valor: (f) => bs(f.precio_compra_bs) },
    ],
    filtros: [],
    secciones: [
      {
        titulo: 'Bien',
        campos: [
          { clave: 'codigo', etiqueta: 'Código', requerido: true },
          { clave: 'descripcion', etiqueta: 'Descripción', requerido: true },
          { clave: 'tipo', etiqueta: 'Tipo', tipo: 'select', catalogo: 'tipos_insumo', requerido: true },
          { clave: 'grupo_id', etiqueta: 'Grupo', tipo: 'select', remoto: { servicio: 'gruposBienes', texto: 'nombre', params: { nivel: 1 } } },
          { clave: 'unidad_medida', etiqueta: 'Unidad', tipo: 'select', catalogo: 'unidades_medida' },
          { clave: 'marca', etiqueta: 'Marca' },
          { clave: 'modelo', etiqueta: 'Modelo' },
          { clave: 'stock_actual', etiqueta: 'Stock actual', tipo: 'numero', requerido: true, defecto: 0 },
          { clave: 'stock_minimo', etiqueta: 'Stock mínimo', tipo: 'numero' },
          { clave: 'rotacion', etiqueta: 'Rotación', tipo: 'select', catalogo: 'rotacion' },
          { clave: 'caracteristicas', etiqueta: 'Características', tipo: 'textarea' },
        ],
      },
      {
        titulo: 'Precios (Bs)',
        campos: [
          { clave: 'precio_compra_bs', etiqueta: 'Compra', tipo: 'numero' },
          { clave: 'precio_venta_base_bs', etiqueta: 'Venta base', tipo: 'numero' },
          { clave: 'precio_venta_cliente_bs', etiqueta: 'Venta al cliente', tipo: 'numero' },
        ],
      },
    ],
  },
]
