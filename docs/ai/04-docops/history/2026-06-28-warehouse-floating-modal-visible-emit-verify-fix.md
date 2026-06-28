# Warehouse Floating Modal Visible Emit Verify Fix

Fixes the verification script for the Core floating modal visible contract and normalizes the Warehouse floating modal template to call the `emit` function from `<script setup>`.

The previous verifier used a PHP double-quoted string containing `$emit` and `$event`, causing PHP interpolation warnings and a false negative for `uses_core_visible_model`.
