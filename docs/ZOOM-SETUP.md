# Connect Zoom (tutor's own Zoom account)

When a tutor presses **Connect Zoom** (Tutor › Settings › Integrations), ENLIXO sends them to Zoom. Zoom shows
**"ENLIXO Tutor would like permission to …"** with **Allow** / **Decline**. After **Allow**, Zoom sends the tutor back
to `/zoom-callback.php`, we save the tokens and the card shows **Connected**. From then on every online class day
gets its Zoom meeting by itself.

Code: `tutor/zoom-connect.php` → Zoom → `zoom-callback.php`; keys and the return address in
`includes/enlixo/zoom-oauth.php`; meetings in `src/Scheduling/Zoom/`.

## 1. Make the Zoom app (once, by ENLIXO)

1. Sign in at <https://marketplace.zoom.us> with the ENLIXO Zoom account → **Develop › Build App**.
2. Choose **General App** → **User-managed** (each tutor connects their own Zoom). Name it **ENLIXO Tutor** and upload the logo (the logo and name are what the tutor sees on the Allow page).
3. **Basic Information › OAuth Information**
   - **OAuth Redirect URL**: `https://enlixotutor.com/zoom-callback.php`
   - **OAuth Allow List**: add every address tutors use, for example
     `https://enlixotutor.com/zoom-callback.php` **and** `https://www.enlixotutor.com/zoom-callback.php`.
     The return address must be exactly the address the tutor is signed in on (same `www` or not, same `https`), or Zoom refuses it and the session is lost.
4. **Scopes** (Add Scopes): `meeting:write:meeting` (create meetings), `meeting:read:meeting`, `user:read:user`.
   Add `cloud_recording:read:list_user_recordings` only when cloud recordings are read.
5. Copy the **Client ID** and **Client Secret** (Production keys once the app is published; Development keys while testing).

## 2. Put the keys on the server

In the server environment or `config/config.local.php` (never in the code, never in git):

```
ZOOM_OAUTH_CLIENT_ID=...
ZOOM_OAUTH_CLIENT_SECRET=...
# optional: force one return address (otherwise the address the tutor uses is sent)
ZOOM_OAUTH_REDIRECT=https://enlixotutor.com/zoom-callback.php
```

The site must be on **https** and the server must reach `https://zoom.us` (outgoing).

## 3. Who can press Allow

- **Development** (app not published): only users of the ENLIXO Zoom account can authorise it. Use it to test.
- **Other tutors' own Zoom accounts**: the app must be **published** (Zoom reviews it; it can be published without being listed publicly). Until then other tutors see a Zoom error instead of the Allow page.

## 4. What the tutor does

1. Tutor › Settings › Integrations › **Connect Zoom** (STARTER and up).
2. Sign in to Zoom, tick nothing else, press **Allow**.
3. Back on Integrations the card says **Connected**. Create a class with online times and "Create Zoom meetings automatically" on, or schedule an online class day.

## 5. When it does not connect

The Integrations page says why, and for setup problems it shows **Zoom said: …** and the **return address the Zoom app must allow**.

| Message | Cause | Fix |
|---|---|---|
| Zoom app keys missing | `ZOOM_OAUTH_CLIENT_ID` / `SECRET` not set | Step 2 |
| Zoom page shows "Invalid redirect" / 4700 | the shown return address is not in the Zoom app | Add it to Redirect URL + Allow List (step 1.3) |
| Zoom said: invalid_client | wrong or Development keys with a published app | Copy the right Client ID / Secret |
| Took too long / another browser | signed in on another address (www vs no www) or the tab was left long | Use one address; press Connect again |
| Permission not given | Decline pressed | Press Connect Zoom and Allow |
| Other tutors see a Zoom error | app not published | Step 3 |

Server log: `Zoom token exchange failed: HTTP … redirect=…` shows Zoom's answer and the return address used.
