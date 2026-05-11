<template>
  <svg viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
    <!-- Définitions des effets : Dégradés et Ombres -->
    <defs>
      <linearGradient id="bg_grad" x1="0" y1="0" x2="48" y2="48" gradientUnits="userSpaceOnUse">
        <stop stop-color="#FB923C"/>
        <stop offset="1" stop-color="#EA580C"/>
      </linearGradient>

      <linearGradient id="glass_shine" x1="0" y1="0" x2="48" y2="48" gradientUnits="userSpaceOnUse">
        <stop stop-color="white" stop-opacity="0.4"/>
        <stop offset="0.5" stop-color="white" stop-opacity="0"/>
      </linearGradient>

      <filter id="shadow" x="-20%" y="-20%" width="140%" height="140%">
        <feGaussianBlur in="SourceAlpha" stdDeviation="1.5"/>
        <feOffset dx="0" dy="2" result="offsetblur"/>
        <feComponentTransfer>
          <feFuncA type="linear" slope="0.3"/>
        </feComponentTransfer>
        <feMerge>
          <feMergeNode/>
          <feMergeNode in="SourceGraphic"/>
        </feMerge>
      </filter>
    </defs>

    <!-- Corps principal avec ombre portée -->
    <rect width="48" height="48" rx="14" fill="url(#bg_grad)" />

    <!-- Reflet "Glass" sur le coin supérieur -->
    <path d="M14 0H34C41.732 0 48 6.268 48 14V18L0 34V14C0 6.268 6.268 0 14 0Z" fill="url(#glass_shine)" />

    <!-- Icône Headset Stylisée -->
    <g filter="url(#shadow)">
      <!-- Arceau -->
      <path d="M14 22C14 16.477 18.477 12 24 12C29.523 12 34 16.477 34 22" stroke="white" stroke-width="3.5" stroke-linecap="round"/>

      <!-- Écouteurs (formes plus organiques) -->
      <rect x="12" y="22" width="6" height="10" rx="3" fill="white"/>
      <rect x="30" y="22" width="6" height="10" rx="3" fill="white"/>

      <!-- Micro fin et dynamique -->
      <path d="M31 31C31 34.5 28.5 37 25 37" stroke="white" stroke-width="2" stroke-linecap="round" opacity="0.9"/>
      <circle cx="24" cy="37" r="1.5" fill="white"/>
    </g>

    <!-- Petit point de brillance final -->
    <circle cx="38" cy="10" r="1.5" fill="white" fill-opacity="0.6"/>
  </svg>
</template>
