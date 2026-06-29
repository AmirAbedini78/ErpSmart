#!/usr/bin/env bash
set -euo pipefail

REPORT="storage/app/warehouse-activities-contract-discovery-report.md"
mkdir -p "$(dirname "$REPORT")"

section() {
  printf '\n## %s\n\n' "$1" >> "$REPORT"
}

subsection() {
  printf '\n### %s\n\n' "$1" >> "$REPORT"
}

code_file() {
  local title="$1"
  local file="$2"
  subsection "$title"
  if [ -f "$file" ]; then
    printf '```text\n' >> "$REPORT"
    sed -n '1,260p' "$file" >> "$REPORT" || true
    printf '\n```\n' >> "$REPORT"
  else
    printf '%s not found\n' "$file" >> "$REPORT"
  fi
}

grep_block() {
  local title="$1"
  shift
  subsection "$title"
  printf '```text\n' >> "$REPORT"
  "$@" >> "$REPORT" 2>&1 || true
  printf '\n```\n' >> "$REPORT"
}

cat > "$REPORT" <<'MD'
# Warehouse Activities Contract Discovery

This report discovers how ConcordCRM wires related activities/timeline records for first-party resources, so Warehouse can follow the same contract without guessing.
MD

section "Current Warehouse frontend"
code_file "modules/Warehouse/resources/js/views/WarehousesView.vue" "modules/Warehouse/resources/js/views/WarehousesView.vue"
code_file "modules/Warehouse/resources/js/app.js" "modules/Warehouse/resources/js/app.js"
code_file "modules/Warehouse/resources/js/routes.js" "modules/Warehouse/resources/js/routes.js"

section "Current Warehouse backend"
code_file "modules/Warehouse/app/Models/Warehouse.php" "modules/Warehouse/app/Models/Warehouse.php"
code_file "modules/Warehouse/app/Resources/Warehouse.php" "modules/Warehouse/app/Resources/Warehouse.php"
code_file "modules/Warehouse/app/Providers/WarehouseServiceProvider.php" "modules/Warehouse/app/Providers/WarehouseServiceProvider.php"

section "Activities module registration and components"
code_file "modules/Activities/resources/js/app.js" "modules/Activities/resources/js/app.js"
code_file "modules/Activities/resources/js/components/RecordTabActivity.vue" "modules/Activities/resources/js/components/RecordTabActivity.vue"
code_file "modules/Activities/resources/js/components/RecordTabActivityPanel.vue" "modules/Activities/resources/js/components/RecordTabActivityPanel.vue"
code_file "modules/Activities/resources/js/components/RecordTabTimelineActivity.vue" "modules/Activities/resources/js/components/RecordTabTimelineActivity.vue"
code_file "modules/Activities/resources/js/components/CreateActivityModal.vue" "modules/Activities/resources/js/components/CreateActivityModal.vue"
code_file "modules/Activities/app/Actions/CreateRelatedActivityAction.php" "modules/Activities/app/Actions/CreateRelatedActivityAction.php"

section "First-party resource activity patterns"
grep_block "Resource actions using CreateRelatedActivityAction" grep -R "CreateRelatedActivityAction\|floatResourceInEditMode" -n modules/Contacts/app/Resources modules/Deals/app/Resources modules/Activities/app/Resources modules/*/app/Resources 2>/dev/null
grep_block "Frontend tab usage of RecordTabActivity" grep -R "RecordTabActivity\|RecordTabActivityPanel\|TimelineTab\|RecordTabTimeline" -n modules/Contacts/resources/js/views modules/Deals/resources/js/views modules/*/resources/js/views 2>/dev/null
grep_block "Activity relation methods in first-party models" grep -R "function activities\|activities()\|activityable\|activityables" -n modules/Contacts/app modules/Deals/app modules/Activities/app modules/*/app/Models 2>/dev/null
grep_block "Activity migrations / pivot tables" find modules -path '*database*' -type f -name '*.php' -print0 | xargs -0 grep -n "activityable\|activityables\|activities" 2>/dev/null

section "Resource panels involving activities/timeline"
grep_block "panels() definitions mentioning timeline or activities" grep -R "Panel::make.*activit\|Panel::make.*timeline\|resource-.*activit\|timeline" -n modules/Contacts/app/Resources modules/Deals/app/Resources modules/*/app/Resources 2>/dev/null

section "Potential API validation hooks"
grep_block "Request filters/rules for activities via_resource" grep -R "via_resource\|viaResource\|activity.*rules\|create_resource_request.activities" -n modules/Activities modules/Core modules/*/app/Providers 2>/dev/null

section "Summary hints"
cat >> "$REPORT" <<'MD'

Look for these decisions:

1. Which frontend components should Warehouse use?
   - RecordTabActivity and RecordTabActivityPanel, or TimelineTab/TimelineTabPanel.
2. Which props does the panel require?
   - resourceName, resourceId, resource, viaResource, viaResourceId, etc.
3. Does Warehouse need a model relation such as activities()?
4. Does Warehouse Resource need CreateRelatedActivityAction::make()->onlyInline()?
5. Does Warehouse need validation rules for via_resource=warehouses?
6. Which failed assumptions should be avoided in the Builder docs?
MD

printf 'Report written to: %s\n\nPreview:\n' "$REPORT"
sed -n '1,220p' "$REPORT"
