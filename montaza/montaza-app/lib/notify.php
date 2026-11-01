<?php
declare(strict_types=1);

// Obaveštenja. Faza 6 ovde dodaje slanje preko Telegrama.
// Slanje NIKAD ne sme da obori akciju (try/catch + log).

/** @param list<string> $roles  uloge kojima ide poruka ('vlasnik', 'hala', 'teren') */
function notify(array $roles, string $text, ?int $projectId = null): void
{
    try {
        // Faza 6: pronađi telegram_chat_id za uloge i pošalji.
    } catch (Throwable $ex) {
        error_log('notify: ' . $ex->getMessage());
    }
}

function project_ref(array $project): string
{
    return $project['customer_name'] . ($project['city'] ? ', ' . $project['city'] : '');
}
