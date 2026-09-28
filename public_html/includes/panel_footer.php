    </main>
  </div>
</div>

<div class="palette" id="palette" role="dialog" aria-modal="true" aria-label="Поиск по панели" hidden>
  <div class="palette-box">
    <label class="palette-input"><?= icon('search') ?><input id="paletteInput" type="text" placeholder="<?= isAdmin() ? 'Волонтёр, раздел или действие' : 'Раздел панели' ?>" autocomplete="off" aria-controls="paletteList"><kbd>Esc</kbd></label>
    <div class="palette-list" id="paletteList" role="listbox"></div>
    <p class="palette-hint"><kbd>↑</kbd><kbd>↓</kbd> выбрать <kbd>Enter</kbd> открыть</p>
  </div>
</div>
<div class="toasts" id="toasts" aria-live="polite"></div>
<div class="tip" id="tip" role="tooltip" hidden></div>

<script type="application/json" id="paletteData"><?= json_encode(['items' => $paletteItems, 'search' => $paletteSearch, 'searchIcon' => icon('user-circle')], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script src="<?= assetUrl('assets/js/panel.js') ?>"></script>
</body>
</html>
