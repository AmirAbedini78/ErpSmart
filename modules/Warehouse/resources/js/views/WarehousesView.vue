<template>
  <MainLayout>
    <div class="mx-auto max-w-5xl py-6">
      <ITextDisplay text="View Warehouse" class="mb-4" />

      <div class="rounded-lg border border-neutral-200 bg-white p-4 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
        View Warehouse #{{ $route.params.id }}
      </div>
    </div>
  </MainLayout>
</template>

