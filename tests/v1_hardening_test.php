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
assert_true(str_contains(standings_table(standings($tournamentId)), 'Alice, Bob'), 'Standings should display player names under team names.');
update_participant($tournamentId, (int) $imported[0]['id'], [
    'name' => 'Equipe 1 bis',
    'type' => 'team',
    'players' => 'Alice' . PHP_EOL . 'Bruno',
    'color' => '#136f63',
    'emoji' => 'E1',
]);
$updatedParticipant = participants($tournamentId)[0];
assert_true($updatedParticipant['name'] === 'Equipe 1 bis' && $updatedParticipant['players'] === 'Alice' . PHP_EOL . 'Bruno' && $updatedParticipant['color'] === '#136f63', 'Participant update should persist editable fields.');

ob_start();
participants_view($tournamentId);
$participantsHtml = ob_get_clean();
assert_true(str_contains($participantsHtml, 'color-palette') && str_contains($participantsHtml, 'data-color="#2f80ed"'), 'Participants view should render a team color palette.');
assert_true(str_contains($participantsHtml, 'participant-edit') && str_contains($participantsHtml, 'Equipe 1 bis'), 'Participants view should render editable participant forms.');
assert_true(str_contains($participantsHtml, 'Equipes aleatoires'), 'Participants view should render random team generation form.');

$randomTeamsId = create_tournament([
    'name' => 'Random Teams Test',
    'event_date' => '2026-07-08',
    'plugin_key' => 'generic',
    'format' => 'pools',
    'number_of_fields' => 1,
]);
generate_random_teams($randomTeamsId, implode(PHP_EOL, ['Alice', 'Bob', 'Chloe', 'David', 'Eve']), 2, 'Duo');
$randomTeams = participants($randomTeamsId);
assert_true(count($randomTeams) === 2, 'Random team generation should avoid a single-player leftover team.');
$randomPlayers = [];
foreach ($randomTeams as $team) {
    array_push($randomPlayers, ...preg_split('/\R+/', $team['players']));
}
sort($randomPlayers);
assert_true($randomPlayers === ['Alice', 'Bob', 'Chloe', 'David', 'Eve'], 'Random team generation should include every player exactly once.');
add_participant($randomTeamsId, ['name' => 'Fred', 'type' => 'player', 'players' => '', 'color' => '', 'emoji' => '']);
add_participant($randomTeamsId, ['name' => 'Gael', 'type' => 'player', 'players' => '', 'color' => '', 'emoji' => '']);
generate_random_teams($randomTeamsId, '', 2, 'Auto');
$convertedParticipants = participants($randomTeamsId);
assert_true(count(array_filter($convertedParticipants, static fn(array $participant): bool => $participant['type'] === 'player')) === 0, 'Random team generation should convert existing player participants without duplicates.');

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
assert_true($qualifiedSummary['qualified_teams'] === [], 'Qualified teams panel should disappear after automatic semi-final generation.');
assert_true(count($qualifiedSummary['final_bracket']) === 1 && $qualifiedSummary['final_bracket'][0]['round'] === 'Demi-finale', 'Final bracket should expose automatically generated semi-finals.');
assert_true($qualifiedSummary['total_matches'] === 16, 'Pools + finals summary should include expected final-stage matches.');
assert_true($qualifiedSummary['remaining_matches'] === 4, 'Pools + finals summary should count remaining final-stage matches.');
assert_true($qualifiedSummary['progress'] === 75, 'Pools + finals progress should use expected final-stage matches.');
ob_start();
display_view($finalsId);
$displayHtml = ob_get_clean();
assert_true(str_contains($displayHtml, 'final-next-bracket') && str_contains($displayHtml, 'next-bracket-match'), 'TV display should render upcoming final matches as a compact bracket.');
assert_true(substr_count($displayHtml, 'Tableau final') === 0, 'TV display should not duplicate the final bracket when upcoming final matches are already shown.');

