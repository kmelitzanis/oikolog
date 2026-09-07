// Pinch-zoom suppression for the installed app.
//
// The viewport meta carries `user-scalable=no`, which every browser honours
// except Safari on iOS — it has ignored the flag since iOS 10 so that a badly
// built page can never trap a reader who needs to magnify it. The app is a PWA
// meant to feel native, so we opt out explicitly here instead.
//
// `gesturestart` is Safari's own pinch event, and the double-tap guard is a
// timing check rather than a blanket preventDefault: swallowing every fast tap
// would break ordinary double-taps inside inputs.

document.addEventListener('gesturestart', (e) => e.preventDefault());
document.addEventListener('gesturechange', (e) => e.preventDefault());
document.addEventListener('gestureend', (e) => e.preventDefault());

let lastTouchEnd = 0;
document.addEventListener('touchend', (e) => {
    const now = Date.now();
    if (now - lastTouchEnd <= 300) e.preventDefault();
    lastTouchEnd = now;
}, { passive: false });
