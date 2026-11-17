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
        { path: 'cronograma', name: 'cronograma', component: () => import('@/views/CronogramaView.vue') },
        { path: 'herramientas', name: 'herramientas', component: () => import('@/views/HerramientasView.vue') },
        { path: 'ingenieria', name: 'ingenieria', component: () => import('@/views/IngenieriaView.vue') },
        { path: 'cobranza-analisis', name: 'cobranza-analisis', component: () => import('@/views/CobranzaAnalisisView.vue') },
        { path: 'mi-ruta', name: 'tecnico-ruta', component: () => import('@/views/TecnicoRutaView.vue') },
        { path: 'tesoreria/cobros', name: 'tesoreria-cobros', component: () => import('@/views/TesoreriaCobrosView.vue') },
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
        { path: 'catalogo', name: 'portal-catalogo', component: () => import('@/portal/PortalCatalogo.vue') },
      ],
    },
    // Portal del cobrador: su ruta de cobranza y sus recibos. Solo para cobradores enlazados a su persona.
    {
      path: '/cobrador',
      component: () => import('@/layouts/CobradorLayout.vue'),
      meta: { cobrador: true },
      children: [
        { path: '', name: 'cobrador-ruta', component: () => import('@/cobrador/CobradorRuta.vue') },
        { path: 'recibos', name: 'cobrador-recibos', component: () => import('@/cobrador/CobradorRecibos.vue') },
      ],
    },
    { path: '/:pathMatch(.*)*', redirect: '/' },
  ],
})

const inicioDe = (u) => (u.es_cliente ? 'portal-inicio' : u.es_cobrador ? 'cobrador-ruta' : 'dashboard')

router.beforeEach(async (to) => {
  const sesion = useSesionStore()
  await sesion.cargar()

  if (!to.meta.publica && !sesion.autenticado) return { name: 'login' }
  if (to.name === 'login' && sesion.autenticado) return { name: inicioDe(sesion.usuario) }

  // Cada tipo de usuario ve solo su parte: los clientes su portal, los cobradores el suyo y el personal el sistema.
  if (sesion.autenticado && !to.meta.publica) {
    const u = sesion.usuario
    const zona = to.matched.some((r) => r.meta.portal) ? 'portal' : to.matched.some((r) => r.meta.cobrador) ? 'cobrador' : 'sistema'
    const suya = u.es_cliente ? 'portal' : u.es_cobrador ? 'cobrador' : 'sistema'
    if (zona !== suya) return { name: inicioDe(u) }
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