$finalsMatches = matches_for_tournament($finalsId);
$semiFinals = array_values(array_filter($finalsMatches, static fn(array $match): bool => $match['round'] === 'Demi-finale'));
assert_true(count($semiFinals) === 2, 'Pools + finals should generate two semi-finals.');
foreach ($semiFinals as $match) {
    save_score($finalsId, (int) $match['id'], 10, 5);
}
$finalRoundMatches = array_values(array_filter(matches_for_tournament($finalsId), static fn(array $match): bool => in_array($match['round'], ['Finale', 'Petite finale'], true)));
assert_true(count($finalRoundMatches) === 2, 'Pools + finals should automatically generate final and third-place match.');
foreach ($finalRoundMatches as $match) {
    save_score($finalsId, (int) $match['id'], 10, 5);
}
$finishedSummary = public_tournament_summary($finalsId);
assert_true($finishedSummary['is_complete'] === true, 'Tournament should be complete when all expected matches are finished.');
assert_true(count($finishedSummary['podium']) === 3, 'Completed tournament should expose a podium.');
assert_true(count($finishedSummary['final_standings']) === 5, 'Completed tournament should expose standings after the podium.');
ob_start();
display_view($finalsId);
$finishedDisplayHtml = ob_get_clean();
assert_true(str_contains($finishedDisplayHtml, 'podium-place-1') && str_contains($finishedDisplayHtml, 'podium-place-2') && str_contains($finishedDisplayHtml, 'podium-place-3'), 'Completed TV display should render a visible three-place podium.');
assert_true(str_contains($finishedDisplayHtml, 'podium-rank'), 'Completed TV display should target podium rank styling separately from team labels.');
assert_true(str_contains($finishedDisplayHtml, 'final-standing-cards'), 'Completed TV display should render compact final ranking cards.');

$threePoolsId = create_tournament([
    'name' => 'Three Pools Finals Test',
    'event_date' => '2026-07-08',
    'plugin_key' => 'generic',
    'format' => 'pools_finals',
    'number_of_fields' => 4,
]);
import_participants($threePoolsId, implode(PHP_EOL, ['T1', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'T8', 'T9', 'T10', 'T11', 'T12']));
generate_pools($threePoolsId);
assert_true(count(pools($threePoolsId)) === 3, '12 participants should generate three pools.');
generate_matches($threePoolsId);
foreach (matches_for_tournament($threePoolsId) as $match) {
    save_score($threePoolsId, (int) $match['id'], 10, 5);
}
$threePoolsSummary = public_tournament_summary($threePoolsId);
assert_true($threePoolsSummary['total_matches'] === 22, 'Three pools finals summary should include expected final-stage matches.');
$threePoolQualifiers = final_qualified_teams($threePoolsId, pools($threePoolsId));
assert_true(count($threePoolQualifiers) === 4, 'Three pools finals should select four qualifiers.');
assert_true(in_array('Poule C', array_column($threePoolQualifiers, 'pool_name'), true), 'Temporary qualifiers should include teams from pool C when eligible.');
ob_start();
echo public_pool_standings_panel($threePoolsId, array_merge($threePoolsSummary, ['qualified_teams' => $threePoolQualifiers, 'final_bracket' => []]));
$threePoolsDisplayHtml = ob_get_clean();
assert_true(str_contains($threePoolsDisplayHtml, 'Poule C') && str_contains($threePoolsDisplayHtml, 'Q provisoire'), 'TV display should highlight temporary qualifiers across three pools.');
assert_true(str_contains($threePoolsDisplayHtml, 'Meilleur 2e') && str_contains($threePoolsDisplayHtml, 'pts / diff'), 'TV display should explain best runner-up qualification criteria.');
assert_true(str_contains($threePoolsDisplayHtml, 'compact-standings'), 'TV pool standings should use the compact readable table layout.');
$threePoolsSemiFinals = array_values(array_filter(matches_for_tournament($threePoolsId), static fn(array $match): bool => $match['round'] === 'Demi-finale'));
assert_true(count($threePoolsSemiFinals) === 2, 'Three pools finals should automatically generate two semi-finals from four qualifiers.');

echo 'OK' . PHP_EOL;
