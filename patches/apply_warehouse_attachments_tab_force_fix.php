<?php
/**
 * Force-fix Warehouse Attachments tab visibility.
 *
 * This patch is intentionally frontend-focused. It does not alter the Notes
 * backend contract and it does not overwrite the whole Warehouse view. It only
 * ensures that the Core ResourceMediaPanel is imported, rendered as a real tab
 * panel, and receives a normalized media array/count.
 *
 * Run from project root:
 *   docker compose exec app php patches/apply_warehouse_attachments_tab_force_fix.php
 */

function fail_step(string $message): never
{
    fwrite(STDERR, "ERROR: {$message}\n");
    exit(1);
}

function read_file_or_fail(string $path): string
{
    if (! is_file($path)) {
        fail_step("File not found: {$path}");
    }

    $contents = file_get_contents($path);

    if ($contents === false) {
        fail_step("Unable to read file: {$path}");
    }

    return $contents;
}

function write_if_changed(string $path, string $old, string $new): void
{
    if ($old === $new) {
        echo "No changes needed: {$path}\n";
        return;
    }

    $backup = $path.'.bak-attachments-tab-'.date('YmdHis');

    if (! copy($path, $backup)) {
        fail_step("Unable to create backup: {$backup}");
    }

    if (file_put_contents($path, $new) === false) {
        fail_step("Unable to write file: {$path}");
    }

    echo "Backup created: {$backup}\n";
    echo "Updated: {$path}\n";
}

$viewPath = 'modules/Warehouse/resources/js/views/WarehousesView.vue';
$view = read_file_or_fail($viewPath);
$viewNew = $view;

// 1) Ensure ResourceMediaPanel import exists exactly once.
$mediaImport = "import ResourceMediaPanel from '@/Core/components/Resource/ResourceMediaPanel.vue'";

if (! str_contains($viewNew, $mediaImport)) {
    $notePanelImport = "import RecordTabNotePanel from '@/Notes/components/RecordTabNotePanel.vue'";

    if (! str_contains($viewNew, $notePanelImport)) {
        fail_step('Could not locate RecordTabNotePanel import anchor. Please send WarehousesView.vue.');
    }

    $viewNew = str_replace($notePanelImport, $notePanelImport."\n".$mediaImport, $viewNew);
}

// 2) Ensure Attachments tab exists after the Notes tab.
if (! str_contains($viewNew, "{{ \$t('core::app.attachments') }}")) {
    $pattern = '/(?<noteTab>\s*<RecordTabNote\s+[^>]*:resource-name="resourceName"[^>]*:resource-id="safeResource\.id"[^>]*:resource="safeResource"\s*\/>)\s*/s';

    if (! preg_match($pattern, $viewNew, $matches)) {
        fail_step('Could not locate self-closing RecordTabNote block. Please send WarehousesView.vue.');
    }

    $replacement = $matches['noteTab'].<<<'VUE'


            <ITab>
              <Icon icon="PaperClip" />
              {{ $t('core::app.attachments') }}
            </ITab>
VUE;

    $viewNew = preg_replace($pattern, $replacement."\n", $viewNew, 1);
}

// 3) Ensure ResourceMediaPanel tab panel exists after the Notes panel.
if (! str_contains($viewNew, '<ResourceMediaPanel')) {
    $pattern = '/(?<notePanel>\s*<RecordTabNotePanel\s+id="tabPanel-notes"\s+scroll-element="#main"\s+:resource-name="resourceName"\s+:resource-id="safeResource\.id"\s+:resource="safeResource"\s*\/>)\s*/s';

    if (! preg_match($pattern, $viewNew, $matches)) {
        fail_step('Could not locate self-closing RecordTabNotePanel block. Please send WarehousesView.vue.');
    }

    $replacement = $matches['notePanel'].<<<'VUE'


          <ITabPanel>
            <ResourceMediaPanel
              :resource-name="resourceName"
              :resource-id="safeResource.id"
              :resource="safeResource"
            />
          </ITabPanel>
VUE;

    $viewNew = preg_replace($pattern, $replacement."\n", $viewNew, 1);
}

// 4) Normalize media fields in the safe resource object so the panel can render.
if (! str_contains($viewNew, 'media: Array.isArray(value.media) ? value.media : []')) {
    $anchor = "    notes_count: Number(value.notes_count || 0),\n";

    if (! str_contains($viewNew, $anchor)) {
        $anchor = "    notes: Array.isArray(value.notes) ? value.notes : [],\n";

        if (! str_contains($viewNew, $anchor)) {
            fail_step('Could not locate resource normalization anchor. Please send normalizeResource() block.');
        }

        $viewNew = str_replace(
            $anchor,
            $anchor."    media: Array.isArray(value.media) ? value.media : [],\n    media_count: Number(value.media_count || (Array.isArray(value.media) ? value.media.length : 0)),\n",
            $viewNew
        );
    } else {
        $viewNew = str_replace(
            $anchor,
            $anchor."\n    media: Array.isArray(value.media) ? value.media : [],\n    media_count: Number(value.media_count || (Array.isArray(value.media) ? value.media.length : 0)),\n",
            $viewNew
        );
    }
}

write_if_changed($viewPath, $view, $viewNew);

echo "\nWarehouse attachments tab force-fix applied.\n";
echo "Next commands:\n";
echo "  php artisan optimize:clear\n";
echo "  rm -f public/hot\n";
echo "  npm run build\n";
