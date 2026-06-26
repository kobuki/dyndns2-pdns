import { createRouter, createWebHistory } from 'vue-router'
import Dashboard from '../views/Dashboard.vue'
import Users from '../views/Users.vue'
import Hostnames from '../views/Hostnames.vue'
import Permissions from '../views/Permissions.vue'
import Changelog from '../views/Changelog.vue'
import UrlGenerator from '../views/UrlGenerator.vue'

const routes = [
  { path: '/', redirect: '/dashboard' },
  { path: '/dashboard', component: Dashboard },
  { path: '/users', component: Users },
  { path: '/hostnames', component: Hostnames },
  { path: '/permissions', component: Permissions },
  { path: '/changelog', component: Changelog },
  { path: '/url-generator', component: UrlGenerator },
]

export default createRouter({
  history: createWebHistory('/admin/dist/'),
  routes,
})
