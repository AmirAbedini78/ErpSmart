import { translate } from '@/Core/i18n'

import WarehousesIndex from './views/WarehousesIndex.vue'
import WarehousesCreate from './views/WarehousesCreate.vue'
import WarehousesView from './views/WarehousesView.vue'

export default [
  {
    path: '/warehouses',
    name: 'warehouse-index',
    component: WarehousesIndex,
    meta: { title: translate('warehouse::warehouse.warehouses') },
  },
  {
    path: '/warehouses/create',
    name: 'create-warehouse',
    component: WarehousesCreate,
    meta: { title: translate('warehouse::warehouse.create') },
  },
  {
    path: '/warehouses/:id',
    name: 'view-warehouse',
    component: WarehousesView,
    meta: { title: translate('warehouse::warehouse.warehouse') },
  },
]
