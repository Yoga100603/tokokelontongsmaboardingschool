/** Umpan balik suara kasir via Web Audio API (port lib/sound.ts). */
function ctx() {
    const C = window.AudioContext || window.webkitAudioContext;
    return C ? new C() : null;
}

function nada(c, freq, tipe, mulai, lama, vol) {
    const osc = c.createOscillator();
    const gain = c.createGain();
    osc.type = tipe;
    osc.frequency.setValueAtTime(freq, c.currentTime + mulai);
    gain.gain.setValueAtTime(vol, c.currentTime + mulai);
    gain.gain.exponentialRampToValueAtTime(0.001, c.currentTime + mulai + lama);
    osc.connect(gain);
    gain.connect(c.destination);
    osc.start(c.currentTime + mulai);
    osc.stop(c.currentTime + mulai + lama);
}

export function bunyiBeep() {
    try {
        const c = ctx();
        c && nada(c, 1200, 'sine', 0, 0.08, 0.15);
    } catch {}
}

export function bunyiSukses() {
    try {
        const c = ctx();
        c && [523.25, 659.25, 783.99, 1046.5].forEach((f, i) => nada(c, f, 'triangle', i * 0.07, 0.2, 0.12));
    } catch {}
}

export function bunyiGalat() {
    try {
        const c = ctx();
        c && nada(c, 150, 'sawtooth', 0, 0.2, 0.2);
    } catch {}
}
