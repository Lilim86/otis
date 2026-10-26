import { createRouter, createWebHistory } from 'vue-router'
import { useSesionStore } from '@/stores/sesion'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    { path: '/login', name: 'login', component: () => import('@/views/LoginView.vue'), meta: { publica: true } },
    {
      path: '/',
      component: () => import('@/layouts/AppLayout.vue'),
      children: [
        { path: '', name: 'dashboard', component: () => import('@/views/DashboardView.vue') },
        { path: 'modulos/:clave', name: 'modulo', component: () => import('@/views/ModuloView.vue'), props: true },
        { path: 'reportes', name: 'reportes', component: () => import('@/views/ReportesView.vue') },
        { path: 'mi-ruta', name: 'tecnico-ruta', component: () => import('@/views/TecnicoRutaView.vue') },
      ],
    },
    // Portal del cliente: pantalla propia, solo para usuarios vinculados a una empresa cliente.
    {
      path: '/portal',
      component: () => import('@/layouts/PortalLayout.vue'),
      meta: { portal: true },
      children: [
        { path: '', name: 'portal-inicio', component: () => import('@/portal/PortalInicio.vue') },
        { path: 'mantenimiento', name: 'portal-mantenimiento', component: () => import('@/portal/PortalMantenimiento.vue') },
        { path: 'cronograma', name: 'portal-cronograma', component: () => import('@/portal/PortalCronograma.vue') },
        { path: 'pagos', name: 'portal-pagos', component: () => import('@/portal/PortalPagos.vue') },
        { path: 'contratos', name: 'portal-contratos', component: () => import('@/portal/PortalContratos.vue') },
      ],
    },
    { path: '/:pathMatch(.*)*', redirect: '/' },
  ],
})

router.beforeEach(async (to) => {
  const sesion = useSesionStore()
  await sesion.cargar()

  if (!to.meta.publica && !sesion.autenticado) return { name: 'login' }
  if (to.name === 'login' && sesion.autenticado) return { name: sesion.usuario.es_cliente ? 'portal-inicio' : 'dashboard' }

  // Cada tipo de usuario ve solo su parte: los clientes el portal, el personal el sistema.
  if (sesion.autenticado && !to.meta.publica) {
    const esPortal = to.matched.some((r) => r.meta.portal)
    if (sesion.usuario.es_cliente && !esPortal) return { name: 'portal-inicio' }
    if (!sesion.usuario.es_cliente && esPortal) return { name: 'dashboard' }
  }
})

// Si la sesión expira a mitad de trabajo, el cliente HTTP avisa y se vuelve al login.
window.addEventListener('sesion-expirada', () => {
  const sesion = useSesionStore()
  if (sesion.autenticado) {
    sesion.usuario = null
    router.push({ name: 'login' })
  }
})

export default router
