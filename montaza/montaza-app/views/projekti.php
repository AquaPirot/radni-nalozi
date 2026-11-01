<?php /** Faza 1: kostur. Lista projekata po nedeljama dolazi u fazi 2. */ ?>
<div class="segments" role="tablist">
    <button class="seg on" type="button">Aktivni · 0</button>
    <button class="seg" type="button">Reklamacije · 0</button>
    <button class="seg" type="button">Završeno · 0</button>
</div>

<div class="empty">
    <?= icon('projekti', 32) ?>
    <p>Još nema projekata.</p>
    <p class="muted small">Prijavljen: <?= e($user['name']) ?> · <span class="mono"><?= e(strtoupper(ROLE_LABELS[$user['role']])) ?></span></p>
</div>

<?php if ($user['role'] === 'vlasnik'): ?>
    <a class="fab" href="#" aria-disabled="true" onclick="return false"><?= icon('plus') ?><span>Novi projekat</span></a>
<?php endif; ?>
