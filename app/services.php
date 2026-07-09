<?php

declare(strict_types=1);

function all_tournaments(): array
{
    return db()->query('SELECT * FROM tournaments ORDER BY created_at DESC')->fetchAll();
}

function tournament_formats(): array
{
    return [
        'pools' => [
            'label' => 'Poules uniquement',
            'description' => 'Poules equilibrees puis classement.',
        ],
        'round_robin' => [
            'label' => 'Championnat integral',
            'description' => 'Une seule poule ou tout le monde rencontre tout le monde.',
        ],
        'pools_finals' => [
            'label' => 'Poules + phases finales',
            'description' => 'Poules puis demi-finales, finale et petite finale.',
        ],
    ];
}

function tournament_format_label(string $format): string
{
    return tournament_formats()[$format]['label'] ?? $format;
}

function normalize_tournament_format(string $format): string
{
    return array_key_exists($format, tournament_formats()) ? $format : 'pools';
}

function find_tournament(int $id): array
{
    $stmt = db()->prepare('SELECT * FROM tournaments WHERE id = ?');
    $stmt->execute([$id]);
    $tournament = $stmt->fetch();
    if (!$tournament) {
        http_response_code(404);
        exit('Tournament not found');
    }
    $tournament['settings_array'] = json_decode((string) $tournament['settings'], true) ?: [];
    return $tournament;
}

