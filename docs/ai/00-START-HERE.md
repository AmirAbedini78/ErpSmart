# 00-START-HERE — ErpSmart AI Development Entrypoint

در شروع هر سشن توسعه:

1. `docs/ai/00-GOVERNANCE.md` را بخوان.
2. `docs/ai/01-PROJECT-MAP.md` را بخوان.
3. اگر کاربر نام بخش گفت، آن را در `docs/ai/02-domains/INDEX.yml` resolve کن.
4. domain doc مربوط را بخوان.
5. قبل از تغییر کد، validation queries را اجرا یا معادلش را بررسی کن.
6. بعد از تغییر، History همان domain را آپدیت کن.

## Profiles
- Cursor/Codex/Cloud: می‌تواند چند فایل و چند hop بخواند.
- Continue + Ollama/Qwen 1.5B: فقط `touches_code` و خلاصه‌های domain را بخواند.

## Standard response before coding
- Current state
- Touch points
- Risk
- Minimal patch plan
- Smoke checks
