#!/usr/bin/env bash
set -euo pipefail

ROOT="$(pwd)"
REPORT="storage/app/warehouse-activity-comments-associations-discovery-report.md"
mkdir -p "$(dirname "$REPORT")"

write_header() {
  printf '\n## %s\n\n' "$1" >> "$REPORT"
}

append_file() {
  local title="$1"
  local file="$2"
  local from="${3:-1}"
  local to="${4:-220}"
  write_header "$title"
  if [[ -f "$file" ]]; then
    printf '### `%s` lines %s-%s\n\n```text\n' "$file" "$from" "$to" >> "$REPORT"
    sed -n "${from},${to}p" "$file" >> "$REPORT" || true
    printf '\n```\n' >> "$REPORT"
  else
    printf 'Missing file: `%s`\n' "$file" >> "$REPORT"
  fi
}

append_cmd() {
  local title="$1"
  shift
  write_header "$title"
  printf '```text\n' >> "$REPORT"
  "$@" >> "$REPORT" 2>&1 || true
  printf '\n```\n' >> "$REPORT"
}

cat > "$REPORT" <<'MD'
# Warehouse Activity Comments & Associations Contract Discovery

This report discovers why Warehouse-related activities can be created but comments and association edit/load do not work correctly.

Focus areas:
- Activity comments flow used by `RelatedActivity`.
- Association editor/load flow used by `RelatedActivity`.
- Whether Activity and Core association/comment contracts know about `warehouses`.
- Whether any backend code still assumes activityable resources are only contacts/companies/deals.

MD

append_file "Current Warehouse detail view" "modules/Warehouse/resources/js/views/WarehousesView.vue" 1 260
append_file "Current Warehouse model" "modules/Warehouse/app/Models/Warehouse.php" 1 180
append_file "Current Warehouse resource" "modules/Warehouse/app/Resources/Warehouse.php" 1 260
append_file "Current Warehouse provider" "modules/Warehouse/app/Providers/WarehouseServiceProvider.php" 1 240

append_file "Activity model relations and purge area" "modules/Activities/app/Models/Activity.php" 220 720
append_file "Activity resource fields/actions area" "modules/Activities/app/Resources/Activity.php" 1 560
append_file "HasActivities concern" "modules/Activities/app/Concerns/HasActivities.php" 1 140
append_file "CreateRelatedActivityAction" "modules/Activities/app/Actions/CreateRelatedActivityAction.php" 1 180

append_file "RelatedActivity component" "modules/Activities/resources/js/components/RelatedActivity.vue" 1 320
append_file "RelatedActivity create component" "modules/Activities/resources/js/components/RelatedActivityCreate.vue" 1 320
append_file "Activities tab panel" "modules/Activities/resources/js/components/RecordTabActivityPanel.vue" 1 360

append_cmd "Activities JS grep: comments / associations / related resource" \
  grep -RIn "comment\|comments\|association\|associations\|via-resource\|viaResource\|related-resource\|relatedResource" modules/Activities/resources/js modules/Core/resources/js modules/Comments/resources/js

append_cmd "Backend grep: activity associations and comments contracts" \
  grep -RIn "activityable\|activities\|comments\|commentable\|associations\|associateable\|via_resource\|viaResource\|timelineRelation\|timeline_relation" modules/Activities/app modules/Core/app modules/Comments/app modules/Warehouse/app

append_cmd "Routes grep: comments / associations / activities / timeline" \
  grep -RIn "comments\|associations\|activities\|timeline" routes modules/*/routes modules/*/routes.php 2>/dev/null

append_cmd "First-party activity relation references to contacts/companies/deals" \
  grep -RIn "\['contacts', 'companies', 'deals'\]\|Contact::class, Company::class, Deal::class\|contacts.*companies.*deals\|companies.*contacts.*deals" modules/Activities modules/Core modules/Contacts modules/Deals

append_cmd "Warehouse activities integration grep" \
  grep -RIn "HasActivities\|activities()\|incomplete_activities\|displayQuery\|CreateRelatedActivityAction\|function warehouses\|via_resource.*warehouses\|activityables\|activityable" modules/Warehouse modules/Activities/app/Models/Activity.php modules/Activities/app/Resources/Activity.php modules/Activities/app/Actions/CreateRelatedActivityAction.php

append_cmd "Last Laravel log lines filtered for relevant errors" \
  sh -lc "if [ -f storage/logs/laravel.log ]; then grep -iE 'warehouse|activity|activities|comment|comments|associate|association|associations|activityable|commentable|500|error|exception' storage/logs/laravel.log | tail -n 220; else echo 'No laravel.log found'; fi"

cat >> "$REPORT" <<'MD'

## Suggested reproduction before sending report

1. Open a Warehouse detail page.
2. Open Activities tab.
3. Add a comment to a related activity.
4. Try editing/adding associations for the same activity.
5. Immediately rerun:

```bash
tail -n 160 storage/logs/laravel.log
```

Then send this report plus the fresh last log lines.
MD

printf 'Warehouse activity comments/associations discovery report written to %s\n' "$REPORT"
