# Landing page photo strip: image prompts (Nano Banana / Gemini)

Section "Hall classes, Zoom classes, and everything in between." on `index.php`. Six cards slide to the left by
themselves. Each card looks for its own picture; until it is uploaded the older photo is shown.

**Size for every picture:** portrait **3:4**, export **900 × 1200 px**, save as **WebP** (quality ~80, under 250 KB),
upload to `assets/landing/img/` with the file name below. The bottom third gets a dark fade with the white caption, so
keep the important part in the top two thirds and leave the bottom calm.

**Style line to add to every prompt (keeps the six pictures as one set):**
> Photorealistic photo, Sri Lanka, natural warm daylight, soft shadows, shot on a 35 mm lens at eye level, shallow depth of field, real people with natural expressions, clean and uncluttered, no text, no logos, no watermarks, no brand names, 3:4 portrait.

| File | Card | Prompt |
|---|---|---|
| `strip-hall.webp` | Hall classes · QR attendance at the door | A tuition class hall in Colombo at the start of a class: Sri Lankan A/L students in white school uniforms walk in through the door one by one; a young assistant at a small table holds up a phone that scans the QR code on a student's ID card; rows of desks and a whiteboard behind them, ceiling fans, early evening light. |
| `strip-paper.webp` | Paper classes · Marks and papers in one place | A paper class in a Sri Lankan tuition hall: students in white uniforms sit at long desks writing a model exam paper in silence, a stack of marked answer papers with red ticks on the teacher's desk in front, a wall clock and a whiteboard reading nothing, focused calm mood. |
| `strip-group.webp` | Group classes · Every batch, every year | A small group class at a Sri Lankan home tuition room: a friendly female teacher in a saree explains a sum to five O/L students around a round table with exercise books and calculators, potted plants by an open window, warm afternoon light. |
| `strip-zoom.webp` | Online on Zoom · Recordings for paid students | A Sri Lankan student at home in the evening joins an online class on a laptop: the screen shows a teacher on a video call (no app logos), notebook and pen beside the laptop, a desk lamp, a cup of tea, a younger sibling reading in the soft background. |
| `strip-revision.webp` | Revision · Tutes posted or online | Revision season: a Sri Lankan A/L student at a study desk opens a freshly delivered courier envelope of printed tutes (plain white sheets, no text visible), a tablet beside it shows the same notes, sticky notes and a highlighter, bright morning light by a window. |
| `strip-team.webp` | Your team · Staff logins for assistants | Behind the scenes of a tuition institute office in Sri Lanka: two young assistants at a desk, one checks student payments on a laptop, the other sorts printed tutes into labelled trays (labels blank), a class timetable board on the wall (no readable text), friendly busy mood. |

Tips:
- Generate 3–4 versions of each and pick the ones with similar colour and light, so the strip looks like one set.
- If faces or hands look wrong, ask Nano Banana to "fix the hands and faces, keep everything else the same".
- After uploading, hard refresh the page (Ctrl + F5).
