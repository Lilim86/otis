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
      ],
    },
    { path: '/:pathMatch(.*)*', redirect: '/' },
  ],
})

router.beforeEach(async (to) => {
  const sesion = useSesionStore()
  await sesion.cargar()

  if (!to.meta.publica && !sesion.autenticado) return { name: 'login' }
  if (to.name === 'login' && sesion.autenticado) return { name: 'dashboard' }
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
