// Browser-side alarm tone for FAILURE events.
//
// Tries an MP3 file from /audio/alarm.mp3 first (placed in public/audio/),
// falls back to a short 880 Hz beep via the Web Audio API so the dashboard
// still warns the user even when the operator hasn't dropped in a file.
//
// No internet CDN — both paths stay inside the bundle / static folder.

let audioElement = null;
let mp3Available = null;     // null = unknown, true/false = probed
let audioContext = null;

const MP3_URL = '/audio/alarm.mp3';

function ensureAudioElement() {
    if (audioElement) return audioElement;
    audioElement = new Audio(MP3_URL);
    audioElement.preload = 'auto';
    audioElement.addEventListener('error', () => { mp3Available = false; });
    return audioElement;
}

function beepFallback() {
    try {
        if (!audioContext) {
            const Ctx = window.AudioContext || window.webkitAudioContext;
            if (!Ctx) return;
            audioContext = new Ctx();
        }
        const osc  = audioContext.createOscillator();
        const gain = audioContext.createGain();
        osc.type = 'square';
        osc.frequency.value = 880;
        // Quick fade in/out so it doesn't click.
        gain.gain.setValueAtTime(0.0001, audioContext.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.25, audioContext.currentTime + 0.02);
        gain.gain.exponentialRampToValueAtTime(0.0001, audioContext.currentTime + 0.6);
        osc.connect(gain).connect(audioContext.destination);
        osc.start();
        osc.stop(audioContext.currentTime + 0.65);
    } catch (_) {
        // Browser blocked autoplay; alarm popup still appears.
    }
}

/**
 * Play the alarm sound. Safe to call repeatedly — multiple alarms will
 * just retrigger the same audio element / beep.
 */
export function playAlarm() {
    if (mp3Available === false) {
        beepFallback();
        return;
    }
    const el = ensureAudioElement();
    try {
        el.currentTime = 0;
        const p = el.play();
        if (p && typeof p.then === 'function') {
            p.then(() => { mp3Available = true; })
             .catch(() => {
                 // Autoplay blocked or file missing — beep.
                 mp3Available = false;
                 beepFallback();
             });
        }
    } catch (_) {
        beepFallback();
    }
}

/**
 * Optional: call once from a user-gesture handler (click/keypress) so
 * later autoplay-blocked alarms are allowed. The Dashboard mounts an
 * invisible primer button that calls this on the first click.
 */
export function primeAlarm() {
    try {
        if (!audioContext) {
            const Ctx = window.AudioContext || window.webkitAudioContext;
            if (Ctx) audioContext = new Ctx();
        }
        if (audioContext && audioContext.state === 'suspended') {
            audioContext.resume();
        }
        const el = ensureAudioElement();
        el.muted = true;
        el.play().then(() => {
            el.pause();
            el.currentTime = 0;
            el.muted = false;
        }).catch(() => { /* ignored */ });
    } catch (_) { /* ignored */ }
}
