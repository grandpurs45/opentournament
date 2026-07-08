<?php

declare(strict_types=1);

function plugins(): array
{
    return [
        'generic' => [
            'key' => 'generic',
            'name' => 'Generique',
            'description' => 'Score numerique simple, vainqueur au plus haut score.',
            'defaults' => ['targetScore' => 10, 'winPoints' => 3, 'lossPoints' => 0],
            'validator' => 'generic_validate_score',
            'winner' => 'highest_score_winner',
        ],
        'molkky' => [
            'key' => 'molkky',
            'name' => 'Molkky',
            'description' => 'Victoire a 50 points, egalite refusee, score superieur a 50 refuse.',
            'defaults' => ['targetScore' => 50, 'winPoints' => 3, 'lossPoints' => 0],
            'validator' => 'molkky_validate_score',
            'winner' => 'target_score_winner',
        ],
    ];
}

function plugin(string $key): array
{
    $plugins = plugins();
    return $plugins[$key] ?? $plugins['generic'];
}

function generic_validate_score(int $scoreA, int $scoreB, array $settings): array
{
    if ($scoreA < 0 || $scoreB < 0) {
        return [false, 'Les scores doivent etre positifs.'];
    }
    if ($scoreA === $scoreB) {
        return [false, 'Un match nul ne permet pas de determiner un vainqueur.'];
    }
    return [true, ''];
}

function molkky_validate_score(int $scoreA, int $scoreB, array $settings): array
{
    $target = (int) ($settings['targetScore'] ?? 50);
    if ($scoreA < 0 || $scoreB < 0) {
        return [false, 'Les scores doivent etre positifs.'];
    }
    if ($scoreA > $target || $scoreB > $target) {
        return [false, 'Au Molkky, un score valide ne peut pas depasser ' . $target . '.'];
    }
    if ($scoreA === $scoreB) {
        return [false, 'Au Molkky, une egalite n est pas valide.'];
    }
    if ($scoreA !== $target && $scoreB !== $target) {
        return [false, 'Au Molkky, le vainqueur doit atteindre exactement ' . $target . '.'];
    }
    return [true, ''];
}

function highest_score_winner(array $match, int $scoreA, int $scoreB, array $settings): ?int
{
    return $scoreA > $scoreB ? (int) $match['participant_a_id'] : (int) $match['participant_b_id'];
}

function target_score_winner(array $match, int $scoreA, int $scoreB, array $settings): ?int
{
    return highest_score_winner($match, $scoreA, $scoreB, $settings);
}