function create_tournament(array $data): int
{
    $selectedPlugin = plugin($data['plugin_key']);
    $settings = $selectedPlugin['defaults'];
    $stmt = db()->prepare('INSERT INTO tournaments (name, event_date, plugin_key, format, number_of_fields, status, settings, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $now = now_iso();
    $stmt->execute([
        $data['name'],
        $data['event_date'],
        $data['plugin_key'],
        normalize_tournament_format((string) $data['format']),
        max(1, (int) $data['number_of_fields']),
        'draft',
        json_encode($settings, JSON_THROW_ON_ERROR),
        $now,
        $now,
    ]);
    return (int) db()->lastInsertId();
}

function update_tournament(int $id, array $data): void
{
    $tournament = find_tournament($id);
    $settings = $tournament['settings_array'];
    foreach (['targetScore', 'winPoints', 'lossPoints'] as $key) {
        if (isset($data[$key]) && $data[$key] !== '') {
            $settings[$key] = max(0, (int) $data[$key]);
        }
    }

    db()->prepare('UPDATE tournaments SET name = ?, event_date = ?, format = ?, number_of_fields = ?, settings = ?, updated_at = ? WHERE id = ?')
        ->execute([
            trim((string) $data['name']),
            trim((string) $data['event_date']),
            normalize_tournament_format(trim((string) $data['format'])),
            max(1, (int) $data['number_of_fields']),
            json_encode($settings, JSON_THROW_ON_ERROR),
            now_iso(),
            $id,
        ]);
    flash('Configuration du tournoi mise a jour.');
}

function delete_tournament(int $id): void
{
    db()->prepare('DELETE FROM tournaments WHERE id = ?')->execute([$id]);
    flash('Tournoi supprime.');
}

function participants(int $tournamentId): array
{
    $stmt = db()->prepare('SELECT * FROM participants WHERE tournament_id = ? ORDER BY id ASC');
    $stmt->execute([$tournamentId]);
    return $stmt->fetchAll();
}

function add_participant(int $tournamentId, array $data): void
{
    $stmt = db()->prepare('INSERT INTO participants (tournament_id, name, type, players, color, emoji, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([$tournamentId, $data['name'], $data['type'], $data['players'], $data['color'], $data['emoji'], now_iso()]);
}

function update_participant(int $tournamentId, int $participantId, array $data): void
{
    $stmt = db()->prepare('UPDATE participants SET name = ?, type = ?, players = ?, color = ?, emoji = ? WHERE tournament_id = ? AND id = ?');
    $stmt->execute([
        trim((string) $data['name']),
        $data['type'] === 'player' ? 'player' : 'team',
        trim((string) $data['players']),
        trim((string) $data['color']),
        trim((string) $data['emoji']),
        $tournamentId,
        $participantId,
    ]);
    flash('Participant modifie.');
}

function import_participants(int $tournamentId, string $names): void
{
    $lines = preg_split('/\R+/', $names) ?: [];
    $added = 0;
    foreach ($lines as $line) {
        [$name, $players] = parse_participant_import_line($line);
        if ($name === '') {
            continue;
        }
        add_participant($tournamentId, [
            'name' => $name,
            'type' => 'team',
            'players' => $players,
            'color' => '',
            'emoji' => '',
        ]);
        $added++;
    }
    flash($added . ' participant(s) importe(s).');
}

function generate_random_teams(int $tournamentId, string $playersInput, int $teamSize, string $teamPrefix): void
{
    $players = parse_random_team_players($playersInput);
    $sourcePlayerIds = [];
    if (!$players) {
        $sourcePlayers = array_values(array_filter(
            participants($tournamentId),
            static fn(array $participant): bool => $participant['type'] === 'player'
        ));
        foreach ($sourcePlayers as $sourcePlayer) {
            if (participant_has_matches((int) $sourcePlayer['id'])) {
                flash('Generation annulee : des joueurs existants sont deja utilises dans des matchs.');
                return;
            }
            $sourcePlayerIds[] = (int) $sourcePlayer['id'];
            $players[] = $sourcePlayer['name'];
        }
    }

    $players = array_values(array_filter(array_map('trim', $players), static fn(string $value): bool => $value !== ''));
    if (count($players) < 2) {
        flash('Ajoutez au moins deux joueurs pour creer des equipes aleatoires.');
        return;
    }

    $teamSize = max(1, $teamSize);
    $teamPrefix = trim($teamPrefix) !== '' ? trim($teamPrefix) : 'Equipe';
    shuffle($players);

    $teamCount = max(1, (int) ceil(count($players) / $teamSize));
    if (count($players) % $teamSize === 1 && $teamCount > 1) {
        $teamCount--;
    }

    $teams = array_fill(0, $teamCount, []);
    foreach ($players as $index => $player) {
        $teams[$index % $teamCount][] = $player;
    }

    foreach ($sourcePlayerIds as $sourcePlayerId) {
        db()->prepare('DELETE FROM participants WHERE tournament_id = ? AND id = ?')->execute([$tournamentId, $sourcePlayerId]);
    }

    foreach ($teams as $index => $teamPlayers) {
        add_participant($tournamentId, [
            'name' => $teamPrefix . ' ' . ($index + 1),
            'type' => 'team',
            'players' => implode(PHP_EOL, $teamPlayers),
            'color' => '',
            'emoji' => '',
        ]);
    }

    flash(count($teams) . ' equipe(s) aleatoire(s) creee(s).');
}

function parse_random_team_players(string $playersInput): array
{
    $players = preg_split('/[\r\n;,]+/', $playersInput) ?: [];
    return array_values(array_filter(array_map('trim', $players), static fn(string $value): bool => $value !== ''));
}

function parse_participant_import_line(string $line): array
{
    $parts = preg_split('/\s*[;:]\s*/', trim($line), 2);
    $name = trim((string) ($parts[0] ?? ''));
    $players = '';
    if (isset($parts[1])) {
        $playersList = preg_split('/\s*[,;|]\s*/', trim($parts[1])) ?: [];
        $players = implode(PHP_EOL, array_values(array_filter(array_map('trim', $playersList), static fn(string $value): bool => $value !== '')));
    }
    return [$name, $players];
}

function delete_participant(int $tournamentId, int $participantId): void
{
    if (participant_has_matches($participantId)) {
        flash('Participant conserve : il est deja utilise dans des matchs. Supprimez ou regenerez le tournoi explicitement si necessaire.');
        return;
    }
    $stmt = db()->prepare('DELETE FROM participants WHERE tournament_id = ? AND id = ?');
    $stmt->execute([$tournamentId, $participantId]);
    flash('Participant supprime.');
}

function participant_has_matches(int $participantId): bool
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM matches WHERE participant_a_id = ? OR participant_b_id = ?');
    $stmt->execute([$participantId, $participantId]);
    return (int) $stmt->fetchColumn() > 0;
}

function match_count(int $tournamentId): int
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM matches WHERE tournament_id = ?');
    $stmt->execute([$tournamentId]);
    return (int) $stmt->fetchColumn();
}

