# Audio Assets

This directory is for **source** audio assets that should be tracked in git
or processed by the build. Files served to the browser live in
`public/audio/` (Vite does not pipeline arbitrary audio).

## alarm.mp3

The Dashboard expects `/audio/alarm.mp3` (i.e. `public/audio/alarm.mp3`)
for the FAILURE event sound. If absent, the Dashboard falls back to a
short Web Audio API beep — see `resources/js/utils/alarmSound.js`.

To install a custom tone:

```
cp your-alarm.mp3 public/audio/alarm.mp3
```

No internet CDN — keep all audio inside this repository.
