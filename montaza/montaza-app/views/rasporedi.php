<?php /** @var array $project @var array $options @var array $load @var int $capacity */ ?>
<p class="muted"><?= e(implode(' · ', array_filter([$project['city'], short_desc($project['description']), $project['deadline'] ? 'rok ' . fmt_dm($project['deadline']) : null]))) ?></p>
<form class="stack" method="post" action="<?= e(url('rasporedi', ['id' => $project['id']])) ?>">
    <?= csrf_field() ?>
    <div class="radios">
        <?php foreach ($options as $val => $label):
            $n = $load[$val] ?? 0;
            $mine = $project['install_week_start'] === $val;
            $full = !$mine && $n >= $capacity;
        ?>
            <label class="radio week-opt"><input type="radio" name="week" value="<?= e($val) ?>"<?= $mine ? ' checked' : '' ?>>
                <span><span class="grow"><?= e($label) ?></span><span class="mono small<?= $full ? ' warn' : ' muted' ?>"><?= $n ?> / <?= $capacity ?><?= $full ? ' · PUNO' : '' ?></span></span></label>
        <?php endforeach; ?>
        <label class="radio week-opt"><input type="radio" name="week" value=""<?= $project['install_week_start'] === null ? ' checked' : '' ?>><span>Nije raspoređeno</span></label>
    </div>
    <button class="btn primary block" type="submit">Sačuvaj</button>
    <a class="btn ghost block" href="<?= e(url('projekat', ['id' => $project['id']])) ?>">Otvori projekat</a>
</form>
