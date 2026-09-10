# 🇺🇿 YoshlarHub — Telegram Bot

O'zbekiston yoshlari uchun grant, tanlov, stajirovka, kurs va boshqa imkoniyatlarni bir joyda to'playdigan Telegram bot.

---

## 📁 Loyiha strukturasi

```
yoshlarhub/
├── .env
├── .env.example
├── bot/
│   └── webhook.php
├── config/
│   └── config.php
├── src/
│   ├── Database.php
│   ├── Telegram.php
│   └── Opportunity.php
├── database/
│   └── schema.sql
├── admin/
│   ├── login.php
│   ├── logout.php
│   ├── index.php
│   ├── add.php
│   ├── edit.php
│   ├── delete.php
│   └── opportunities.php
└── cron/
    └── reminders.php
```

---

## ⚙️ Talablar

- PHP **8.1+**
- MySQL **5.7+** / MariaDB
- HTTPS (SSL sertifikat majburiy)
- PHP extensionlar: `curl`, `pdo`, `pdo_mysql`, `mbstring`, `json`

---

## 🚀 1. `.env` sozlash

`.env.example`ni nusxa olib `.env` yarating:

```env
BOT_TOKEN=BOTFATHER_DAN_OLGAN_TOKEN
ADMIN_ID=TELEGRAM_IDING

DB_HOST=localhost
DB_NAME=yoshlarhub
DB_USER=MYSQL_USER
DB_PASS=MYSQL_PASSWORD
```

> ⚠️ **Tokenni hech kimga yubormang va `.env`ni GitHub'ga yuklamas!**

---

## 🐬 2. MySQL ulash

Hostingda database yarating:

```text
Database : yoshlarhub
User     : yoshlarhub_user
Password : ********
Host     : localhost
```

Keyin `database/schema.sql`ni **phpMyAdmin** orqali import qiling:

```
phpMyAdmin → yoshlarhub → Import → schema.sql → Go
```

Quyidagi jadvallar yaratiladi:

```
users · categories · interests · user_interests · opportunities · bookmarks
```

Keyin `ALTER TABLE` migratsiyasini ham ishlatish kerak:

```sql
ALTER TABLE users
ADD COLUMN notifications_enabled BOOLEAN NOT NULL DEFAULT TRUE;
```

---

## 🔗 3. Webhook ulash

Sayting HTTPS bo'lishi shart. Masalan:

```
https://yoshlarhub.uz
```

Webhook o'rnatish — brauzerga yozing:

```
https://api.telegram.org/botBOT_TOKEN/setWebhook?url=https://yoshlarhub.uz/bot/webhook.php
```

Tekshirish:

```
https://api.telegram.org/botBOT_TOKEN/getWebhookInfo
```

Kutilgan javob:

```json
{
  "ok": true,
  "result": {
    "url": "https://yoshlarhub.uz/bot/webhook.php"
  }
}
```

✅ Webhook ulandi!

---

## 🤖 4. Botni ishga tushirish

Hostingga butun loyihani yuklang va Telegram'dan `/start` yuboring.

```
👋 YoshlarHub ga xush kelibsiz!
```

chiqsa — **bot ishlayapti ✅**

---

## 🔐 5. Admin panelga kirish

Brauzerdan oching:

```
https://yoshlarhub.uz/admin/login.php
```

Telegram ID'ingizni kiriting (`.env`dagi `ADMIN_ID` bilan bir xil):

```
123456789
```

### Dashboard ko'rinishi

```
🚀 YoshlarHub Admin

👥 Foydalanuvchilar  📋 Imkoniyatlar  ✅ Faol  ⭐ Saqlashlar

[📋 Imkoniyatlar]  [➕ Yangi imkoniyat]  [🚪 Chiqish]
```

### Birinchi imkoniyat qo'shish

```
➕ Yangi imkoniyat → to'ldirish → 🚀 E'lon qilish
```

Misol:

```
Nomi       : Python bo'yicha bepul kurs
Kategoriya : 📚 Kurslar
Hudud      : O'zbekiston
Tashkilotchi: YoshlarHub
Havola     : https://example.com
Deadline   : 2026-10-01 23:59
```

Keyin Telegram'da `📚 Kurslar` bosing — imkoniyat chiqadi.

---

## ✅ 6. Yakuniy test checklist

### Telegram bot

| Buyruq | Holat |
|---|---|
| `/start` | ✅ |
| `🎓 Grantlar` | ✅ |
| `🏆 Tanlovlar` | ✅ |
| `💼 Stajirovkalar` | ✅ |
| `🤝 Volontyorlik` | ✅ |
| `📚 Kurslar` | ✅ |
| `🚀 Startaplar` | ✅ |
| `🧠 Olimpiadalar` | ✅ |
| `🔎 Qidirish` + matn | ✅ |
| `⭐ Saqlash` callback | ✅ |
| `⭐ Saqlanganlar` | ✅ |
| `🗑 Olib tashlash` callback | ✅ |
| `🔗 Batafsil` URL | ✅ |
| `👤 Profil` | ✅ |

---

## 🔔 7. Deadline eslatmasi (Cron)

Hostingdagi **Cron Jobs** bo'limiga qo'shing:

```
Har 1 soatda
```

```bash
0 * * * * /usr/bin/php /home/USERNAME/yoshlarhub/cron/reminders.php
```

Haqiqiy yo'lni moslashtiring:

```bash
/usr/bin/php /home/user/public_html/yoshlarhub/cron/reminders.php
```

Foydalanuvchi ⭐ saqlagan imkoniyat deadline'i **24 soat ichida** bo'lsa, bot avtomatik eslatma yuboradi.

---

## 🔒 Xavfsizlik eslatmalari

- `.env` → `.gitignore`ga qo'shing
- Tokenni hech kimga yubormaslik
- `delete.php` kelajakda POST + CSRF token bilan himoyalang
- Admin login uchun IP restriction qo'shish tavsiya etiladi

---

## 📌 Keyingi bosqichlar

- [ ] Foydalanuvchilar ro'yxati (admin panel)
- [ ] Kategoriya boshqaruvi (admin panel)
- [ ] Imkoniyatni faollashtirish / o'chirish toggle
- [ ] Foydalanuvchi hududi bo'yicha filter
- [ ] Broadcast xabar yuborish (admin → barcha users)
