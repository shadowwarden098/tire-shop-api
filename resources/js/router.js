import { createRouter, createWebHistory } from 'vue-router'
import Dashboard  from './components/Dashboard.vue'
import Products   from './components/Products.vue'
import Customers  from './components/Customers.vue'
import Sales      from './components/Sales.vue'
import Services   from './components/Services.vue'
import Reports    from './components/Reports.vue'
import Login      from './components/Login.vue'

const routes = [
    { path: '/',          component: Login,     name: 'login',     meta: { isLogin: true } },
    { path: '/dashboard', component: Dashboard, name: 'dashboard' },
    { path: '/products',  component: Products,  name: 'products'  },
    { path: '/customers', component: Customers, name: 'customers' },
    { path: '/sales',     component: Sales,     name: 'sales'     },
    { path: '/services',  component: Services,  name: 'services'  },
    { path: '/reports',   component: Reports,   name: 'reports',  meta: { adminOnly: true } },
    { path: '/:pathMatch(.*)*', redirect: '/' },
]

const router = createRouter({
    history: createWebHistory(),
    routes,
})

router.beforeEach((to, from) => {
    const token    = localStorage.getItem('auth_token')
    const userJson = localStorage.getItem('auth_user')
    const role     = localStorage.getItem('auth_role')
    const user     = userJson ? JSON.parse(userJson) : null

    if (to.meta.isLogin) {
        if (token) return '/dashboard'
        return true
    }

    if (!token && !role) return '/'

    if (to.meta.adminOnly) {
        if (token && user && user.role === 'admin') return true
        return '/'
    }

    return true
})

export default router