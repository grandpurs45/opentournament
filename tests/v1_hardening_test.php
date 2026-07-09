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

$rules = plugin_public_rules('molkky', find_tournament($tournamentId)['settings_array']);
assert_true(str_contains(implode(' ', $rules), '50'), 'Public rules should include configured target score.');

generate_matches($tournamentId);
$generatedMatches = matches_for_tournament($tournamentId);
assert_true(count($generatedMatches) === 12, '2 pools of 4 should generate 12 matches.');
assert_true($generatedMatches[0]['pool_name'] !== $generatedMatches[1]['pool_name'], 'Match planning should alternate pools.');
ob_start();
fields_view($tournamentId);
$fieldsHtml = ob_get_clean();
assert_true(str_contains($fieldsHtml, 'Terrain 1') && str_contains($fieldsHtml, 'Terrain 2'), 'Fields view should render field cards.');

$firstMatch = $generatedMatches[0];
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

$roundRobinId = create_tournament([
    'name' => 'Round Robin Test',
    'event_date' => '2026-07-08',
    'plugin_key' => 'generic',
    'format' => 'round_robin',
    'number_of_fields' => 2,
]);
import_participants($roundRobinId, implode(PHP_EOL, ['A', 'B', 'C', 'D', 'E']));
generate_pools($roundRobinId);
assert_true(count(pools($roundRobinId)) === 1, 'Round-robin format should generate one pool.');
generate_matches($roundRobinId);
assert_true(count(matches_for_tournament($roundRobinId)) === 10, '5 participants should generate 10 round-robin matches.');

$finalsId = create_tournament([
    'name' => 'Finals Test',
    'event_date' => '2026-07-08',
    'plugin_key' => 'generic',
    'format' => 'pools_finals',
    'number_of_fields' => 2,
]);
import_participants($finalsId, implode(PHP_EOL, ['F1', 'F2', 'F3', 'F4', 'F5', 'F6', 'F7', 'F8']));
generate_pools($finalsId);
generate_matches($finalsId);
foreach (matches_for_tournament($finalsId) as $match) {
    save_score($finalsId, (int) $match['id'], 10, 5);
}
$qualifiedSummary = public_tournament_summary($finalsId);
assert_true(count($qualifiedSummary['qualified_teams']) === 4, 'Qualified teams should be exposed after pool matches are finished.');
assert_true($qualifiedSummary['total_matches'] === 16, 'Pools + finals summary should include expected final-stage matches before generation.');
assert_true($qualifiedSummary['remaining_matches'] === 4, 'Pools + finals summary should count ungenerated final-stage matches as remaining.');
assert_true($qualifiedSummary['progress'] === 75, 'Pools + finals progress should use expected final-stage matches.');

generate_final_matches($finalsId);
$qualifiedSummaryAfterSemis = public_tournament_summary($finalsId);
assert_true($qualifiedSummaryAfterSemis['qualified_teams'] === [], 'Qualified teams panel should disappear after semi-finals are generated.');
assert_true(count($qualifiedSummaryAfterSemis['final_bracket']) === 1 && $qualifiedSummaryAfterSemis['final_bracket'][0]['round'] === 'Demi-finale', 'Final bracket should expose generated semi-finals.');

$finalsMatches = matches_for_tournament($finalsId);
$semiFinals = array_values(array_filter($finalsMatches, static fn(array $match): bool => $match['round'] === 'Demi-finale'));
assert_true(count($semiFinals) === 2, 'Pools + finals should generate two semi-finals.');
foreach ($semiFinals as $match) {
    save_score($finalsId, (int) $match['id'], 10, 5);
}
generate_final_matches($finalsId);
$finalRoundMatches = array_values(array_filter(matches_for_tournament($finalsId), static fn(array $match): bool => in_array($match['round'], ['Finale', 'Petite finale'], true)));
assert_true(count($finalRoundMatches) === 2, 'Pools + finals should generate final and third-place match.');
foreach ($finalRoundMatches as $match) {
    save_score($finalsId, (int) $match['id'], 10, 5);
}
$finishedSummary = public_tournament_summary($finalsId);
assert_true($finishedSummary['is_complete'] === true, 'Tournament should be complete when all expected matches are finished.');
assert_true(count($finishedSummary['podium']) === 3, 'Completed tournament should expose a podium.');
assert_true(count($finishedSummary['final_standings']) === 5, 'Completed tournament should expose standings after the podium.');

echo 'OK' . PHP_EOL;
