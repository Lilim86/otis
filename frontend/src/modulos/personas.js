import { personas } from '@/services'

// Cada módulo se describe una sola vez: el componente ModuloCrud arma la tabla y el formulario.
// tipo: texto | email | fecha | numero | select. Un select toma sus opciones de un catálogo
// (`catalogo`) o de otro módulo (`remoto`), y puede depender de otro campo (`depende`).
const ubicacion = [
  { clave: 'pais_id', etiqueta: 'País', tipo: 'select', remoto: { servicio: 'paises', texto: 'nombre' } },
  { clave: 'departamento_id', etiqueta: 'Departamento', tipo: 'select', remoto: { servicio: 'departamentos', texto: 'nombre', depende: 'pais_id' } },
  { clave: 'provincia_id', etiqueta: 'Provincia', tipo: 'select', remoto: { servicio: 'provincias', texto: 'nombre', depende: 'departamento_id' } },
  { clave: 'municipio_id', etiqueta: 'Municipio', tipo: 'select', remoto: { servicio: 'municipios', texto: 'nombre', depende: 'provincia_id' } },
  { clave: 'zona_id', etiqueta: 'Zona / Barrio', tipo: 'select', remoto: { servicio: 'zonas', texto: 'nombre', depende: 'municipio_id' } },
  { clave: 'via_acceso_id', etiqueta: 'Vía de acceso (calle, av.)', tipo: 'select', remoto: { servicio: 'viasAcceso', texto: 'nombre', depende: 'zona_id' } },
]

export default {
  clave: 'personas',
  titulo: 'Personas',
  servicio: personas,
  aprobacion: true,
  busqueda: 'Buscar por nombre, documento, NIT o correo',
  columnas: [
    { clave: 'nombre', etiqueta: 'Nombre completo', valor: (p) => [p.primer_apellido, p.segundo_apellido, p.nombres].filter(Boolean).join(' ') },
    { clave: 'numero_documento', etiqueta: 'Documento', valor: (p) => `${p.numero_documento} ${p.expedido_en ?? ''}`.trim() },
    { clave: 'tipo_persona', etiqueta: 'Tipo', catalogo: 'tipos_persona' },
    { clave: 'telefono_celular', etiqueta: 'Celular' },
  ],
  filtros: [{ clave: 'tipo_persona', etiqueta: 'Tipo de persona', catalogo: 'tipos_persona' }],
  secciones: [
    {
      titulo: 'Identificación',
      campos: [
        { clave: 'tipo_documento', etiqueta: 'Tipo de documento', tipo: 'select', catalogo: 'tipos_documento', requerido: true, defecto: 'CI' },
        { clave: 'numero_documento', etiqueta: 'No. de documento', requerido: true },
        { clave: 'expedido_en', etiqueta: 'Expedido en', tipo: 'select', catalogo: 'expedido_en' },
        { clave: 'primer_apellido', etiqueta: 'Primer apellido', requerido: true },
        { clave: 'segundo_apellido', etiqueta: 'Segundo apellido' },
        { clave: 'nombres', etiqueta: 'Nombres', requerido: true },
        { clave: 'tipo_persona', etiqueta: 'Tipo de persona', tipo: 'select', catalogo: 'tipos_persona', requerido: true },
        { clave: 'nit', etiqueta: 'Número de NIT' },
        { clave: 'fecha_nacimiento', etiqueta: 'Fecha de nacimiento', tipo: 'fecha' },
        { clave: 'cargo_puesto', etiqueta: 'Cargo o puesto' },
      ],
    },
    {
      titulo: 'Contacto',
      campos: [
        { clave: 'telefono_celular', etiqueta: 'Teléfono celular', requerido: true },
        { clave: 'telefono_fijo', etiqueta: 'Teléfono fijo' },
        { clave: 'telefono_oficina', etiqueta: 'Teléfono de oficina' },
        { clave: 'correo_personal', etiqueta: 'Correo electrónico personal', tipo: 'email', requerido: true },
        { clave: 'correo_institucional', etiqueta: 'Correo institucional', tipo: 'email' },
      ],
    },
    {
      titulo: 'Lugar donde radica',
      campos: [
        { clave: 'nacionalidad_id', etiqueta: 'Nacionalidad', tipo: 'select', remoto: { servicio: 'paises', texto: 'nombre' } },
        ...ubicacion,
        { clave: 'edificio_id', etiqueta: 'Edificio', tipo: 'select', remoto: { servicio: 'edificios', texto: 'denominacion' } },
        { clave: 'nro_vivienda', etiqueta: 'Nro. vivienda' },
        { clave: 'nro_piso', etiqueta: 'Nro. piso' },
        { clave: 'nro_depto', etiqueta: 'Nro. depto.' },
      ],
    },
  ],
}
