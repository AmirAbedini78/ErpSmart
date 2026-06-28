#!/usr/bin/env bash
set -euo pipefail

REPORT="storage/app/warehouse-edit-contract-discovery-report.md"
mkdir -p "$(dirname "$REPORT")"

{
  echo "# Warehouse Edit Contract Discovery"
  echo
  echo "Generated at: $(date -Iseconds)"
  echo
  echo "## Goal"
  echo
  echo "Discover how first-party modules implement record detail edit actions/routes so Warehouse can follow the same contract."
  echo

  echo "## Current Warehouse routes"
  echo '```text'
  sed -n '1,220p' modules/Warehouse/resources/js/routes.js 2>/dev/null || true
  echo '```'
  echo

  echo "## Current Warehouse detail view edit-related snippets"
  echo '```text'
  grep -nC 10 "goBackToIndex\|openInlineEditForm\|Teleport\|WarehousesEdit\|backButtonText\|editButtonText\|router.push\|@click\|IButton" modules/Warehouse/resources/js/views/WarehousesView.vue 2>/dev/null || true
  echo '```'
  echo

  echo "## Current Warehouse edit view snippets"
  echo '```text'
  grep -nC 8 "defineProps\|recordId\|redirectOnClose\|emit\|closed\|router.push\|route.params\|ISlideover\|FormFields\|updateResource\|retrieveResource" modules/Warehouse/resources/js/views/WarehousesEdit.vue 2>/dev/null || true
  echo '```'
  echo

  echo "## First-party module routes and edit/detail patterns"
  for module in Contacts Companies Deals Activities; do
    echo
    echo "### $module"
    echo
    echo "#### routes.js"
    echo '```text'
    sed -n '1,220p' "modules/$module/resources/js/routes.js" 2>/dev/null || echo "routes.js not found"
    echo '```'
    echo

    echo "#### Edit/View related files"
    echo '```text'
    find "modules/$module/resources/js" -type f \( -name "*.vue" -o -name "*.js" \) 2>/dev/null \
      | grep -Ei "(Edit|View|Record|routes|Index)" \
      | sort \
      | head -80 || true
    echo '```'
    echo

    echo "#### edit/detail snippets"
    echo '```text'
    find "modules/$module/resources/js" -type f \( -name "*.vue" -o -name "*.js" \) 2>/dev/null \
      | xargs grep -nE "RecordLayout|ResourceRecord|RecordView|ResourceView|RouterView|ISlideover|RecordTab|RecordActions|edit|Edit|router\.push|route\.params|resourceId|@click" 2>/dev/null \
      | head -220 || true
    echo '```'
  done

  echo
  echo "## Core reusable record/edit components"
  echo
  echo '```text'
  find modules/Core/resources/js -type f \( -name "*.vue" -o -name "*.js" \) 2>/dev/null \
    | grep -Ei "(Record|Resource).*\.vue|Resource.*\.js|routes" \
    | sort \
    | head -160 || true
  echo '```'
  echo

  echo "## Core edit/navigation snippets"
  echo '```text'
  find modules/Core/resources/js -type f \( -name "*.vue" -o -name "*.js" \) 2>/dev/null \
    | xargs grep -nE "RecordLayout|RecordActions|ResourceRecord|ResourceView|RouterView|ISlideover|edit|Edit|router\.push|route\.params|resourceId|action" 2>/dev/null \
    | head -260 || true
  echo '```'
  echo

  echo "## PHP Resource panels/actions hints"
  echo '```text'
  grep -RInE "Panel::make|Action::make|edit|Edit|WithResourceRoutes|displayQuery|updateQuery" modules/Contacts modules/Companies modules/Deals modules/Core 2>/dev/null | head -260 || true
  echo '```'

} > "$REPORT"

echo "Report written to: $REPORT"
echo
echo "Preview:"
sed -n '1,120p' "$REPORT"
