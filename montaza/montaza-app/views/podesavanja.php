<?php /** @var array $user @var array $users @var array $errors @var ?array $updates @var ?string $updatesError */
$token = (string) setting('telegram_bot_token', '');
$tgOn = $token !== '';
?>
<?php foreach ($errors as $er): ?><div class="alert warn"><?= e($er) ?></div><?php endforeach; ?>

<section class="stack">
    <h2 class="h2">Korisnici</h2>
    <?php foreach ($users as $u): $self = (int) $u['id'] === (int) $user['id']; ?>
        <details class="rcard user-card<?= $u['active'] ? '' : ' off' ?>">
            <summary>
                <span><strong><?= e($u['name']) ?><?= $self ? ' (ti)' : '' ?></strong>
                    <span class="mono small muted"><?= e($u['username']) ?> · <?= e(mb_strtoupper(ROLE_LABELS[$u['role']])) ?><?= (int) $u['phones'] ? ' · OBAVEŠTENJA (' . (int) $u['phones'] . ')' : '' ?><?= $u['telegram_chat_id'] ? ' · TELEGRAM' : '' ?><?= $u['active'] ? '' : ' · NEAKTIVAN' ?></span>
                    <?php if ((int) $u['fails'] >= 5): ?><span class="small warn"><?= (int) $u['fails'] ?> pogrešnih lozinki u 24h</span><?php endif; ?>
                </span><?= icon('chevron') ?>
            </summary>
            <form class="stack pad" method="post" action="<?= e(url('podesavanja')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update_user">
                <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                <label class="field"><span>Ime</span><input class="input" name="name" value="<?= e($u['name']) ?>"></label>
                <?php if (!$self): ?>
                    <label class="field"><span>Uloga</span>
                        <select class="input" name="role"><?php foreach (ROLE_LABELS as $k => $l): ?><option value="<?= $k ?>"<?= $u['role'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
                <?php endif; ?>
                <label class="field"><span>Telegram chat ID</span><input class="input mono" name="telegram_chat_id" value="<?= e($u['telegram_chat_id']) ?>" inputmode="numeric" placeholder="npr. 123456789"></label>
                <label class="field"><span>Nova lozinka (prazno = bez promene)</span><input class="input" type="password" name="password" autocomplete="new-password" minlength="<?= PASSWORD_MIN ?>"></label>
                <?php if (!$self): ?>
                    <label class="check"><input type="checkbox" name="active" value="1"<?= $u['active'] ? ' checked' : '' ?>><span class="box"></span><span>Aktivan (može da se prijavi)</span></label>
                <?php endif; ?>
                <button class="btn primary block" type="submit">Sačuvaj</button>
            </form>
            <?php if ((int) $u['fails'] >= 5): ?>
                <form class="pad" method="post" action="<?= e(url('podesavanja')) ?>">
                    <?= csrf_field() ?><input type="hidden" name="action" value="unlock"><input type="hidden" name="username" value="<?= e($u['username']) ?>">
                    <button class="btn ghost block" type="submit">Otključaj nalog</button>
                </form>
            <?php endif; ?>
            <?php if ($tgOn && $u['telegram_chat_id']): ?>
                <form class="pad" method="post" action="<?= e(url('podesavanja')) ?>">
                    <?= csrf_field() ?><input type="hidden" name="action" value="test_telegram"><input type="hidden" name="chat_id" value="<?= e($u['telegram_chat_id']) ?>">
                    <button class="btn ghost block" type="submit">Pošalji test poruku</button>
                </form>
            <?php endif; ?>
        </details>
    <?php endforeach; ?>

    <details class="rcard">
        <summary><span><strong>+ Dodaj korisnika</strong><span class="muted small">npr. još jedan majstor na terenu</span></span><?= icon('chevron') ?></summary>
        <form class="stack pad" method="post" action="<?= e(url('podesavanja')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_user">
            <label class="field"><span>Ime</span><input class="input" name="name" required></label>
            <label class="field"><span>Korisničko ime</span><input class="input" name="username" autocapitalize="none" required></label>
            <label class="field"><span>Uloga</span><select class="input" name="role"><?php foreach (ROLE_LABELS as $k => $l): ?><option value="<?= $k ?>"<?= $k === 'teren' ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
            <label class="field"><span>Lozinka (min. <?= PASSWORD_MIN ?>)</span><input class="input" type="password" name="password" autocomplete="new-password" required></label>
            <label class="field"><span>Telegram chat ID (opciono)</span><input class="input mono" name="telegram_chat_id" inputmode="numeric"></label>
            <button class="btn primary block" type="submit">Dodaj</button>
        </form>
    </details>
</section>

<form class="stack" method="post" action="<?= e(url('podesavanja')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="settings">
    <h2 class="h2">Stavke koje se biraju u projektu</h2>
    <label class="field"><span>Jedna po redu, redom kojim se rade <span class="small muted">— Nalog i Montaža se dodaju sami</span></span>
        <textarea class="input" name="item_catalog" rows="7"><?= e(implode("\n", item_catalog())) ?></textarea></label>

    <h2 class="h2">Pravila</h2>
    <div class="grid2">
        <label class="field"><span>Najmanje slika za montažu</span><input class="input mono" name="min_photos_montaza" type="number" min="1" max="20" value="<?= e(setting('min_photos_montaza', '3')) ?>"></label>
    </div>

    <h2 class="h2">Telegram <span class="muted small">(opciono)</span></h2>
    <p class="small muted">Obaveštenja na telefon rade i bez Telegrama: svako u aplikaciji otvori meni (krug sa slovom) → Obaveštenja. Telegram je dodatni kanal. Napravi bota kod <span class="mono">@BotFather</span> (preporuka: novi bot samo za Montažu), nalepi token ovde, pa neka svako pošalje botu poruku „start". Onda klikni „Pronađi chat ID" i upiši brojeve korisnicima iznad.</p>
    <label class="field"><span>Token bota <?= $tgOn ? '<span class="mono muted">(sačuvan: …' . e(substr($token, -4)) . ')</span>' : '' ?></span>
        <input class="input mono" name="telegram_bot_token" autocomplete="off" placeholder="<?= $tgOn ? 'prazno = bez promene' : '123456789:ABC…' ?>"></label>
    <?php if ($tgOn): ?><label class="check"><input type="checkbox" name="telegram_clear" value="1"><span class="box"></span><span>Isključi Telegram (obriši token)</span></label><?php endif; ?>
    <label class="field"><span>Chat ID vlasnika / grupe (opciono)</span>
        <input class="input mono" name="telegram_owner_chat_id" value="<?= e(setting('telegram_owner_chat_id', '')) ?>" placeholder="ako ti nije upisan iznad"></label>
    <button class="btn primary block" type="submit">Sačuvaj podešavanja</button>
</form>

<?php if ($tgOn): ?>
    <section class="stack">
        <a class="btn ghost block" href="<?= e(url('podesavanja', ['chats' => 1])) ?>">Pronađi chat ID</a>
        <?php if ($updatesError): ?>
            <div class="alert warn">Telegram: <?= e($updatesError) ?><?= str_contains($updatesError, 'webhook') ? ' — ovaj bot već koristi drugi sistem (webhook). Napravi novi bot za Montažu.' : '' ?></div>
        <?php elseif ($updates !== null): ?>
            <?php if (!$updates): ?>
                <p class="small muted">Nema novih poruka. Neka svako pošalje botu poruku, pa probaj ponovo.</p>
            <?php else: ?>
                <div class="list">
                    <?php foreach ($updates as $id => $label): ?>
                        <div class="list-row"><span><?= e($label ?: 'Chat') ?></span><span class="mono"><?= e((string) $id) ?></span></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </section>
<?php endif; ?>
