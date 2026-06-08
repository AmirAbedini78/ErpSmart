# 00 — ErpSmart SmartDocs Governance

هدف: این پوشه حافظهٔ توسعهٔ پروژه است؛ هم برای انسان، هم برای Cursor/Continue/Ollama.

## سندها
- `01-PROJECT-MAP.md`: نقشه کلی و lifecycle.
- `02-domains/*.md`: سند هر ماژول/دامنه.
- `03-adr/*.md`: تصمیم‌های معماری.
- `04-docops/*`: تاریخچه، کشف، backlog، metrics.

## قانون هر تغییر
هر تسک توسعه باید این چرخه را طی کند:
1. Scope را تشخیص بده.
2. Domain doc مربوط را از `INDEX.yml` پیدا کن.
3. فایل‌های `touches_code` و `depends_on` را بخوان.
4. قبل از تغییر، وضعیت فعلی را از روی کد validate کن.
5. تغییر را کوچک و قابل برگشت انجام بده.
6. smoke check اجرا کن.
7. History همان domain را آپدیت کن.
8. اگر تصمیم معماری بود، ADR ثبت کن.

## قانون برای LLM کوچک
- فقط فایل‌های هدف را بخوان؛ کل پروژه را به مدل کوچک نده.
- خروجی باید Patch کوچک باشد، نه بازنویسی بزرگ.
- اگر doc با کد mismatch داشت، اول Mismatch Report بده.
- برای Laravel/Vue: مسیرهای واقعی فایل را همیشه ذکر کن.

## Anti-patterns
- تغییر vendor یا فایل‌های generated به‌جای extension point.
- فعال‌کردن ماژول بدون بررسی provider/migration/seed.
- اجرای migration عمومی وقتی مسئله مربوط به یک ماژول است.
- روشن گذاشتن Telescope/Debugbar/Pulse هنگام benchmark سرعت.
