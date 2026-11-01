<?php /** @var array $groups */ ?>
<?php if (!$groups): ?>
    <div class="empty"><?= icon('image', 32) ?><p>Nema fotografija.</p></div>
<?php endif; ?>
<?php foreach ($groups as $label => $list): ?>
    <section class="stack">
        <h2 class="group-head"><span><?= e(mb_strtoupper($label)) ?></span><span><?= count($list) ?></span></h2>
        <div class="gallery">
            <?php foreach ($list as $f): ?>
                <a href="<?= e(file_url((int) $f['id'])) ?>" target="_blank" rel="noopener">
                    <img src="<?= e(file_url((int) $f['id'], true)) ?>" alt="" loading="lazy">
                    <?php if ($f['kind'] === 'posle'): ?><span class="pp-label mono">POSLE</span><?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
<?php endforeach; ?>
