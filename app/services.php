<?php

declare(strict_types=1);

function all_tournaments(): array
{
    return db()->query('SELECT * FROM tournaments ORDER BY created_at DESC')->fetchAll();
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
        $data['format'],
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
            trim((string) $data['format']),
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

    $count = count($items);
    $poolCount = max(1, (int) round($count / 4));
    while ($poolCount > 1 && floor($count / $poolCount) < 3) {
        $poolCount--;
    }

    $poolIds = [];
    for ($i = 0; $i < $poolCount; $i++) {
        $name = 'Poule ' . chr(65 + $i);
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
    foreach ($allPools as $pool) {
        $items = pool_participants((int) $pool['id']);
        for ($i = 0; $i < count($items); $i++) {
            for ($j = $i + 1; $j < count($items); $j++) {
                $pdo->prepare('INSERT INTO matches (tournament_id, pool_id, phase, round, field_number, participant_a_id, participant_b_id, status, scheduled_order, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
                    ->execute([$tournamentId, $pool['id'], 'pool', $pool['name'], (($order - 1) % $fieldCount) + 1, $items[$i]['id'], $items[$j]['id'], 'scheduled', $order, now_iso(), now_iso()]);
                $order++;
            }
        }
    }
    $pdo->prepare('UPDATE tournaments SET status = ?, updated_at = ? WHERE id = ?')->execute(['running', now_iso(), $tournamentId]);
    $pdo->commit();
    flash('Matchs generes.');
}

function matches_for_tournament(int $tournamentId): array
{
    $stmt = db()->prepare(<<<'SQL'
SELECT m.*, pa.name AS participant_a_name, pb.name AS participant_b_name, p.name AS pool_name
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
    $total = count($matches);
    $progress = $total > 0 ? (int) round((count($finished) / $total) * 100) : 0;
    $fieldNumbers = array_unique(array_map(static fn(array $m): int => (int) $m['field_number'], $matches));
    sort($fieldNumbers);
    $fieldsCount = count($fieldNumbers) > 0 ? count($fieldNumbers) : max(1, (int) $tournament['number_of_fields']);

    $topStanding = standings($tournamentId)[0] ?? null;
    $lastResults = array_slice(array_reverse($finished), 0, 5);
    $closestMatch = null;
    foreach ($finished as $match) {
        $diff = abs((int) $match['score_a'] - (int) $match['score_b']);
        if ($closestMatch === null || $diff < $closestMatch['diff']) {
            $closestMatch = ['diff' => $diff, 'match' => $match];
        }
    }

    $leaderLabel = $topStanding ? $topStanding['participant'] . ' (' . $topStanding['ranking_points'] . ' pts)' : 'Aucun';
    $closestLabel = 'Aucun';
    if ($closestMatch) {
        $m = $closestMatch['match'];
        $closestLabel = $m['participant_a_name'] . ' ' . $m['score_a'] . '-' . $m['score_b'] . ' ' . $m['participant_b_name'];
    }

    return [
        'tournament' => $tournament,
        'participants_count' => count($participants),
        'pools_count' => count($pools),
        'fields_count' => $fieldsCount,
        'total_matches' => $total,
        'finished_matches' => count($finished),
        'remaining_matches' => count($remaining),
        'progress' => $progress,
        'leader_label' => $leaderLabel,
        'closest_match_label' => $closestLabel,
        'next_matches' => array_slice($remaining, 0, 8),
        'last_results' => $lastResults,
    ];
}

function save_score(int $tournamentId, int $matchId, int $scoreA, int $scoreB): void
{
    $tournament = find_tournament($tournamentId);
    $selectedPlugin = plugin($tournament['plugin_key']);
    $stmt = db()->prepare('SELECT * FROM matches WHERE tournament_id = ? AND id = ?');
    $stmt->execute([$tournamentId, $matchId]);
    $match = $stmt->fetch();
    if (!$match) {
        flash('Match introuvable.');
        return;
    }

    [$valid, $message] = $selectedPlugin['validator']($scoreA, $scoreB, $tournament['settings_array']);
    if (!$valid) {
        flash($message);
        return;
    }

    $winner = $selectedPlugin['winner']($match, $scoreA, $scoreB, $tournament['settings_array']);
    db()->prepare('UPDATE matches SET score_a = ?, score_b = ?, winner_participant_id = ?, status = ?, updated_at = ? WHERE id = ?')
        ->execute([$scoreA, $scoreB, $winner, 'finished', now_iso(), $matchId]);
    flash('Score enregistre.');
}

function clear_score(int $tournamentId, int $matchId): void
{
    db()->prepare('UPDATE matches SET score_a = NULL, score_b = NULL, winner_participant_id = NULL, status = ?, updated_at = ? WHERE tournament_id = ? AND id = ?')
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
