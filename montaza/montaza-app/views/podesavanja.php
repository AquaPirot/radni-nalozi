<?php /** @var array $user @var array $users @var array $errors @var ?array $updates @var ?string $updatesError @var array $teamsAll @var array $typesAll @var array $tplAll */
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

<section class="stack">
    <h2 class="h2">Montažne ekipe</h2>
    <?php foreach (array_merge($teamsAll, [['id' => 0, 'name' => '', 'leader_id' => null, 'members' => '', 'active' => 1]]) as $tm): $new = !(int) $tm['id']; ?>
        <details class="rcard<?= $tm['active'] ? '' : ' off' ?>">
            <summary><span><strong><?= $new ? '+ Nova ekipa' : e($tm['name']) ?></strong><span class="muted small"><?= $new ? 'npr. Marko + ekipa' : e(trim(user_name($tm['leader_id'] ? (int) $tm['leader_id'] : null) . ($tm['members'] ? ' · ' . $tm['members'] : '') . ($tm['active'] ? '' : ' · neaktivna'))) ?></span></span><?= icon('chevron') ?></summary>
            <form class="stack pad" method="post" action="<?= e(url('podesavanja')) ?>">
                <?= csrf_field() ?><input type="hidden" name="action" value="team_save"><input type="hidden" name="id" value="<?= (int) $tm['id'] ?>">
                <label class="field"><span>Naziv</span><input class="input" name="name" value="<?= e($tm['name']) ?>" required></label>
                <label class="field"><span>Vođa ekipe (dobija obaveštenja o terminima)</span>
                    <select class="input" name="leader_id"><option value="">—</option><?php foreach ($users as $u): if (!$u['active']) continue; ?><option value="<?= (int) $u['id'] ?>"<?= (int) $tm['leader_id'] === (int) $u['id'] ? ' selected' : '' ?>><?= e($u['name']) ?></option><?php endforeach; ?></select></label>
                <label class="field"><span>Članovi</span><input class="input" name="members" value="<?= e($tm['members']) ?>" placeholder="npr. Milan, Dejan"></label>
                <?php if (!$new): ?><label class="check"><input type="checkbox" name="active" value="1"<?= $tm['active'] ? ' checked' : '' ?>><span class="box"></span><span>Aktivna</span></label><?php endif; ?>
                <button class="btn primary block" type="submit">Sačuvaj ekipu</button>
            </form>
        </details>
    <?php endforeach; ?>
</section>

<section class="stack">
    <h2 class="h2">Sistemi i faze proizvodnje</h2>
    <p class="small muted">Faze se pišu jedna po redu. „(bez slike)" na kraju reda = faza se zatvara bez fotografije. Izmena važi za nove projekte; postojeći ostaju kakvi su.</p>
    <?php foreach (array_merge($typesAll, [['id' => 0, 'name' => '', 'fields' => '', 'active' => 1]]) as $ty): $new = !(int) $ty['id']; $f = array_filter(explode(',', (string) $ty['fields'])); ?>
        <details class="rcard<?= $ty['active'] ? '' : ' off' ?>">
            <summary><span><strong><?= $new ? '+ Nova vrsta sistema' : e($ty['name']) ?></strong><span class="muted small"><?= $new ? '' : e(implode(' › ', $tplAll[$ty['id']] ?? [])) ?></span></span><?= icon('chevron') ?></summary>
            <form class="stack pad" method="post" action="<?= e(url('podesavanja')) ?>">
                <?= csrf_field() ?><input type="hidden" name="action" value="type_save"><input type="hidden" name="id" value="<?= (int) $ty['id'] ?>">
                <label class="field"><span>Naziv</span><input class="input" name="name" value="<?= e($ty['name']) ?>" required></label>
                <div class="field"><span>Polja specifikacije</span>
                    <div class="chips"><?php foreach (SPEC_FIELDS as $k => $label): ?><label class="chip-radio"><input type="checkbox" name="fields[]" value="<?= $k ?>"<?= in_array($k, $f, true) ? ' checked' : '' ?>><span><?= e($label) ?></span></label><?php endforeach; ?></div></div>
                <label class="field"><span>Faze (jedna po redu)</span><textarea class="input mono" name="phases" rows="6"><?= e(implode("\n", $tplAll[$ty['id']] ?? [])) ?></textarea></label>
                <?php if (!$new): ?><label class="check"><input type="checkbox" name="active" value="1"<?= $ty['active'] ? ' checked' : '' ?>><span class="box"></span><span>Aktivan (nudi se u formi)</span></label><?php endif; ?>
                <button class="btn primary block" type="submit">Sačuvaj sistem</button>
            </form>
        </details>
    <?php endforeach; ?>
</section>

<form class="stack" method="post" action="<?= e(url('podesavanja')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="settings">
    <h2 class="h2">Pravila</h2>
    <div class="grid3">
        <label class="field"><span>Okvirno montaža / ned.</span><input class="input mono" name="weekly_capacity" type="number" min="1" max="50" value="<?= e(setting('weekly_capacity', '5')) ?>"></label>
        <label class="field"><span>Slike hala</span><input class="input mono" name="min_photos_hala" type="number" min="0" max="10" value="<?= e(setting('min_photos_hala', '1')) ?>"></label>
        <label class="field"><span>Slike montaža</span><input class="input mono" name="min_photos_montaza" type="number" min="1" max="20" value="<?= e(setting('min_photos_montaza', '3')) ?>"></label>
    </div>

    <h2 class="h2">Telegram <span class="muted small">(opciono)</span></h2>
    <p class="small muted">Obaveštenja na telefon rade i bez Telegrama: svako u aplikaciji dodirne zvonce. Telegram je dodatni kanal. Napravi bota kod <span class="mono">@BotFather</span> (preporuka: novi bot samo za Montažu), nalepi token ovde, pa neka svako pošalje botu poruku „start". Onda klikni „Pronađi chat ID" i upiši brojeve korisnicima iznad.</p>
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
