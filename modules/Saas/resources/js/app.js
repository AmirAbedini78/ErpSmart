import GrapesJSEditor from './components/pages/GrapesJSEditor.vue'
import SaasSettingManager from './components/SaasSettingManager.vue'
import TenantRegister from './components/TenantRegister.vue'
import Link from './components/UI/Link.vue'
import PackageCreate from './views/package/PackageCreate.vue'
import PackageEdit from './views/package/PackageEdit.vue'
import PackageIndex from './views/package/PackageIndex.vue'
import PageCreate from './views/page/PageCreate.vue'
import PageIndex from './views/page/PageIndex.vue'
import QuotaCreate from './views/quota/QuotaCreate.vue'
import QuotaIndex from './views/quota/QuotaIndex.vue'
import SaasIndex from './views/SaasIndex.vue'
import TenantCreate from './views/tenant/TenantCreate.vue'
import TenantEdit from './views/tenant/TenantEdit.vue'
import TenantIndex from './views/tenant/TenantIndex.vue'
import { SidebarManager } from './sidebarInitializer'

if (window.Innoclapps) {
  Innoclapps.booting(function (app, router) {
    app.component('SidebarManager', SidebarManager)
    app.component('LinkComponent', Link)
    app.component('TenantRegister', TenantRegister)

    router.addRoute({
      path: '/saas',
      name: 'saas-index',
      component: SaasIndex,
      meta: {
        title: 'saas',
        subRoutes: ['tenants', 'packages', 'pages'],
      },
      children: [
        {
          path: 'tenants',
          name: 'tenants-index',
          component: TenantIndex,
          meta: {
            title: 'Tenants',
          },
          subRoutes: ['create', 'edit'],
          children: [
            {
              path: 'create',
              name: 'create-tenant',
              component: TenantCreate,
              meta: { title: 'Create Tenant' },
            },
            {
              path: ':id/edit',
              name: 'edit-tenant',
              component: TenantEdit,
              meta: { title: 'Edit Tenant' },
            },
          ],
        },
        {
          path: 'packages',
          name: 'packages-index',
          component: PackageIndex,
          meta: { title: 'Packages' },
          children: [
            {
              path: 'create',
              name: 'create-package',
              component: PackageCreate,
              meta: { title: 'Create Package' },
            },
            {
              path: ':id/edit',
              name: 'edit-package',
              component: PackageEdit,
              meta: { title: 'Edit Package' },
            },
          ],
        },
        {
          path: 'quotas',
          name: 'quotas-index',
          component: QuotaIndex,
          meta: { title: 'Quotas' },
          children: [
            {
              path: 'create',
              name: 'create-quota',
              component: QuotaCreate,
              meta: { title: 'Create Quota' },
            },
          ],
        },

        {
          path: 'pages',
          name: 'pages-index',
          component: PageIndex,
          meta: { title: 'pages' },
          children: [
            {
              path: 'create',
              name: 'create-page',
              component: PageCreate,
              meta: { title: 'Create Page' },
            },
          ],
        },

        {
          path: '/pages/:id/edit',
          name: 'grapejs-edit',
          component: GrapesJSEditor,
          props: true,
        },

        {
          path: 'editor',
          name: 'editor',
          component: GrapesJSEditor,
          meta: {
            title: 'Editor',
          },
        },
      ],
    })

    router.addRoute('settings', {
      path: '/settings/saas',
      component: SaasSettingManager,
      name: 'saas-setting',
    })
    const sidebarManager = new SidebarManager(router)

    setTimeout(() => {
      sidebarManager.initializeDropdowns()
    }, 100)
  })
}
