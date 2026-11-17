# Demo data (bin/seed-demo.php)

Made on 17 November 2026. Every password is `Demo#2026`. Students and parents sign in with a WhatsApp code:
on a server without WhatsApp set `ENLIXO_DEMO_OTP=1`, then every demo number takes the code `123456` (only numbers starting +9470000).

| Plan | Tutor (tutor/) | Staff (tutor/) | Student who paid (portal/) | Student who owes (portal/) | Parent (parent/) | Classes | Students |
|---|---|---|---|---|---|---|---|
| FREE | demo.free@demo.enlixo.lk | - | 0700001001 (Dulaj Rathnayake) | 0700001002 (Sewwandi Wickramasinghe) | - (FREE: no parent login) | 3 | 18 |
| STARTER | demo.starter@demo.enlixo.lk | - | 0700002001 (Ravindu Bandara) | 0700002002 (Methmi Gunawardena) | 0700006001 | 4 | 36 |
| GROW | demo.grow@demo.enlixo.lk | assistant.sp@demo.enlixo.lk | 0700003001 (Kalana Jayasinghe) | 0700003002 (Nethmi Wickramasinghe) | 0700007001 | 5 | 60 |
| CUSTOM | demo.custom@demo.enlixo.lk | assistant.de@demo.enlixo.lk | 0700004001 (Ravindu Kumara) | 0700004002 (Tharushi Rathnayake) | 0700008001 | 6 | 80 |

What each one shows:

- **FREE:** 3 classes, 18 students, recordings, download tutes; no parents, no tutes by post, no pre-recorded classes, upgrade nudges.
- **STARTER:** 4 classes, parents, tutes by post with parcels, upgrade nudges to GROW; no pre-recorded classes.
- **GROW:** 5 classes, pre-recorded classes (one opens in 5 days), a staff member (Class Assistant role), no upgrade nudges.
- **CUSTOM:** 6 classes on an agreed price, everything GROW has.

Every tutor has 14 weekly class days (10 done, 4 coming), attendance, fees of the last 3 months and this month (paid, slip waiting, unpaid, overdue, one rejected slip) and receipts.
Remove it with `php bin/seed-demo.php remove`; make it again with `php bin/seed-demo.php reset`.
