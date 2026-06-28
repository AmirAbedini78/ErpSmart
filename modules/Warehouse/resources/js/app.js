import routes from './routes'
import WarehouseFloatingModal from './components/WarehouseFloatingModal.vue'

if (window.Innoclapps) {
  Innoclapps.booting((app, router) => {
    routes.forEach(route => router.addRoute(route))
  })
}


Innoclapps.booting(app => {
  app.component('WarehouseFloatingModal', WarehouseFloatingModal)
})
