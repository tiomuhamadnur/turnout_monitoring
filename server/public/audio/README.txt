MRT Turnout Monitoring — Alarm Sound
====================================

The Dashboard plays /audio/alarm.mp3 whenever a TurnoutAlarmRaised event
arrives over Reverb. To customise the alarm tone, drop your own
"alarm.mp3" file into this folder. It MUST stay inside this directory
(public/audio/) — per BLUEPRINT, no external CDN is allowed.

If alarm.mp3 is missing or fails to load, the Dashboard automatically
falls back to a Web Audio API beep (resources/js/utils/alarmSound.js).

Recommended format: short (1-2 second), -3 dBFS, MP3 at 128 kbps.
