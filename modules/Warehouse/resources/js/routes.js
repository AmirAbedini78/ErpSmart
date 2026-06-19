import { translate } from '@/Core/i18n'

import WarehousesCreate from './views/WarehousesCreate.vue'
import WarehousesEdit from './views/WarehousesEdit.vue'
import WarehousesIndex from './views/WarehousesIndex.vue'
import WarehousesView from './views/WarehousesView.vue'

export default [
  {
    path: '/warehouses',
    name: 'warehouse-index',
    component: WarehousesIndex,
    meta: { title: translate('warehouse::warehouse.warehouses') },
    children: [
      {
        path: 'create',
        name: 'create-warehouse',
        component: WarehousesCreate,
        meta: { title: translate('warehouse::warehouse.create') },
      },
      {
        path: ':id/edit',
        name: 'edit-warehouse',
        component: WarehousesEdit,
        meta: { title: translate('warehouse::warehouse.edit') },
      },
    ],
  },
  {
    path: '/warehouses/:id',
    name: 'view-warehouse',
    component: WarehousesView,
    meta: { title: translate('warehouse::warehouse.warehouse') },
  },
]