function finished_match_count(int $tournamentId): int
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM matches WHERE tournament_id = ? AND status = ?');
    $stmt->execute([$tournamentId, 'finished']);
    return (int) $stmt->fetchColumn();
}

function pools(int $tournamentId): array
{
    $stmt = db()->prepare('SELECT * FROM pools WHERE tournament_id = ? ORDER BY sort_order ASC');
    $stmt->execute([$tournamentId]);
    return $stmt->fetchAll();
}

function pool_participants(int $poolId): array
{
    $stmt = db()->prepare('SELECT p.* FROM participants p JOIN pool_participants pp ON pp.participant_id = p.id WHERE pp.pool_id = ? ORDER BY pp.id ASC');
    $stmt->execute([$poolId]);
    return $stmt->fetchAll();
}

function generate_pools(int $tournamentId, bool $force = false): void
{
    $items = participants($tournamentId);
    if (count($items) < 3) {
        flash('Ajoutez au moins 3 participants avant de generer les poules.');
        return;
    }
    if (!$force && (pools($tournamentId) || match_count($tournamentId) > 0)) {
        flash('Generation annulee : des poules ou matchs existent deja. Cochez la confirmation pour regenerer.');
        return;
    }
    if (!$force && finished_match_count($tournamentId) > 0) {
        flash('Generation annulee : des scores sont deja saisis.');
        return;
    }

    $pdo = db();
    $pdo->beginTransaction();
    $pdo->prepare('DELETE FROM matches WHERE tournament_id = ?')->execute([$tournamentId]);
    $pdo->prepare('DELETE FROM pools WHERE tournament_id = ?')->execute([$tournamentId]);

    $tournament = find_tournament($tournamentId);
    $count = count($items);
    $poolCount = $tournament['format'] === 'round_robin' ? 1 : max(1, (int) round($count / 4));
    while ($poolCount > 1 && floor($count / $poolCount) < 3) {
        $poolCount--;
    }

    $poolIds = [];
    for ($i = 0; $i < $poolCount; $i++) {
        $name = $tournament['format'] === 'round_robin' ? 'Championnat' : 'Poule ' . chr(65 + $i);
        $pdo->prepare('INSERT INTO pools (tournament_id, name, sort_order) VALUES (?, ?, ?)')->execute([$tournamentId, $name, $i + 1]);
        $poolIds[] = (int) $pdo->lastInsertId();
    }

    foreach ($items as $index => $participant) {
        $poolId = $poolIds[$index % $poolCount];
        $pdo->prepare('INSERT INTO pool_participants (pool_id, participant_id) VALUES (?, ?)')->execute([$poolId, $participant['id']]);
    }

    $pdo->prepare('UPDATE tournaments SET status = ?, updated_at = ? WHERE id = ?')->execute(['configured', now_iso(), $tournamentId]);
    $pdo->commit();
    flash('Poules generees.');
}

