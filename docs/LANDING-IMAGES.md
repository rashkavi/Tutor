# Landing page pictures: one prompt per picture (Nano Banana / Gemini)

The landing page (`index.php`) has **12 photo places** and the sign-up page has **1**. Each place has its **own file**
in `assets/landing/img/` (today they hold the older photos as placeholders). To change a picture, generate it and upload
it with the **same file name**, replacing the old file: the page picks it up at once, no code change needed.

**Rules for every picture**
- **No school uniforms.** Students wear normal clothes: T-shirts, shirts, frocks, kurtas, hoodies. No white uniforms, no ties, no school badges.
- No text, logos, app names, brand names or watermarks. Screens show blurred or simple shapes, never readable words.
- The same light and colour in all 12, so the page looks like one set.
- Save as **WebP**, quality about 80, under 250 KB. Upload to `assets/landing/img/` with the **exact file name** below. Then hard refresh (Ctrl + F5).

**Style line: add it to the end of every prompt**
> Photorealistic photo, Sri Lanka, natural warm daylight, soft shadows, 35 mm lens at eye level, shallow depth of field, real Sri Lankan people with natural expressions, students in casual everyday clothes (no school uniforms), clean and uncluttered, no text, no logos, no watermarks, no brand names.

---

## 1. "Made for real classes" moving strip (6 cards)

Section "Hall classes, Zoom classes, and everything in between." The cards slide to the left by themselves.
**Size:** portrait **3:4**, **900 × 1200 px**. The bottom third gets a dark fade with a white caption, so keep the important part in the top two thirds.

| # | File | Card | Prompt |
|---|---|---|---|
| 1 | `strip-hall.webp` | Hall classes · QR attendance at the door | A large tuition class hall in Colombo in the early evening: teenage students in casual clothes (T-shirts, shirts, frocks) walk in one by one through the door; a young assistant at a small table scans the QR code on a student's ID card with a phone; rows of desks, a whiteboard and ceiling fans behind them, busy but friendly mood. 3:4 portrait. |
| 2 | `strip-paper.webp` | Paper classes · Marks and papers in one place | A paper class in a Sri Lankan tuition hall: teenage students in casual clothes sit at long desks writing a model exam paper in silence; in the front a stack of marked answer sheets with red ticks lies on the teacher's desk; a plain wall clock and an empty whiteboard, calm focused mood. 3:4 portrait. |
| 3 | `strip-group.webp` | Group classes · Every batch, every year | A small group class in a Sri Lankan home tuition room: a friendly female teacher in a saree explains a sum to five teenage students in casual clothes around a round table with exercise books and calculators; potted plants by an open window, warm afternoon light. 3:4 portrait. |
| 4 | `strip-zoom.webp` | Online on Zoom · Recordings for paid students | A Sri Lankan teenage girl in a casual hoodie at home in the evening joins an online class on a laptop: the screen shows a teacher on a video call (no app logo), notebook and pen beside the laptop, a desk lamp and a cup of tea, a younger sibling reading in the soft background. 3:4 portrait. |
| 5 | `strip-revision.webp` | Revision · Tutes posted or online | Revision season: a Sri Lankan teenage boy in a casual T-shirt at a study desk opens a courier envelope of printed notes (plain sheets, no readable text); a tablet beside it shows the same notes as blurred shapes; sticky notes and a highlighter, bright morning light from a window. 3:4 portrait. |
| 6 | `strip-team.webp` | Your team · Staff logins for assistants | The office of a Sri Lankan tuition institute: two young assistants in smart casual clothes at a desk, one checks student payments on a laptop, the other sorts printed notes into trays with blank labels; a timetable board on the wall with no readable text, friendly busy mood. 3:4 portrait. |

## 2. "Students and parents get their own login" (3 cards)

Each card has a phone drawing in the **middle and bottom** of the picture, so keep the person to the **left or right side** and the top part clear.
**Size:** landscape **4:3**, **1200 × 900 px**.

