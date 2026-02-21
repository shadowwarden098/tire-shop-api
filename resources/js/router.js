import { createRouter, createWebHistory } from 'vue-router'
import Dashboard  from './components/Dashboard.vue'
import Products   from './components/Products.vue'
import Customers  from './components/Customers.vue'
import Sales      from './components/Sales.vue'
import Services   from './components/Services.vue'
import Reports    from './components/Reports.vue'
import Login      from './components/Login.vue'

const routes = [
    // Pantalla de bienvenida / selección de rol
    { path: '/login', component: Login, name: 'login', meta: { public: true } },

    // App principal
    { path: '/',          component: Dashboard, name: 'dashboard' },
    { path: '/products',  component: Products,  name: 'products'  },
    { path: '/customers', component: Customers, name: 'customers' },
    { path: '/sales',     component: Sales,     name: 'sales'     },
    { path: '/services',  component: Services,  name: 'services'  },
    { path: '/reports',   component: Reports,   name: 'reports',  meta: { adminOnly: true } },
]

const router = createRouter({
    history: createWebHistory(),
    routes,
})

router.beforeEach((to, from, next) => {
    // Ruta pública siempre pasa
    if (to.meta.public) return next()

    const token    = localStorage.getItem('auth_token')
    const userJson = localStorage.getItem('auth_user')
    const role     = localStorage.getItem('auth_role')
    const user     = userJson ? JSON.parse(userJson) : null

    // Si no eligió rol todavía → mostrar pantalla de bienvenida
    if (!role && !token) {
        return next('/login')
    }

    // Ruta solo admin
    if (to.meta.adminOnly) {
        if (token && user && user.role === 'admin') {
            return next()
        }
        return next('/login')
    }

    next()
})

export default router
