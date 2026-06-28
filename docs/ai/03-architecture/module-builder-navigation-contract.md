# Module Builder Navigation Contract

For resource modules, navigation must be generated in a predictable route order:

1. `/resource/create`
2. `/resource/{id}/edit`
3. `/resource/{id}`
4. `/resource`

The edit route must be matched before the view route. Detail page edit actions should navigate to the canonical edit path unless the module declares and tests an inline edit capability.

Required feature flag examples:

```json
{
  "detail_page": true,
  "detail_edit_action": true,
  "edit_route": true,
  "inline_detail_edit": false
}
```