| # | File | Card | Prompt |
|---|---|---|---|
| 7 | `portal-student.webp` | Student Portal | A Sri Lankan teenage girl in a casual shirt sits on a sofa at home and smiles at her phone while watching a class recording; earphones in, a notebook on her lap; she sits on the left side of the picture, the right side is a soft blurred living room with plants, warm evening light. 4:3 landscape. |
| 8 | `portal-tutor.webp` | Tutor Dashboard | A confident Sri Lankan male tutor in his thirties in a neat shirt stands in front of a whiteboard with simple maths shapes (no readable text) and checks a tablet in his hand; he stands on the right side of the picture, the left side shows an empty classroom with desks softly blurred, daylight. 4:3 landscape. |
| 9 | `portal-parent.webp` | Parent Portal | A Sri Lankan mother in her forties in a simple dress sits at a dining table in the morning and looks at her phone with a relieved smile, a cup of tea beside her; she sits on the left side of the picture, the right side is a bright kitchen softly blurred. 4:3 landscape. |

## 3. "Teaching on ENLIXO in three steps"

The big photo next to the three steps. Two small cards ("Account ready", "248 students added") float over the **top left and bottom right**, so keep the person in the middle.
**Size:** landscape **5:4**, **1250 × 1000 px**.

| # | File | Prompt |
|---|---|---|
| 10 | `how-setup.webp` | A Sri Lankan female tutor in her thirties in a smart kurta sits at a desk at home with a laptop and smiles while a support person helps her on a phone call (speaker on, phone on the desk); an open register book and a cup of tea beside the laptop, the laptop screen shows a simple table as blurred shapes, bright calm room, morning light. 5:4 landscape. |

## 4. FAQ card "Rather talk to a person?"

**Size:** landscape **16:10**, **1200 × 750 px**.

| # | File | Prompt |
|---|---|---|
| 11 | `talk.webp` | A friendly Sri Lankan customer support person in their late twenties sits at a bright modern office desk with a headset, smiling while talking on a call and looking at a laptop; a notebook and a cup of tea beside them, soft daylight from a window, plants in the background, warm and welcoming. 16:10 landscape. |

## 5. Footer "Bring your class online this week."

The picture sits on the **right** of the footer box and fades out to the left, so keep the people on the **right half**.
**Size:** landscape **16:10**, **1200 × 750 px**.

| # | File | Prompt |
|---|---|---|
| 12 | `cta-start.webp` | A happy Sri Lankan tutor in her late twenties in casual smart clothes waves goodbye to her students at the end of an evening class, standing at the door of a small tuition room; two teenage students in casual clothes with backpacks walk out smiling; the people are on the right half of the picture, the left half is a soft blurred wall, warm golden evening light. 16:10 landscape. |

## 6. Sign-up page (`tutor-registration-application.php`), left side

The photo fills the tall dark-blue left panel behind the white heading and the list of perks, so keep it **calm and a bit dark**, with the person in the **lower half**.
**Size:** portrait **3:4**, **900 × 1200 px**.

| # | File | Prompt |
|---|---|---|
| 13 | `signup-side.webp` | A Sri Lankan female tutor in her early thirties in a smart casual blouse sits at a wooden desk in the evening and plans her week on a laptop, a small stack of exercise books and a cup of tea beside her, soft lamp light, deep blue shadows in the room, calm and confident mood; she sits in the lower half of the picture, the top half is a dark plain wall. 3:4 portrait. |

---

**Tips**
- Make 3 or 4 versions of each picture and pick the ones with the same colour and light.
- If faces or hands look wrong, ask Nano Banana: "fix the hands and faces, keep everything else the same".
- If a uniform still appears, add: "students wear casual clothes, not school uniforms".
- Cut the picture to the size above before saving (Nano Banana: "make it 3:4 portrait", "make it 16:10 landscape").
- Keep the file names exactly as above (small letters, `-`, `.webp`), or the picture will not show.
