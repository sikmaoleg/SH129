<?php
/**
 * Небольшой набор line-иконок (SVG, 24x24, stroke=currentColor).
 * Не эмодзи и не внешняя библиотека — самодостаточные инлайн-иконки для консистентного вида.
 */
function icon(string $name, string $class = 'icon'): string
{
    $paths = [
        'flag'         => '<path d="M5 3v18"/><path d="M5 4h11l-1.8 3.5L16 11H5"/>',
        'heart-hand'   => '<path d="M12 21s-6.5-4.35-9-8.28C1.2 9.9 2.3 6.5 5.4 6c2-.32 3.4.66 4.6 2 1.2-1.34 2.6-2.32 4.6-2 3.1.5 4.2 3.9 2.4 6.72C18.5 16.65 12 21 12 21Z"/>',
        'leaf'         => '<path d="M20 4C10 4 4 10 4 19c9 0 15-6 16-16Z"/><path d="M4 20 12 12"/>',
        'camera'       => '<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7 9.5 4h5L16 7"/><circle cx="12" cy="13.5" r="3.5"/>',
        'dumbbell'     => '<path d="M6.5 9v6M17.5 9v6"/><path d="M3.5 10v4M20.5 10v4"/><path d="M6.5 12h11"/>',
        'academic-cap' => '<path d="M2.5 9 12 4.5 21.5 9 12 13.5 2.5 9Z"/><path d="M6.5 11v4.2c0 1 2.4 2.3 5.5 2.3s5.5-1.3 5.5-2.3V11"/>',
        'users'        => '<circle cx="9" cy="8" r="3.2"/><path d="M2.8 19c.6-3 3-5 6.2-5s5.6 2 6.2 5"/><circle cx="17" cy="9" r="2.6"/><path d="M15.5 14c2.6.2 4.5 2 5 4.6"/>',
        'calendar'     => '<rect x="3.5" y="5" width="17" height="15" rx="2"/><path d="M3.5 9.5h17"/><path d="M8 3v4M16 3v4"/>',
        'clock'        => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/>',
        'grid'         => '<rect x="3.5" y="3.5" width="7" height="7" rx="1.3"/><rect x="13.5" y="3.5" width="7" height="7" rx="1.3"/><rect x="3.5" y="13.5" width="7" height="7" rx="1.3"/><rect x="13.5" y="13.5" width="7" height="7" rx="1.3"/>',
        'map-pin'      => '<path d="M12 21.5S5 15.4 5 10a7 7 0 1 1 14 0c0 5.4-7 11.5-7 11.5Z"/><circle cx="12" cy="10" r="2.6"/>',
        'mail'         => '<rect x="3" y="5.5" width="18" height="13" rx="2.2"/><path d="m4 7 8 6 8-6"/>',
        'phone'        => '<path d="M6 3.5 9 4l1 4-2 1.5c.9 2.2 2.8 4.1 5 5l1.5-2 4 1v3c0 1-1 1.5-2 1.5C9.5 18 6 14.5 4.5 7.5 4.4 6.5 5 5.5 6 3.5Z"/>',
        'arrow-right'  => '<path d="M4.5 12h15"/><path d="m13 6 6 6-6 6"/>',
        'chevron-down' => '<path d="m6 9 6 6 6-6"/>',
        'medal'        => '<path d="M9 3 6.5 8.5M15 3l2.5 5.5"/><circle cx="12" cy="15" r="5.8"/><circle cx="12" cy="15" r="2.3"/>',
        'badge-check'  => '<path d="M12 2.5 14.6 4l3.2-.4 1 3.1 2.7 1.8-1.3 3 1.3 3-2.7 1.8-1 3.1-3.2-.4L12 21.5 9.4 20l-3.2.4-1-3.1-2.7-1.8 1.3-3-1.3-3 2.7-1.8 1-3.1L9.4 4 12 2.5Z"/><path d="m8.5 12.3 2.3 2.3 4.7-4.7"/>',
        'shield-check' => '<path d="M12 3 5 5.5V11c0 5 3 8.3 7 9.5 4-1.2 7-4.5 7-9.5V5.5L12 3Z"/><path d="m9 12 2.2 2.2L15.5 10"/>',
        'chart-bar'    => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
        'user-plus'    => '<circle cx="9" cy="8" r="3.6"/><path d="M2.5 19.5c.6-3.4 3.2-5.6 6.5-5.6s5.9 2.2 6.5 5.6"/><path d="M18.5 8v5M16 10.5h5"/>',
        'clipboard'    => '<rect x="5.5" y="4.5" width="13" height="16" rx="2"/><path d="M9 4.5V3.3c0-.4.4-.8.9-.8h4.2c.5 0 .9.4.9.8v1.2"/><path d="M8.5 10h7M8.5 13.5h7M8.5 17h4.5"/>',
        'lock-off'     => '<rect x="4.5" y="10.5" width="15" height="9.5" rx="2"/><path d="M8 10.5V8a4 4 0 0 1 7.4-2.1"/>',
        'target'       => '<circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="4.2"/><circle cx="12" cy="12" r=".8" fill="currentColor" stroke="none"/>',
        'telegram'     => '<path d="m3 11.2 17-6.7-3 15.5-5.6-4-2.7 2.6-.4-4 9-7-11 5.7-3.3-1.1Z"/>',
        'menu'         => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'close'        => '<path d="m6 6 12 12M18 6 6 18"/>',
        'check'        => '<path d="m5 13 4.5 4.5L19 8"/>',
        'alert'        => '<path d="M12 3.5 21.5 20h-19L12 3.5Z"/><path d="M12 9.5v4.2"/><circle cx="12" cy="17" r=".9" fill="currentColor" stroke="none"/>',
        'download'     => '<path d="M12 3.5v11M8 11l4 4 4-4"/><path d="M4.5 17v2a2 2 0 0 0 2 2h11a2 2 0 0 0 2-2v-2"/>',
    ];
    $body = $paths[$name] ?? $paths['check'];
    return '<svg class="' . htmlspecialchars($class, ENT_QUOTES) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $body . '</svg>';
}
