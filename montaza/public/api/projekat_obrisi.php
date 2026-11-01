<?php
declare(strict_types=1);

require __DIR__ . '/_owner_project.php';

delete_project($project);
flash('ok', 'Projekat ' . $project['code'] . ' je obrisan.');
redirect(url());
