<?php

/**
 * Warehouse detail edit route fix.
 *
 * Purpose:
 * The Warehouse detail page is a custom Vue view. Some previous detail-page fixes
 * stabilized notes/media tabs but left the top Edit action routed through an
 * unstable or wrong route target. This patch standardizes the detail -> edit
 * navigation as a direct canonical path: /warehouses/{id}/edit.
 */

$root = dirname(__DIR__);
$viewPath = $root.'/modules/Warehouse/resources/js/views/WarehousesView.vue';

function fail(string $message): void
{
    fwrite(STDERR, "ERROR: {$message}\n");
    exit(1);
}

if (! file_exists($viewPath)) {
    fail("View not found: {$viewPath}");
}

$timestamp = date('YmdHis');
copy($viewPath, $viewPath.'.bak-'.$timestamp);
echo "Backup created: {$viewPath}.bak-{$timestamp}\n";

$contents = file_get_contents($viewPath);
$original = $contents;

// 1) Ensure useRouter is imported with useRoute.
if (str_contains($contents, "from 'vue-router'")) {
    $contents = preg_replace_callback(
        "/import\s*\{([^}]*)\}\s*from\s*['\"]vue-router['\"]/",
        function ($matches) {
            $imports = array_map('trim', explode(',', $matches[1]));
            $imports = array_values(array_filter($imports));
            if (! in_array('useRouter', $imports, true)) {
                $imports[] = 'useRouter';
            }
            if (! in_array('useRoute', $imports, true)) {
                array_unshift($imports, 'useRoute');
            }
            return 'import { '.implode(', ', array_unique($imports))." } from 'vue-router'";
        },
        $contents,
        1
    );
} else {
    $contents = preg_replace(
        "/(<script\s+setup[^>]*>\s*)/",
        "$1\nimport { useRoute, useRouter } from 'vue-router'\n",
        $contents,
        1
    );
}

// 2) Ensure router instance exists after route declaration.
if (! preg_match('/\bconst\s+router\s*=\s*useRouter\s*\(/', $contents)) {
    if (preg_match('/\bconst\s+route\s*=\s*useRoute\s*\(\s*\)/', $contents)) {
        $contents = preg_replace(
            '/(\bconst\s+route\s*=\s*useRoute\s*\(\s*\)\s*)/',
            "$1\nconst router = useRouter()\n",
            $contents,
            1
        );
    } else {
        $contents = preg_replace(
            "/(<script\s+setup[^>]*>\s*(?:\nimport[^\n]*\n)*)/",
            "$1\nconst route = useRoute()\nconst router = useRouter()\n",
            $contents,
            1
        );
    }
}

// 3) Ensure canonical resource/id/edit path helpers exist.
$helperBlock = <<<'JS'

const editPath = computed(() => `/${resourceName}/${warehouseId.value}/edit`)

function goToEdit() {
  if (!warehouseId.value) {
    return
  }

  router.push(editPath.value)
}
JS;

if (! preg_match('/\bconst\s+editPath\s*=\s*computed\s*\(/', $contents)) {
    if (preg_match('/\bconst\s+resourcePath\s*=\s*computed\s*\([^\n]*\)\s*/', $contents)) {
        $contents = preg_replace(
            '/(\bconst\s+resourcePath\s*=\s*computed\s*\([^\n]*\)\s*)/',
            "$1".$helperBlock."\n",
            $contents,
            1
        );
    } elseif (preg_match('/\bconst\s+warehouseId\s*=\s*computed\s*\([^\n]*\)\s*/', $contents)) {
        $contents = preg_replace(
            '/(\bconst\s+warehouseId\s*=\s*computed\s*\([^\n]*\)\s*)/',
            "$1".$helperBlock."\n",
            $contents,
            1
        );
    } else {
        // Fallback: create helpers after router declaration.
        $contents = preg_replace(
            '/(\bconst\s+router\s*=\s*useRouter\s*\(\s*\)\s*)/',
            "$1\nconst resourceName = Innoclapps.resourceName('warehouses')\nconst warehouseId = computed(() => route.params.id)\nconst resourcePath = computed(() => `/${resourceName}/${warehouseId.value}`)\n".$helperBlock."\n",
            $contents,
            1
        );
    }
}

// 4) Normalize the visible Edit button to use goToEdit().
// This targets an IButton block that contains edit translation/text, without
// assuming the previous wrong route name.
$patchedButton = false;
$contents = preg_replace_callback(
    '/<IButton\b[\s\S]*?<\/IButton>/i',
    function ($matches) use (&$patchedButton) {
        $block = $matches[0];
        if ($patchedButton) {
            return $block;
        }

        $looksLikeEdit = str_contains($block, 'core::app.edit')
            || str_contains($block, 'warehouse.edit')
            || preg_match('/>\s*(Edit|ویرایش)\s*</iu', $block);

        if (! $looksLikeEdit) {
            return $block;
        }

        $block = preg_replace('/\s+:to="[^"]*"/i', '', $block);
        $block = preg_replace('/\s+to="[^"]*"/i', '', $block);
        $block = preg_replace('/\s+@click(?:\.prevent)?="[^"]*"/i', '', $block);

        if (! str_contains($block, '@click.prevent="goToEdit"')) {
            $block = preg_replace('/<IButton\b/i', '<IButton @click.prevent="goToEdit"', $block, 1);
        }

        $patchedButton = true;
        return $block;
    },
    $contents,
    1
);

// 5) If no IButton was detected, patch RouterLink/ILink style edit controls.
if (! $patchedButton) {
    $contents = preg_replace_callback(
        '/<(RouterLink|ILink|IButtonLink)\b[\s\S]*?<\/\1>/i',
        function ($matches) use (&$patchedButton) {
            $block = $matches[0];
            if ($patchedButton) {
                return $block;
            }

            $looksLikeEdit = str_contains($block, 'core::app.edit')
                || str_contains($block, 'warehouse.edit')
                || preg_match('/>\s*(Edit|ویرایش)\s*</iu', $block);

            if (! $looksLikeEdit) {
                return $block;
            }

            $block = preg_replace('/\s+:to="[^"]*"/i', '', $block);
            $block = preg_replace('/\s+to="[^"]*"/i', '', $block);
            $block = preg_replace('/\s+@click(?:\.prevent)?="[^"]*"/i', '', $block);
            $block = preg_replace('/<'.$matches[1].'\b/i', '<'.$matches[1].' @click.prevent="goToEdit"', $block, 1);
            $patchedButton = true;
            return $block;
        },
        $contents,
        1
    );
}

// 6) Add an explicit safe edit button only if no edit control exists at all.
if (! $patchedButton && preg_match('/<template>[\s\S]*?<\/template>/', $contents)) {
    // Keep this conservative: insert a small action block after the first opening container div.
    $buttonMarkup = <<<'VUE'

        <IButton
          class="mb-3"
          variant="primary"
          @click.prevent="goToEdit"
        >
          {{ $t('core::app.edit') }}
        </IButton>
VUE;

    $contents = preg_replace('/(<template>[\s\S]*?<div[^>]*>)/', '$1'.$buttonMarkup, $contents, 1);
    $patchedButton = true;
}

if ($contents === $original) {
    echo "No textual change was required. The file may already be patched.\n";
} else {
    file_put_contents($viewPath, $contents);
    echo "Warehouse detail edit route fix applied.\n";
}

echo "Patched edit button: ".($patchedButton ? 'yes' : 'no')."\n";
echo "Next commands:\n";
echo "  php artisan optimize:clear\n";
echo "  php artisan cache:clear\n";
echo "  npm run build\n";
