<?php /** @var array $task @var int $existing @var ?string $error @var string $note */ ?>
<?php if ($error): ?><div class="alert warn" role="alert"><?= e($error) ?></div><?php endif; ?>
<form class="stack done-form" method="post" action="<?= e(url('zadatak_zavrsi', ['id' => $task['id']])) ?>"
      data-gate data-confirm="Potvrđuješ da je zadatak završen?">
    <?= csrf_field() ?><?= once_field() ?>
    <?= uploader('foto_ids', 'Fotografije', true, 'foto', $task['requires_photo'] && !$existing, $task['requires_photo'] ? ($existing ? 'već ima ' . $existing : 'obavezna bar jedna') : 'opciono') ?>
    <label class="field"><span>Napomena</span>
        <textarea class="input" name="note" rows="3" placeholder="Opciono — npr. servis plaćen 12.000 din"><?= e($note) ?></textarea></label>
    <div class="sticky-foot">
        <p class="small muted center" data-missing></p>
        <button class="btn primary block" type="submit">Potvrdi završetak</button>
    </div>
</form>
