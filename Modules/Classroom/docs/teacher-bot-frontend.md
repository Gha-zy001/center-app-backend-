# Teacher's own Telegram bot: frontend guide

Each teacher can create **their own bot** for parents (attendance, grades, schedule messages). Until they do,
parents use the shared platform bot and nothing changes. The teacher creates the bot **inside Telegram**
through our manager bot; the app only starts the flow and shows the result.

Base: `/api/v1/teacher` · teacher token required · errors look like `{ success:false, message, data:null, code }`.

## Endpoints

### GET /bot: current state
```json
{ "success": true, "message": "OK", "data": {
  "bot": { "username": "ahmed_parents_bot", "link": "https://t.me/ahmed_parents_bot", "status": "active" },
  "managerConfigured": true } }
```
- `parentsLink` is the link to give parents, **always present** (null only if no bot is configured at all): the teacher's
  own bot link once created, the shared platform bot link before that. Keep it visible on the screen at all times with
  Copy / Share / QR; it changes automatically when the teacher creates their own bot.
- `pending` is `{ startLink }` while the teacher has a generated link, otherwise `null`.
  Use it to keep showing the link (with copy / open buttons) after a page reload or on another device:
  no need to call `connect` again. It never expires; it disappears only when the bot is created.
- `bot` is `null` when the teacher has no own bot yet.
- `managerConfigured: false` means the server isn't set up for this: hide the "Create bot" button.
- The bot token is never returned.

### POST /bot/connect: start creating a bot
No body. Response:
```json
{ "data": { "startLink": "https://t.me/CenterManagerBot?start=Ab3..." } }
```
- Open `startLink` in a new tab / `window.open` (on mobile it opens the Telegram app).
- The link does not expire. Calling `connect` again returns the **same** link until the bot is created.
- Errors: `503 manager_bot_not_configured`.

### DELETE /bot: remove the bot
Removes the teacher's bot **and the parents linked through it** (they must link again). Always `200`, also if there was no bot.
Ask for confirmation first.

## What the teacher sees in Telegram (not our UI)
1. Opens `startLink` → the manager bot answers "Press the button to create your parents bot".
2. Presses **🤖 إنشاء البوت** → Telegram shows a window with a suggested name and username (editable) → confirms.
3. The manager bot sends "تم تفعيل بوتك ✅" with the new bot link.

The server learns about it from Telegram, so **the app is not notified**: it must poll.

## Screen: "Parents bot"
States:
| State | Condition | UI |
|---|---|---|
| Not available | `managerConfigured === false` | Explain it isn't enabled yet; no button |
| No own bot | `bot === null` | Short explanation + **Create my bot** button. Parents currently use the shared bot |
| Waiting | after `connect` | "Finish in Telegram" + **Open Telegram again** (re-open `startLink`) |
| Active | `bot !== null` | Bot `@username`, **Copy link**, **Share link** (`bot.link`), QR code of `bot.link` (nice for printing), **Remove bot** (danger) |

Flow:
1. Tap **Create my bot** → `POST /bot/connect` → open `startLink`, switch to *Waiting*.
2. While waiting, `GET /bot` every **3 s** (stop on screen leave; also refetch on window focus / `visibilitychange`).
3. When `bot !== null` → *Active*, show a success toast, stop polling.

Tell the teacher, once the bot is active: "Send this link to parents. They press Start and share their phone number,
then they get absence, grades and schedule messages." Parents who already use the shared bot keep getting messages there
until they link with the new bot (a parent linked in both receives each message twice, so suggest moving over).

## Types (Zod)
```ts
const BotState = z.object({
  bot: z.object({ username: z.string(), link: z.string().url(), status: z.literal("active") }).nullable(),
  managerConfigured: z.boolean(),
});
const Connect = z.object({ startLink: z.string().url() });
```

## i18n keys to add (AR + EN)
Create my bot · Open Telegram again · Finish creating the bot in Telegram ·
Bot is active · Copy link · Share link · Remove bot · Remove bot confirmation (mentions parents must link again) ·
Not enabled yet · Parents currently use the shared bot.

## Admin panel
`GET /api/v1/admin/teachers/{id}/bot` now returns the teacher's own bot username when they have one, otherwise the shared bot.
`PUT` on it is still refused (`409 bot_shared`); the admin cannot create bots for teachers.

## Edge cases
- Teacher closes Telegram without confirming → stays *Waiting* (the link keeps working, also after a reload: it is in `pending`); **Open Telegram again** reuses it.
- Teacher creates a new bot while one exists → the old bot is replaced and its linked parents are dropped. Warn before `connect` when `bot !== null`.
- Network error on polling → keep polling quietly, show an error only after several failures.
- `401` → login screen. `429` → slow down (shared 120 req/min limit).
