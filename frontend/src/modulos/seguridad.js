import { usuarios } from '@/services'

export const seguridad = [
  {
    clave: 'usuarios',
    titulo: 'Usuarios del sistema',
    servicio: usuarios,
    aprobacion: false,
    busqueda: 'Buscar por nombre, usuario o correo',
    columnas: [
      { clave: 'name', etiqueta: 'Nombre' },
      { clave: 'username', etiqueta: 'Usuario' },
      { clave: 'email', etiqueta: 'Correo' },
      { clave: 'roles', etiqueta: 'Rol', valor: (f) => f.roles?.join(', ') || 'Sin rol' },
    ],
    filtros: [],
    secciones: [
      {
        titulo: 'Cuenta',
        campos: [
          { clave: 'name', etiqueta: 'Nombre completo', requerido: true },
          { clave: 'email', etiqueta: 'Correo electrónico', tipo: 'email', requerido: true },
          { clave: 'roles', etiqueta: 'Rol', tipo: 'select', arreglo: true, remoto: { servicio: 'roles', texto: 'name', valor: 'name' } },
          { clave: 'empresa_id', etiqueta: 'Empresa (solo para rol Cliente)', tipo: 'select', remoto: { servicio: 'empresas', texto: 'denominacion' } },
          { clave: 'personal_id', etiqueta: 'Persona del personal (solo para técnicos)', tipo: 'select', remoto: { servicio: 'personal', texto: 'apellidos' } },
        ],
      },
    ],
  },
]
