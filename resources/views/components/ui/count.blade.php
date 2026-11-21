@props(['value' => 0])
<span {{ $attributes->class('num') }} x-data="{ n: 0, t: {{ (int) $value }} }"
      x-init="if (matchMedia('(prefers-reduced-motion: reduce)').matches) { n = t; return; } const s = performance.now(); const step = (now) => { const k = Math.min(1, (now - s) / 700); n = Math.round(t * (1 - Math.pow(1 - k, 3))); if (k < 1) requestAnimationFrame(step); }; requestAnimationFrame(step);"
      x-text="n">{{ (int) $value }}</span>
