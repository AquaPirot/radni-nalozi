<?php $users = q_all('SELECT id, name, username, role, active FROM users ORDER BY FIELD(role, "vlasnik", "hala", "teren"), name'); ?>
<section class="stack">
    <h2 class="h2">Korisnici</h2>
    <div class="list">
        <?php foreach ($users as $u): ?>
            <div class="list-row">
                <div>
                    <div class="strong"><?= e($u['name']) ?></div>
                    <div class="mono muted small"><?= e($u['username']) ?> · <?= e(strtoupper(ROLE_LABELS[$u['role']])) ?></div>
                </div>
                <span class="tag<?= $u['active'] ? ' ok' : '' ?>"><?= $u['active'] ? 'AKTIVAN' : 'NEAKTIVAN' ?></span>
            </div>
        <?php endforeach; ?>
    </div>
    <p class="muted small">Dodavanje korisnika, kapacitet, broj slika i Telegram podešavaju se ovde — stiže u kasnijim fazama.</p>
</section>