function generate_matches(int $tournamentId, bool $force = false): void
{
    $tournament = find_tournament($tournamentId);
    $allPools = pools($tournamentId);
    if (!$allPools) {
        flash('Generez les poules avant les matchs.');
        return;
    }
    if (!$force && match_count($tournamentId) > 0) {
        flash('Generation annulee : des matchs existent deja. Cochez la confirmation pour regenerer.');
        return;
    }
    if (!$force && finished_match_count($tournamentId) > 0) {
        flash('Generation annulee : des scores sont deja saisis.');
        return;
    }

    $pdo = db();
    $pdo->beginTransaction();
    $pdo->prepare('DELETE FROM matches WHERE tournament_id = ?')->execute([$tournamentId]);
    $order = 1;
    $fieldCount = max(1, (int) $tournament['number_of_fields']);
    $matchesByPool = [];
    foreach ($allPools as $pool) {
        $items = pool_participants((int) $pool['id']);
        $poolMatches = [];
        for ($i = 0; $i < count($items); $i++) {
            for ($j = $i + 1; $j < count($items); $j++) {
                $poolMatches[] = [(int) $items[$i]['id'], (int) $items[$j]['id']];
            }
        }
        $matchesByPool[] = ['pool' => $pool, 'matches' => $poolMatches];
    }

    $round = 0;
    do {
        $inserted = false;
        foreach ($matchesByPool as $poolSchedule) {
            if (!isset($poolSchedule['matches'][$round])) {
                continue;
            }
            [$participantAId, $participantBId] = $poolSchedule['matches'][$round];
            insert_match($pdo, $tournamentId, (int) $poolSchedule['pool']['id'], 'pool', $poolSchedule['pool']['name'], (($order - 1) % $fieldCount) + 1, $participantAId, $participantBId, $order);
            $order++;
            $inserted = true;
        }
        $round++;
    } while ($inserted);

    $pdo->prepare('UPDATE tournaments SET status = ?, updated_at = ? WHERE id = ?')->execute(['running', now_iso(), $tournamentId]);
    $pdo->commit();
    flash('Matchs generes.');
}

