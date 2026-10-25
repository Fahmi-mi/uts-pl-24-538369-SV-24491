import { createRouter, createWebHistory } from 'vue-router'

const routes = [
  {
    path: '/',
    redirect: '/gadgets',
  },
  {
    path: '/login',
    name: 'login',
    component: () => import('../views/LoginView.vue'),
  },
  {
    path: '/gadgets',
    name: 'gadgets',
    component: () => import('../views/GadgetList.vue'),
    meta: { auth: true },
  },
  {
    path: '/gadgets/new',
    name: 'gadget-create',
    component: () => import('../views/GadgetForm.vue'),
    meta: { auth: true },
  },
  {
    path: '/gadgets/:id/edit',
    name: 'gadget-edit',
    component: () => import('../views/GadgetForm.vue'),
    meta: { auth: true },
  },
  {
    path: '/gadgets/:id',
    name: 'gadget-detail',
    component: () => import('../views/GadgetDetail.vue'),
    meta: { auth: true },
  },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

export default router
