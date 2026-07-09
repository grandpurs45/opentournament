<?php

declare(strict_types=1);

$dbPath = dirname(__DIR__) . '/data/test-opentournament.sqlite';
@unlink($dbPath);
putenv('SQLITE_PATH=' . $dbPath);
putenv('APP_URL=http://opentournament.local');

require dirname(__DIR__) . '/app/bootstrap.php';

function assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: " . $message . PHP_EOL);
        exit(1);
    }
}

$tournamentId = create_tournament([
    'name' => 'Hardening Test',
    'event_date' => '2026-07-08',
    'plugin_key' => 'molkky',
    'format' => 'pools',
    'number_of_fields' => 2,
]);

import_participants($tournamentId, implode(PHP_EOL, [
    'Equipe 1; Alice; Bob',
    'Equipe 2',
    'Equipe 3',
    'Equipe 4',
    'Equipe 5',
    'Equipe 6',
    'Equipe 7',
    'Equipe 8',
]));

$imported = participants($tournamentId);
assert_true($imported[0]['players'] === 'Alice' . PHP_EOL . 'Bob', 'Bulk import should parse player first names.');

generate_pools($tournamentId);
assert_true(count(pools($tournamentId)) === 2, '8 teams should generate 2 pools.');

generate_matches($tournamentId);
assert_true(count(matches_for_tournament($tournamentId)) === 12, '2 pools of 4 should generate 12 matches.');

$firstMatch = matches_for_tournament($tournamentId)[0];
save_score_draft($tournamentId, (int) $firstMatch['id'], 50, 37);
$draftMatch = matches_for_tournament($tournamentId)[0];
assert_true((int) $draftMatch['draft_score_a'] === 50 && (int) $draftMatch['draft_score_b'] === 37, 'Draft score should be persisted.');
assert_true(finished_match_count($tournamentId) === 0, 'Draft score should not finish the match.');

$summary = public_tournament_summary($tournamentId);
assert_true($summary['finished_matches'] === 0, 'Public summary should ignore draft scores.');
assert_true($summary['remaining_matches'] === 12, 'Draft score should stay private until validation.');

save_score($tournamentId, (int) $firstMatch['id'], 50, 37);
assert_true(finished_match_count($tournamentId) === 1, 'A valid Molkky score should finish the match.');

$summary = public_tournament_summary($tournamentId);
assert_true($summary['finished_matches'] === 1, 'Public summary should count finished matches.');
assert_true($summary['remaining_matches'] === 11, 'Public summary should count remaining matches.');
assert_true($summary['progress'] === 8, 'Public summary should expose rounded progress.');

generate_pools($tournamentId);
assert_true(count(matches_for_tournament($tournamentId)) === 12, 'Regeneration without force should preserve existing matches.');
assert_true(finished_match_count($tournamentId) === 1, 'Regeneration without force should preserve finished scores.');

clear_score($tournamentId, (int) $firstMatch['id']);
assert_true(finished_match_count($tournamentId) === 0, 'Score clearing should reopen the match.');

$svg = qr_svg(app_url('/t/' . $tournamentId));
assert_true(str_starts_with($svg, '<svg'), 'QR generator should return SVG.');

echo 'OK' . PHP_EOL;