function insert_match(PDO $pdo, int $tournamentId, ?int $poolId, string $phase, string $round, int $fieldNumber, int $participantAId, int $participantBId, int $order): void
{
    $pdo->prepare('INSERT INTO matches (tournament_id, pool_id, phase, round, field_number, participant_a_id, participant_b_id, status, scheduled_order, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
        ->execute([$tournamentId, $poolId, $phase, $round, $fieldNumber, $participantAId, $participantBId, 'scheduled', $order, now_iso(), now_iso()]);
}

function generate_final_matches(int $tournamentId, bool $useFlash = true): ?string
{
    $tournament = find_tournament($tournamentId);
    if ($tournament['format'] !== 'pools_finals') {
        if ($useFlash) {
            flash('Les phases finales sont disponibles uniquement pour le format Poules + phases finales.');
        }
        return null;
    }

    $matches = matches_for_tournament($tournamentId);
    if (!$matches) {
        if ($useFlash) {
            flash('Generez les matchs de poule avant les phases finales.');
        }
        return null;
    }

    $poolMatches = array_filter($matches, static fn(array $match): bool => $match['phase'] === 'pool');
    $unfinishedPoolMatches = array_filter($poolMatches, static fn(array $match): bool => $match['status'] !== 'finished');
    if ($unfinishedPoolMatches) {
        if ($useFlash) {
            flash('Terminez tous les matchs de poule avant de generer les phases finales.');
        }
        return null;
    }

    $finalMatches = array_values(array_filter($matches, static fn(array $match): bool => $match['phase'] === 'final'));
    $semiFinals = array_values(array_filter($finalMatches, static fn(array $match): bool => $match['round'] === 'Demi-finale'));
    if (!$semiFinals) {
        return generate_semi_finals($tournamentId, $matches, $useFlash);
    }

    $finalRoundMatches = array_values(array_filter($finalMatches, static fn(array $match): bool => in_array($match['round'], ['Finale', 'Petite finale'], true)));
    if ($finalRoundMatches) {
        if ($useFlash) {
            flash('Les finales sont deja generees.');
        }
        return null;
    }

    $unfinishedSemiFinals = array_filter($semiFinals, static fn(array $match): bool => $match['status'] !== 'finished');
    if ($unfinishedSemiFinals) {
        if ($useFlash) {
            flash('Terminez les demi-finales avant de generer la finale.');
        }
        return null;
    }

    return generate_final_and_third_place($tournamentId, $matches, $semiFinals, $useFlash);
}

function generate_semi_finals(int $tournamentId, array $matches, bool $useFlash = true): ?string
{
    $pools = pools($tournamentId);
    $qualified = final_qualified_teams($tournamentId, $pools);
    if (count($qualified) < 4) {
        if ($useFlash) {
            flash('Les demi-finales necessitent quatre qualifies.');
        }
        return null;
    }

    $nextOrder = next_match_order($matches);
    $fieldCount = max(1, (int) find_tournament($tournamentId)['number_of_fields']);
    $pdo = db();
    insert_match($pdo, $tournamentId, null, 'final', 'Demi-finale', (($nextOrder - 1) % $fieldCount) + 1, (int) $qualified[0]['participant_id'], (int) $qualified[3]['participant_id'], $nextOrder);
    insert_match($pdo, $tournamentId, null, 'final', 'Demi-finale', ($nextOrder % $fieldCount) + 1, (int) $qualified[1]['participant_id'], (int) $qualified[2]['participant_id'], $nextOrder + 1);
    $message = 'Demi-finales generees.';
    if ($useFlash) {
        flash($message);
    }
    return $message;
}

function generate_final_and_third_place(int $tournamentId, array $matches, array $semiFinals, bool $useFlash = true): string
{
    $winners = [];
    $losers = [];
    foreach ($semiFinals as $match) {
        $winnerId = (int) $match['winner_participant_id'];
        $winners[] = $winnerId;
        $losers[] = $winnerId === (int) $match['participant_a_id'] ? (int) $match['participant_b_id'] : (int) $match['participant_a_id'];
    }

    $nextOrder = next_match_order($matches);
    $fieldCount = max(1, (int) find_tournament($tournamentId)['number_of_fields']);
    $pdo = db();
    insert_match($pdo, $tournamentId, null, 'final', 'Finale', (($nextOrder - 1) % $fieldCount) + 1, $winners[0], $winners[1], $nextOrder);
    insert_match($pdo, $tournamentId, null, 'final', 'Petite finale', ($nextOrder % $fieldCount) + 1, $losers[0], $losers[1], $nextOrder + 1);
    $message = 'Finale et petite finale generees.';
    if ($useFlash) {
        flash($message);
    }
    return $message;
}

function next_match_order(array $matches): int
{
    $orders = array_map(static fn(array $match): int => (int) $match['scheduled_order'], $matches);
    return $orders ? max($orders) + 1 : 1;
}

function matches_for_tournament(int $tournamentId): array
{
    $stmt = db()->prepare(<<<'SQL'
SELECT m.*,
    pa.name AS participant_a_name,
    pa.players AS participant_a_players,
    pb.name AS participant_b_name,
    pb.players AS participant_b_players,
    p.name AS pool_name
FROM matches m
JOIN participants pa ON pa.id = m.participant_a_id
JOIN participants pb ON pb.id = m.participant_b_id
LEFT JOIN pools p ON p.id = m.pool_id
WHERE m.tournament_id = ?
ORDER BY m.scheduled_order ASC
SQL);
    $stmt->execute([$tournamentId]);
    return $stmt->fetchAll();
}

function public_tournament_summary(int $tournamentId): array
{
    $tournament = find_tournament($tournamentId);
    $matches = matches_for_tournament($tournamentId);
    $participants = participants($tournamentId);
    $pools = pools($tournamentId);
    $finished = array_values(array_filter($matches, static fn(array $m): bool => $m['status'] === 'finished'));
    $remaining = array_values(array_filter($matches, static fn(array $m): bool => $m['status'] !== 'finished'));
    $total = expected_match_count($tournament, $matches);
    $generatedTotal = count($matches);
    $progress = $total > 0 ? (int) round((count($finished) / $total) * 100) : 0;
    $fieldNumbers = array_unique(array_map(static fn(array $m): int => (int) $m['field_number'], $matches));
    sort($fieldNumbers);
    $fieldsCount = count($fieldNumbers) > 0 ? count($fieldNumbers) : max(1, (int) $tournament['number_of_fields']);

    $topStanding = standings($tournamentId)[0] ?? null;
    $standings = standings($tournamentId);
    $lastResults = array_slice(array_reverse($finished), 0, 5);
    $qualifiedTeams = public_qualified_teams($tournamentId, $tournament, $matches, $pools);
    $isComplete = $total > 0 && count($finished) >= $total;
    $finalRanking = $isComplete ? public_final_ranking($tournamentId, $matches, $standings) : ['podium' => [], 'remaining' => []];
    $closestMatch = null;
    foreach ($finished as $match) {
        $diff = abs((int) $match['score_a'] - (int) $match['score_b']);
        if ($closestMatch === null || $diff < $closestMatch['diff']) {
            $closestMatch = ['diff' => $diff, 'match' => $match];
        }
    }

    $leaderLabel = $topStanding ? participant_text_label($topStanding['participant'], $topStanding['players'] ?? '') . ' (' . $topStanding['ranking_points'] . ' pts)' : 'Aucun';
    $closestLabel = 'Aucun';
    if ($closestMatch) {
        $m = $closestMatch['match'];
        $closestLabel = participant_text_label($m['participant_a_name'], $m['participant_a_players'] ?? '') . ' ' . $m['score_a'] . '-' . $m['score_b'] . ' ' . participant_text_label($m['participant_b_name'], $m['participant_b_players'] ?? '');
    }

    return [
        'tournament' => $tournament,
        'participants_count' => count($participants),
        'pools_count' => count($pools),
        'fields_count' => $fieldsCount,
        'total_matches' => $total,
        'generated_matches' => $generatedTotal,
        'finished_matches' => count($finished),
        'remaining_matches' => max(0, $total - count($finished)),
        'scheduled_remaining_matches' => count($remaining),
        'progress' => $progress,
        'leader_label' => $leaderLabel,
        'closest_match_label' => $closestLabel,
        'next_matches' => array_slice($remaining, 0, 8),
        'last_results' => $lastResults,
        'qualified_teams' => $qualifiedTeams,
        'final_bracket' => final_bracket($matches),
        'is_complete' => $isComplete,
        'podium' => $finalRanking['podium'],
        'final_standings' => $finalRanking['remaining'],
    ];
}

function participant_text_label(string $name, ?string $players = ''): string
{
    $players = trim((string) $players);
    if ($players === '') {
        return $name;
    }
    return $name . ' (' . (preg_replace('/\s*\R+\s*/', ', ', $players) ?? $players) . ')';
}

function public_final_ranking(int $tournamentId, array $matches, array $standings): array
{
    $podium = final_podium_from_bracket($matches);
    if (!$podium) {
        $podium = array_slice($standings, 0, 3);
    }

    $podiumIds = array_map(static fn(array $row): int => (int) $row['participant_id'], $podium);
    $remaining = array_values(array_filter($standings, static fn(array $row): bool => !in_array((int) $row['participant_id'], $podiumIds, true)));

    return ['podium' => array_values($podium), 'remaining' => $remaining];
}

function final_podium_from_bracket(array $matches): array
{
    $final = null;
    $thirdPlace = null;
    foreach ($matches as $match) {
        if ($match['phase'] === 'final' && $match['round'] === 'Finale' && $match['status'] === 'finished') {
            $final = $match;
        }
        if ($match['phase'] === 'final' && $match['round'] === 'Petite finale' && $match['status'] === 'finished') {
            $thirdPlace = $match;
        }
    }
    if (!$final) {
        return [];
    }

    $winnerId = (int) $final['winner_participant_id'];
    $secondId = $winnerId === (int) $final['participant_a_id'] ? (int) $final['participant_b_id'] : (int) $final['participant_a_id'];
    $podium = [
        podium_row_from_match($final, $winnerId),
        podium_row_from_match($final, $secondId),
    ];

    if ($thirdPlace) {
        $podium[] = podium_row_from_match($thirdPlace, (int) $thirdPlace['winner_participant_id']);
    }

    return $podium;
}

function podium_row_from_match(array $match, int $participantId): array
{
    $isParticipantA = $participantId === (int) $match['participant_a_id'];
    return [
        'participant_id' => $participantId,
        'participant' => $isParticipantA ? $match['participant_a_name'] : $match['participant_b_name'],
        'players' => $isParticipantA ? $match['participant_a_players'] : $match['participant_b_players'],
        'ranking_points' => 0,
        'played' => 0,
        'wins' => 0,
        'losses' => 0,
        'scored' => 0,
        'conceded' => 0,
        'diff' => 0,
    ];
}

function final_bracket(array $matches): array
{
    $roundLabels = ['Quart de finale', 'Demi-finale', 'Finale', 'Petite finale'];
    $bracket = [];
    foreach ($roundLabels as $round) {
        $roundMatches = array_values(array_filter($matches, static fn(array $match): bool => $match['phase'] === 'final' && $match['round'] === $round));
        if ($roundMatches) {
            $bracket[] = ['round' => $round, 'matches' => $roundMatches];
        }
    }
    return $bracket;
}

function expected_match_count(array $tournament, array $matches): int
{
    $generatedCount = count($matches);
    if ($tournament['format'] !== 'pools_finals') {
        return $generatedCount;
    }

    $poolCount = count(array_filter($matches, static fn(array $match): bool => $match['phase'] === 'pool'));
    if ($poolCount === 0) {
        return $generatedCount;
    }

    $expectedFinalCount = 4; // 2 demi-finales, 1 finale, 1 petite finale.
    $generatedFinalCount = count(array_filter($matches, static fn(array $match): bool => $match['phase'] === 'final'));

    return $poolCount + max($expectedFinalCount, $generatedFinalCount);
}

function public_qualified_teams(int $tournamentId, array $tournament, array $matches, array $pools): array
{
    if ($tournament['format'] !== 'pools_finals') {
        return [];
    }

    $poolMatches = array_values(array_filter($matches, static fn(array $match): bool => $match['phase'] === 'pool'));
    $finalMatches = array_values(array_filter($matches, static fn(array $match): bool => $match['phase'] === 'final'));
    if (!$poolMatches || $finalMatches) {
        return [];
    }

    $unfinishedPoolMatches = array_filter($poolMatches, static fn(array $match): bool => $match['status'] !== 'finished');
    if ($unfinishedPoolMatches) {
        return [];
    }

    return final_qualified_teams($tournamentId, $pools);
}

function final_qualified_teams(int $tournamentId, array $pools, int $limit = 4): array
{
    $candidatesByRank = [];
    foreach ($pools as $pool) {
        foreach (array_slice(standings($tournamentId, (int) $pool['id']), 0, 2) as $rank => $row) {
            $row['pool_id'] = (int) $pool['id'];
            $row['pool_name'] = $pool['name'];
            $row['rank'] = $rank + 1;
            $row['points'] = (int) $row['ranking_points'];
            $candidatesByRank[$rank + 1][] = $row;
        }
    }

    ksort($candidatesByRank);
    $qualified = [];
    foreach ($candidatesByRank as $rows) {
        usort($rows, 'compare_final_seed');
        foreach ($rows as $row) {
            $qualified[] = $row;
            if (count($qualified) >= $limit) {
                return $qualified;
            }
        }
    }
    return $qualified;
}

function compare_final_seed(array $a, array $b): int
{
    foreach (['ranking_points', 'wins', 'diff', 'scored'] as $key) {
        $comparison = (int) $b[$key] <=> (int) $a[$key];
        if ($comparison !== 0) {
            return $comparison;
        }
    }
    return strnatcasecmp((string) $a['participant'], (string) $b['participant']);
}

function save_score(int $tournamentId, int $matchId, int $scoreA, int $scoreB, bool $useFlash = true): array
{
    $tournament = find_tournament($tournamentId);
    $selectedPlugin = plugin($tournament['plugin_key']);
    $stmt = db()->prepare('SELECT * FROM matches WHERE tournament_id = ? AND id = ?');
    $stmt->execute([$tournamentId, $matchId]);
    $match = $stmt->fetch();
    if (!$match) {
        $message = 'Match introuvable.';
        if ($useFlash) {
            flash($message);
        }
        return ['ok' => false, 'message' => $message];
    }

    [$valid, $message] = $selectedPlugin['validator']($scoreA, $scoreB, $tournament['settings_array']);
    if (!$valid) {
        if ($useFlash) {
            flash($message);
        }
        return ['ok' => false, 'message' => $message];
    }

    $winner = $selectedPlugin['winner']($match, $scoreA, $scoreB, $tournament['settings_array']);
    db()->prepare('UPDATE matches SET score_a = ?, score_b = ?, draft_score_a = NULL, draft_score_b = NULL, winner_participant_id = ?, status = ?, updated_at = ? WHERE id = ?')
        ->execute([$scoreA, $scoreB, $winner, 'finished', now_iso(), $matchId]);
    $message = 'Score valide et publie.';
    $generatedMessage = generate_final_matches($tournamentId, false);
    if ($generatedMessage) {
        $message .= ' ' . $generatedMessage;
    }
    if ($useFlash) {
        flash($message);
    }
    return ['ok' => true, 'message' => $message];
}

function save_score_draft(int $tournamentId, int $matchId, int $scoreA, int $scoreB): array
{
    $stmt = db()->prepare('SELECT id FROM matches WHERE tournament_id = ? AND id = ?');
    $stmt->execute([$tournamentId, $matchId]);
    if (!$stmt->fetch()) {
        return ['ok' => false, 'message' => 'Match introuvable.'];
    }

    db()->prepare('UPDATE matches SET draft_score_a = ?, draft_score_b = ?, updated_at = ? WHERE tournament_id = ? AND id = ?')
        ->execute([$scoreA, $scoreB, now_iso(), $tournamentId, $matchId]);

    return ['ok' => true, 'message' => 'Brouillon enregistre.'];
}

function clear_score(int $tournamentId, int $matchId): void
{
    db()->prepare('UPDATE matches SET score_a = NULL, score_b = NULL, draft_score_a = NULL, draft_score_b = NULL, winner_participant_id = NULL, status = ?, updated_at = ? WHERE tournament_id = ? AND id = ?')
        ->execute(['scheduled', now_iso(), $tournamentId, $matchId]);
    flash('Score efface.');
}

function standings(int $tournamentId, ?int $poolId = null): array
{
    $tournament = find_tournament($tournamentId);
    $settings = $tournament['settings_array'];
    $winPoints = (int) ($settings['winPoints'] ?? 3);
    $lossPoints = (int) ($settings['lossPoints'] ?? 0);
    $items = $poolId ? pool_participants($poolId) : participants($tournamentId);
    $rows = [];
    foreach ($items as $participant) {
        $rows[(int) $participant['id']] = [
            'participant_id' => (int) $participant['id'],
            'participant' => $participant['name'],
            'players' => $participant['players'],
            'played' => 0,
            'wins' => 0,
            'losses' => 0,
            'ranking_points' => 0,
            'scored' => 0,
            'conceded' => 0,
            'diff' => 0,
        ];
    }

    $sql = 'SELECT * FROM matches WHERE tournament_id = ? AND status = ?';
    $params = [$tournamentId, 'finished'];
    if ($poolId) {
        $sql .= ' AND pool_id = ?';
        $params[] = $poolId;
    }
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    foreach ($stmt->fetchAll() as $match) {
        $a = (int) $match['participant_a_id'];
        $b = (int) $match['participant_b_id'];
        if (!isset($rows[$a], $rows[$b])) {
            continue;
        }
        $scoreA = (int) $match['score_a'];
        $scoreB = (int) $match['score_b'];
        $winner = (int) $match['winner_participant_id'];
        foreach ([$a, $b] as $id) {
            $rows[$id]['played']++;
        }
        $rows[$a]['scored'] += $scoreA;
        $rows[$a]['conceded'] += $scoreB;
        $rows[$b]['scored'] += $scoreB;
        $rows[$b]['conceded'] += $scoreA;
        $loser = $winner === $a ? $b : $a;
        $rows[$winner]['wins']++;
        $rows[$winner]['ranking_points'] += $winPoints;
        $rows[$loser]['losses']++;
        $rows[$loser]['ranking_points'] += $lossPoints;
    }

    foreach ($rows as &$row) {
        $row['diff'] = $row['scored'] - $row['conceded'];
    }
    unset($row);

    usort($rows, static fn(array $a, array $b): int => [$b['ranking_points'], $b['wins'], $b['diff'], $b['scored'], $a['participant']] <=> [$a['ranking_points'], $a['wins'], $a['diff'], $a['scored'], $b['participant']]);
    return array_values($rows);
}

function export_csv(string $filename, array $headers, array $rows): never
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fputcsv($out, $headers, ';');
    foreach ($rows as $row) {
        fputcsv($out, $row, ';');
    }
    exit;
}
