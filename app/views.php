<?php

declare(strict_types=1);

function layout(string $title, string $content, string $class = ''): void
{
    $flash = flash();
    echo '<!doctype html><html lang="fr"><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . h($title) . ' - ' . APP_NAME . '</title>';
    echo '<link rel="stylesheet" href="/public/assets/app.css">';
    echo '</head><body class="' . h($class) . '">';
    echo '<header class="topbar"><a class="brand" href="/">OpenTournament</a><nav><a href="/">Tournois</a><a href="/ROADMAP.md">Roadmap</a></nav></header>';
    if ($flash) {
        echo '<div class="flash">' . h($flash) . '</div>';
    }
    echo '<main>' . $content . '</main>';
    echo '<footer>Version ' . h(app_version()) . ' - Open source tournament management</footer>';
    echo '</body></html>';
}

function dashboard_view(): void
{
    $rows = all_tournaments();
    ob_start();
    echo '<section class="page-head"><div><h1>Tournois</h1><p>Gestion locale des tournois, prete pour XAMPP et Docker.</p></div><a class="button primary" href="/tournaments/create">Creer un tournoi</a></section>';
    echo '<section class="panel"><table><thead><tr><th>Nom</th><th>Date</th><th>Plugin</th><th>Statut</th><th></th></tr></thead><tbody>';
    foreach ($rows as $row) {
        echo '<tr><td>' . h($row['name']) . '</td><td>' . h($row['event_date']) . '</td><td>' . h(plugin($row['plugin_key'])['name']) . '</td><td>' . status_badge($row['status']) . '</td><td><a class="button" href="/admin/' . (int) $row['id'] . '">Ouvrir</a></td></tr>';
    }
    if (!$rows) {
        echo '<tr><td colspan="5" class="empty">Aucun tournoi pour le moment.</td></tr>';
    }
    echo '</tbody></table></section>';
    layout('Tournois', ob_get_clean());
}

function create_tournament_view(): void
{
    ob_start();
    echo '<section class="page-head"><div><h1>Nouveau tournoi</h1><p>Choisissez le jeu et le format initial.</p></div></section>';
    echo '<form class="panel form" method="post"><label>Nom<input required name="name"></label>';
    echo '<label>Date<input required type="date" name="event_date"></label>';
    echo '<label>Plugin<select name="plugin_key">';
    foreach (plugins() as $plugin) {
        echo '<option value="' . h($plugin['key']) . '">' . h($plugin['name']) . ' - ' . h($plugin['description']) . '</option>';
    }
    echo '</select></label><label>Format<select name="format">' . format_options('pools') . '</select></label>';
    echo '<label>Nombre de terrains<input required type="number" min="1" name="number_of_fields"></label>';
    echo '<div class="actions"><button class="button primary" type="submit">Creer</button><a class="button" href="/">Annuler</a></div></form>';
    layout('Nouveau tournoi', ob_get_clean());
}

function format_options(string $selected): string
{
    $html = '';
    foreach (tournament_formats() as $key => $format) {
        $html .= '<option value="' . h($key) . '"' . ($selected === $key ? ' selected' : '') . '>' . h($format['label']) . ' - ' . h($format['description']) . '</option>';
    }
    return $html;
}

function admin_nav(int $id, string $active): string
{
    $items = [
        'overview' => ['/admin/' . $id, 'Synthese'],
        'participants' => ['/admin/' . $id . '/participants', 'Participants'],
        'matches' => ['/admin/' . $id . '/matches', 'Matchs'],
        'fields' => ['/admin/' . $id . '/fields', 'Terrains'],
        'standings' => ['/admin/' . $id . '/standings', 'Classements'],
        'bracket' => ['/admin/' . $id . '/bracket', 'Tableau'],
        'display' => ['/display/' . $id, 'TV'],
        'mobile' => ['/t/' . $id, 'Mobile'],
    ];
    $html = '<nav class="tabs">';
    foreach ($items as $key => [$url, $label]) {
        $target = $key === 'display' ? ' target="_blank" rel="noopener"' : '';
        $html .= '<a class="' . ($key === $active ? 'active' : '') . '" href="' . $url . '"' . $target . '>' . $label . '</a>';
    }
    return $html . '</nav>';
}

function auto_refresh_script(int $seconds = 5): string
{
    $milliseconds = max(1, $seconds) * 1000;
    return '<script>setTimeout(function(){window.location.reload()},' . $milliseconds . ')</script>';
}

function status_badge(string $status): string
{
    $labels = [
        'draft' => 'Brouillon',
        'configured' => 'Configure',
        'running' => 'En cours',
        'scheduled' => 'A saisir',
        'finished' => 'Score saisi',
        'cancelled' => 'Annule',
    ];
    $class = preg_replace('/[^a-z0-9_-]/', '', strtolower($status));
    return '<span class="badge badge-' . h($class ?: 'default') . '">' . h($labels[$status] ?? $status) . '</span>';
}

function score_state_badge(array $match): string
{
    $hasDraft = $match['draft_score_a'] !== null || $match['draft_score_b'] !== null;
    if ($match['status'] === 'finished') {
        if ($hasDraft) {
            return '<span class="badge badge-score-draft">Brouillon modifie</span>';
        }
        return '<span class="badge badge-score-ok">Valide</span>';
    }
    if ($hasDraft) {
        return '<span class="badge badge-score-draft">Brouillon</span>';
    }
    return '<span class="badge badge-score-empty">Vide</span>';
}

function admin_overview_view(int $id): void
{
    $t = find_tournament($id);
    $participants = participants($id);
    $pools = pools($id);
    $matches = matches_for_tournament($id);
    $finished = array_filter($matches, static fn($m) => $m['status'] === 'finished');
    $mobileUrl = app_url('/t/' . $id);
    $settings = $t['settings_array'];
    ob_start();
    echo '<section class="page-head"><div><h1>' . h($t['name']) . '</h1><p>' . h($t['event_date']) . ' - ' . h(plugin($t['plugin_key'])['name']) . ' - ' . h(tournament_format_label($t['format'])) . ' - ' . (int) $t['number_of_fields'] . ' terrain(s)</p></div><div class="actions"><a class="button" href="/display/' . $id . '" target="_blank" rel="noopener">Vue TV</a><a class="button" href="/t/' . $id . '">Vue mobile</a></div></section>';
    echo admin_nav($id, 'overview');
    echo '<section class="stats"><div><strong>' . count($participants) . '</strong><span>Participants</span></div><div><strong>' . count($pools) . '</strong><span>Poules</span></div><div><strong>' . count($matches) . '</strong><span>Matchs</span></div><div><strong>' . count($finished) . '</strong><span>Termines</span></div></section>';
    echo '<section class="panel mobile-link"><div><h2>Acces mobile</h2><p><a href="/t/' . $id . '">' . h($mobileUrl) . '</a></p></div><img class="qr-image" src="/qr/' . $id . '" alt="QR Code acces mobile"></section>';
    echo '<section class="grid two"><form class="panel form" method="post" action="/admin/' . $id . '/settings"><h2>Configuration</h2>';
    echo '<label>Nom<input required name="name" value="' . h($t['name']) . '"></label><label>Date<input required type="date" name="event_date" value="' . h($t['event_date']) . '"></label>';
    echo '<label>Format<select name="format">' . format_options($t['format']) . '</select></label>';
    echo '<label>Nombre de terrains<input required type="number" min="1" name="number_of_fields" value="' . (int) $t['number_of_fields'] . '"></label>';
    echo '<label>Score cible<input type="number" min="0" name="targetScore" value="' . h($settings['targetScore'] ?? '') . '"></label><label>Points victoire<input type="number" min="0" name="winPoints" value="' . h($settings['winPoints'] ?? '') . '"></label><label>Points defaite<input type="number" min="0" name="lossPoints" value="' . h($settings['lossPoints'] ?? '') . '"></label>';
    echo '<button class="button primary">Enregistrer</button></form>';
    echo '<div class="panel flow"><h2>Actions</h2><form class="confirm-action" method="post" action="/admin/' . $id . '/generate-pools"><label><input type="checkbox" name="force" value="1"> Regenerer meme si des poules ou matchs existent</label><button class="button primary">Generer les poules</button></form><form class="confirm-action" method="post" action="/admin/' . $id . '/generate-matches"><label><input type="checkbox" name="force" value="1"> Regenerer meme si des matchs existent</label><button class="button primary">Generer les matchs</button></form>';
    if ($t['format'] === 'pools_finals') {
        echo '<form class="confirm-action" method="post" action="/admin/' . $id . '/generate-finals"><p class="help">Apres les poules terminees : genere les demi-finales. Apres les demi-finales : genere la finale et la petite finale.</p><button class="button primary">Generer les phases finales</button></form>';
    }
    echo '<div class="actions wrap"><a class="button" href="/export/' . $id . '/participants">Export participants</a><a class="button" href="/export/' . $id . '/matches">Export matchs</a><a class="button" href="/export/' . $id . '/standings">Export classements</a></div><form class="confirm-action" method="post" action="/admin/' . $id . '/delete"><label><input required type="checkbox" name="confirm_delete" value="1"> Confirmer la suppression definitive</label><button class="button danger-button">Supprimer le tournoi</button></form></div></section>';
    layout($t['name'], ob_get_clean());
}

function participants_view(int $id): void
{
    $t = find_tournament($id);
    $rows = participants($id);
    ob_start();
    echo '<section class="page-head"><div><h1>Participants</h1><p>' . h($t['name']) . '</p></div></section>' . admin_nav($id, 'participants');
    echo '<section class="grid two"><div class="flow"><form class="panel form" method="post"><h2>Ajouter</h2><label>Nom<input required name="name"></label><label>Type<select name="type"><option value="team">Equipe</option><option value="player">Joueur</option></select></label><label>Joueurs<textarea name="players" rows="3" placeholder="Un nom par ligne"></textarea></label><label>Couleur<input name="color" placeholder="#2f80ed"></label><label>Emoji<input name="emoji" maxlength="8" placeholder="OT"></label><button class="button primary">Ajouter</button></form>';
    echo '<form class="panel form" method="post" action="/admin/' . $id . '/participants/import"><h2>Import rapide</h2><label>Equipes<textarea required name="participants" rows="8" placeholder="Equipe 1; Alice; Bob&#10;Equipe 2; Chloe; David&#10;Equipe 3"></textarea></label><p class="help">Format : une equipe par ligne. Ajoutez les prenoms apres le premier point-virgule.</p><button class="button primary">Importer</button></form></div>';
    echo '<div class="panel"><h2>Liste</h2><table><thead><tr><th>Nom</th><th>Type</th><th>Joueurs</th><th></th></tr></thead><tbody>';
    foreach ($rows as $row) {
        echo '<tr><td>' . h($row['emoji'] ? $row['emoji'] . ' ' . $row['name'] : $row['name']) . '</td><td>' . h($row['type']) . '</td><td>' . nl2br(h($row['players'])) . '</td><td><form method="post" action="/admin/' . $id . '/participants/delete"><input type="hidden" name="participant_id" value="' . (int) $row['id'] . '"><button class="link danger">Supprimer</button></form></td></tr>';
    }
    if (!$rows) {
        echo '<tr><td colspan="4" class="empty">Ajoutez les equipes ou joueurs.</td></tr>';
    }
    echo '</tbody></table></div></section>';
    layout('Participants', ob_get_clean());
}

function matches_view(int $id): void
{
    $t = find_tournament($id);
    $rows = matches_for_tournament($id);
    ob_start();
    echo '<section class="page-head"><div><h1>Matchs</h1><p>Saisie rapide des scores.</p></div></section>' . admin_nav($id, 'matches');
    echo '<section class="panel"><table><thead><tr><th>#</th><th>Phase</th><th>Terrain</th><th>Match</th><th>Score</th><th>Saisie</th><th>Statut</th></tr></thead><tbody>';
    foreach ($rows as $row) {
        $displayScoreA = $row['draft_score_a'] ?? $row['score_a'] ?? '';
        $displayScoreB = $row['draft_score_b'] ?? $row['score_b'] ?? '';
        $phaseLabel = $row['phase'] === 'final' ? $row['round'] : $row['pool_name'];
        echo '<tr><td>' . (int) $row['scheduled_order'] . '</td><td>' . h($phaseLabel) . '</td><td>' . (int) $row['field_number'] . '</td><td>' . h($row['participant_a_name']) . ' vs ' . h($row['participant_b_name']) . '</td>';
        echo '<td>' . match_score_form($id, $row, $displayScoreA, $displayScoreB, 'matches');
        if ($row['status'] === 'finished') {
            echo '<form method="post" action="/admin/' . $id . '/matches/' . (int) $row['id'] . '/clear"><input type="hidden" name="return_to" value="matches"><button class="link danger">Effacer</button></form>';
        }
        echo '</td><td>' . score_state_badge($row) . '</td><td>' . status_badge($row['status']) . '</td></tr>';
    }
    if (!$rows) {
        echo '<tr><td colspan="7" class="empty">Generez les matchs depuis la synthese.</td></tr>';
    }
    echo '</tbody></table></section>';
    echo autosave_scores_script();
    layout('Matchs', ob_get_clean());
}

function match_score_form(int $tournamentId, array $match, string|int $scoreA, string|int $scoreB, string $returnTo): string
{
    return '<form class="score-form" data-autosave-score data-autosave-url="/admin/' . $tournamentId . '/matches/' . (int) $match['id'] . '/draft" method="post" action="/admin/' . $tournamentId . '/matches/' . (int) $match['id'] . '/score"><input type="hidden" name="return_to" value="' . h($returnTo) . '"><input type="number" min="0" name="score_a" value="' . h($scoreA) . '"><span>-</span><input type="number" min="0" name="score_b" value="' . h($scoreB) . '"><button class="button small">Valider</button><small class="autosave-state" aria-live="polite"></small></form>';
}

function fields_view(int $id): void
{
    $t = find_tournament($id);
    $rows = matches_for_tournament($id);
    $fieldCount = max((int) $t['number_of_fields'], ...array_map(static fn(array $match): int => (int) $match['field_number'], $rows ?: [['field_number' => 1]]));
    $matchesByField = [];
    for ($field = 1; $field <= $fieldCount; $field++) {
        $matchesByField[$field] = array_values(array_filter($rows, static fn(array $match): bool => (int) $match['field_number'] === $field));
    }

    ob_start();
    echo '<section class="page-head"><div><h1>Terrains</h1><p>Suivi par terrain des matchs et des scores.</p></div></section>' . admin_nav($id, 'fields');
    echo '<section class="field-board">';
    foreach ($matchesByField as $fieldNumber => $matches) {
        $finished = array_values(array_filter($matches, static fn(array $match): bool => $match['status'] === 'finished'));
        $remaining = array_values(array_filter($matches, static fn(array $match): bool => $match['status'] !== 'finished'));
        $current = $remaining[0] ?? null;
        echo '<article class="panel field-card"><header><h2>Terrain ' . (int) $fieldNumber . '</h2><span>' . count($finished) . '/' . count($matches) . ' termines</span></header>';
        if ($current) {
            $phaseLabel = $current['phase'] === 'final' ? $current['round'] : $current['pool_name'];
            $displayScoreA = $current['draft_score_a'] ?? $current['score_a'] ?? '';
            $displayScoreB = $current['draft_score_b'] ?? $current['score_b'] ?? '';
            echo '<div class="field-current"><span class="badge badge-running">En cours</span><strong>' . h($current['participant_a_name']) . ' vs ' . h($current['participant_b_name']) . '</strong><small>' . h($phaseLabel) . ' - Match #' . (int) $current['scheduled_order'] . '</small>' . match_score_form($id, $current, $displayScoreA, $displayScoreB, 'fields') . '</div>';
        } else {
            echo '<p class="empty compact">Aucun match restant.</p>';
        }

        echo '<div class="field-list"><h3>A venir</h3>';
        foreach (array_slice($remaining, 1, 4) as $match) {
            $phaseLabel = $match['phase'] === 'final' ? $match['round'] : $match['pool_name'];
            echo '<p><span>' . h($phaseLabel) . '</span><strong>' . h($match['participant_a_name']) . ' vs ' . h($match['participant_b_name']) . '</strong></p>';
        }
        if (count($remaining) <= 1) {
            echo '<p class="muted">Aucun autre match planifie.</p>';
        }
        echo '</div>';

        echo '<div class="field-list"><h3>Derniers resultats</h3>';
        foreach (array_slice(array_reverse($finished), 0, 3) as $match) {
            echo '<p><span>#' . (int) $match['scheduled_order'] . '</span><strong>' . h($match['participant_a_name']) . ' ' . h($match['score_a']) . '-' . h($match['score_b']) . ' ' . h($match['participant_b_name']) . '</strong></p>';
        }
        if (!$finished) {
            echo '<p class="muted">Aucun resultat.</p>';
        }
        echo '</div></article>';
    }
    echo '</section>';
    echo autosave_scores_script();
    layout('Terrains', ob_get_clean());
}

function autosave_scores_script(): string
{
    return <<<'HTML'
<script>
document.querySelectorAll('[data-autosave-score]').forEach(function (form) {
  var timer = null;
  var dirty = false;
  var state = form.querySelector('.autosave-state');
  var inputs = form.querySelectorAll('input[type="number"]');
  function hasCompleteScore() {
    return inputs[0].value !== '' && inputs[1].value !== '';
  }
  function setState(text, mode) {
    state.textContent = text;
    state.dataset.state = mode;
  }
  function save() {
    if (!hasCompleteScore()) {
      setState('', '');
      return;
    }
    setState('Enregistrement brouillon...', 'saving');
    fetch(form.dataset.autosaveUrl, {
      method: 'POST',
      headers: {'X-Requested-With': 'fetch'},
      body: new FormData(form)
    })
      .then(function (response) { return response.json(); })
      .then(function (payload) {
        if (payload.ok) {
          dirty = false;
        }
        setState(payload.message || (payload.ok ? 'Brouillon enregistre' : 'Erreur'), payload.ok ? 'ok' : 'error');
      })
      .catch(function () {
        setState('Erreur reseau', 'error');
      });
  }
  function flush() {
    if (!dirty || !hasCompleteScore() || !navigator.sendBeacon) {
      return;
    }
    navigator.sendBeacon(form.dataset.autosaveUrl, new FormData(form));
    dirty = false;
  }
  inputs.forEach(function (input) {
    input.addEventListener('input', function () {
      clearTimeout(timer);
      dirty = true;
      setState('Modification...', 'pending');
      timer = setTimeout(save, 700);
    });
    input.addEventListener('blur', function () {
      clearTimeout(timer);
      save();
    });
  });
  window.addEventListener('pagehide', flush);
});
</script>
HTML;
}

function standings_table(array $rows): string
{
    $html = '<table><thead><tr><th>#</th><th>Participant</th><th>MJ</th><th>V</th><th>D</th><th>Pts</th><th>Pour</th><th>Contre</th><th>Diff</th></tr></thead><tbody>';
    foreach ($rows as $i => $row) {
        $html .= '<tr><td>' . ($i + 1) . '</td><td>' . h($row['participant']) . '</td><td>' . (int) $row['played'] . '</td><td>' . (int) $row['wins'] . '</td><td>' . (int) $row['losses'] . '</td><td>' . (int) $row['ranking_points'] . '</td><td>' . (int) $row['scored'] . '</td><td>' . (int) $row['conceded'] . '</td><td>' . (int) $row['diff'] . '</td></tr>';
    }
    if (!$rows) {
        $html .= '<tr><td colspan="9" class="empty">Aucun classement disponible.</td></tr>';
    }
    return $html . '</tbody></table>';
}

function standings_view(int $id): void
{
    $t = find_tournament($id);
    ob_start();
    echo '<section class="page-head"><div><h1>Classements</h1><p>' . h($t['name']) . '</p></div></section>' . admin_nav($id, 'standings');
    foreach (pools($id) as $pool) {
        echo '<section class="panel"><h2>' . h($pool['name']) . '</h2>' . standings_table(standings($id, (int) $pool['id'])) . '</section>';
    }
    echo '<section class="panel"><h2>General</h2>' . standings_table(standings($id)) . '</section>';
    layout('Classements', ob_get_clean());
}

function bracket_view(int $id): void
{
    $t = find_tournament($id);
    $bracket = final_bracket(matches_for_tournament($id));
    ob_start();
    echo '<section class="page-head"><div><h1>Tableau final</h1><p>' . h($t['name']) . '</p></div></section>' . admin_nav($id, 'bracket');
    if ($bracket) {
        echo final_bracket_panel($bracket);
    } else {
        echo '<section class="panel"><p class="empty">Aucun match de phase finale genere pour le moment.</p></section>';
    }
    layout('Tableau final', ob_get_clean());
}

function public_stats_cards(array $summary): string
{
    return '<section class="public-stats">'
        . '<div><strong>' . (int) $summary['remaining_matches'] . '</strong><span>Matchs restants</span></div>'
        . '<div><strong>' . (int) $summary['finished_matches'] . '/' . (int) $summary['total_matches'] . '</strong><span>Matchs termines</span></div>'
        . '<div><strong>' . (int) $summary['participants_count'] . '</strong><span>Participants</span></div>'
        . '<div><strong>' . (int) $summary['fields_count'] . '</strong><span>Terrains</span></div>'
        . '</section>';
}

function progress_bar(array $summary): string
{
    $progress = max(0, min(100, (int) $summary['progress']));
    return '<section class="progress-block"><div><strong>Progression</strong><span>' . $progress . '%</span></div><div class="progress-bar"><span style="width: ' . $progress . '%"></span></div></section>';
}

function public_rules_panel(array $tournament): string
{
    $rules = plugin_public_rules($tournament['plugin_key'], $tournament['settings_array']);
    $items = '';
    foreach ($rules as $rule) {
        $items .= '<li>' . h($rule) . '</li>';
    }
    return '<div class="panel public-rules"><h2>Regles</h2><p>' . h(plugin($tournament['plugin_key'])['description']) . '</p><ul>' . $items . '</ul></div>';
}

function public_qualified_panel(array $qualified): string
{
    if (!$qualified) {
        return '';
    }
    $items = '';
    foreach ($qualified as $team) {
        $items .= '<li><span>' . h($team['pool_name']) . ' #' . (int) $team['rank'] . '</span><strong>' . h($team['participant']) . '</strong><em>' . (int) $team['points'] . ' pts</em></li>';
    }
    return '<div class="panel public-qualified"><h2>Equipes qualifiees</h2><p>En attente de generation des phases finales.</p><ul>' . $items . '</ul></div>';
}

function public_finished_panel(array $summary): string
{
    return '<section class="finished-view">' . podium_panel($summary['podium']) . '<div class="panel final-ranking"><h2>Classement final</h2>' . final_standings_table($summary['final_standings'], 4) . '</div></section>';
}

function podium_panel(array $podium): string
{
    if (!$podium) {
        return '<div class="panel podium"><h2>Podium</h2><p class="empty compact">Aucun podium disponible.</p></div>';
    }

    $order = [1 => $podium[0] ?? null, 2 => $podium[1] ?? null, 3 => $podium[2] ?? null];
    $html = '<div class="panel podium"><h2>Podium</h2><div class="podium-steps">';
    foreach ($order as $place => $row) {
        $html .= '<article class="podium-place podium-place-' . $place . '"><span>' . $place . '</span><strong>' . h($row['participant'] ?? '-') . '</strong></article>';
    }
    return $html . '</div></div>';
}

function final_standings_table(array $rows, int $startRank): string
{
    $html = '<table><thead><tr><th>#</th><th>Participant</th><th>MJ</th><th>V</th><th>D</th><th>Pts</th><th>Diff</th></tr></thead><tbody>';
    foreach ($rows as $i => $row) {
        $html .= '<tr><td>' . ($startRank + $i) . '</td><td>' . h($row['participant']) . '</td><td>' . (int) $row['played'] . '</td><td>' . (int) $row['wins'] . '</td><td>' . (int) $row['losses'] . '</td><td>' . (int) $row['ranking_points'] . '</td><td>' . (int) $row['diff'] . '</td></tr>';
    }
    if (!$rows) {
        $html .= '<tr><td colspan="7" class="empty">Aucune equipe au-dela du podium.</td></tr>';
    }
    return $html . '</tbody></table>';
}

function final_bracket_panel(array $bracket): string
{
    if (!$bracket) {
        return '';
    }

    $html = '<div class="panel final-bracket"><h2>Tableau final</h2><div class="bracket-grid">';
    foreach ($bracket as $roundIndex => $round) {
        $nextRound = $bracket[$roundIndex + 1]['round'] ?? null;
        $hasNextRound = $nextRound !== null && $round['round'] !== 'Finale' && $nextRound !== 'Petite finale';
        $html .= '<section class="bracket-round bracket-count-' . count($round['matches']) . ($hasNextRound ? ' has-next-round' : '') . '"><h3>' . h($round['round']) . '</h3><div class="bracket-round-matches">';
        foreach ($round['matches'] as $matchIndex => $match) {
            $html .= '<article class="bracket-match"><small>Match #' . (int) $match['scheduled_order'] . ' - Terrain ' . (int) $match['field_number'] . '</small>'
                . bracket_team_row($match['participant_a_name'], $match['score_a'], (int) $match['winner_participant_id'] === (int) $match['participant_a_id'])
                . bracket_team_row($match['participant_b_name'], $match['score_b'], (int) $match['winner_participant_id'] === (int) $match['participant_b_id'])
                . '</article>';
        }
        $html .= '</div></section>';
    }
    return $html . '</div></div>';
}

function bracket_team_row(string $name, mixed $score, bool $winner): string
{
    return '<p class="' . ($winner ? 'winner' : '') . '"><span class="team-name">' . h($name) . '</span><strong class="team-score">' . h($score ?? '-') . '</strong></p>';
}

function compact_results_table(array $matches): string
{
    $html = '<table><thead><tr><th>Equipe A</th><th>Score</th><th>Equipe B</th></tr></thead><tbody>';
    foreach ($matches as $m) {
        $html .= '<tr><td>' . h($m['participant_a_name']) . '</td><td><strong>' . h($m['score_a']) . '-' . h($m['score_b']) . '</strong></td><td>' . h($m['participant_b_name']) . '</td></tr>';
    }
    if (!$matches) {
        $html .= '<tr><td class="empty">Aucun resultat pour le moment.</td></tr>';
    }
    return $html . '</tbody></table>';
}

function display_view(int $id): void
{
    $summary = public_tournament_summary($id);
    $t = $summary['tournament'];
    $matches = array_slice($summary['next_matches'], 0, 6);
    $mobileUrl = app_url('/t/' . $id);
    ob_start();
    echo '<section class="display-head"><div><h1>' . h($t['name']) . '</h1><p>' . h(plugin($t['plugin_key'])['name']) . ' - ' . h($t['event_date']) . '</p></div><div class="display-qr"><img src="/qr/' . $id . '" alt="QR Code acces mobile"><span>' . h($mobileUrl) . '</span></div></section>';
    echo public_stats_cards($summary);
    echo progress_bar($summary);
    if ($summary['is_complete']) {
        echo public_finished_panel($summary);
        echo auto_refresh_script(5);
        layout('Affichage TV', ob_get_clean(), 'display');
        return;
    }
    echo '<section class="display-grid"><div class="panel"><h2>Prochains matchs</h2><table><thead><tr><th>Terrain</th><th>Poule</th><th>Equipe A</th><th></th><th>Equipe B</th></tr></thead><tbody>';
    foreach ($matches as $m) {
        $phaseLabel = $m['phase'] === 'final' ? $m['round'] : $m['pool_name'];
        echo '<tr><td>Terrain ' . (int) $m['field_number'] . '</td><td>' . h($phaseLabel) . '</td><td>' . h($m['participant_a_name']) . '</td><td>vs</td><td>' . h($m['participant_b_name']) . '</td></tr>';
    }
    if (!$matches) {
        echo '<tr><td colspan="5" class="empty">Tous les matchs sont termines.</td></tr>';
    }
    echo '</tbody></table></div><div class="panel"><h2>Classement general</h2>' . standings_table(array_slice(standings($id), 0, 8)) . '</div></section>';
    $temporaryPanel = $summary['qualified_teams'] ? public_qualified_panel($summary['qualified_teams']) : ($summary['final_bracket'] ? final_bracket_panel($summary['final_bracket']) : public_rules_panel($t));
    echo '<section class="display-grid secondary"><div class="panel"><h2>Derniers resultats</h2>' . compact_results_table($summary['last_results']) . '</div><div class="panel public-highlight"><h2>Infos tournoi</h2><p><strong>Leader actuel</strong><span>' . h($summary['leader_label']) . '</span></p><p><strong>Match le plus serre</strong><span>' . h($summary['closest_match_label']) . '</span></p><p><strong>Poules</strong><span>' . (int) $summary['pools_count'] . '</span></p></div>' . $temporaryPanel . '</section>';
    echo auto_refresh_script(5);
    layout('Affichage TV', ob_get_clean(), 'display');
}

function mobile_view(int $id): void
{
    $summary = public_tournament_summary($id);
    $t = $summary['tournament'];
    ob_start();
    echo '<section class="mobile-head"><h1>' . h($t['name']) . '</h1><p>' . h(plugin($t['plugin_key'])['name']) . '</p></section>';
    echo public_stats_cards($summary);
    echo progress_bar($summary);
    if ($summary['is_complete']) {
        echo public_finished_panel($summary);
        echo auto_refresh_script(5);
        layout('Vue mobile', ob_get_clean());
        return;
    }
    echo '<section class="panel public-highlight"><h2>Infos tournoi</h2><p><strong>Leader actuel</strong><span>' . h($summary['leader_label']) . '</span></p><p><strong>Match le plus serre</strong><span>' . h($summary['closest_match_label']) . '</span></p></section>';
    echo public_qualified_panel($summary['qualified_teams']);
    echo final_bracket_panel($summary['final_bracket']);
    echo public_rules_panel($t);
    echo '<section class="panel"><h2>Prochains matchs</h2><table><tbody>';
    foreach (array_slice($summary['next_matches'], 0, 10) as $m) {
        echo '<tr><td>Terrain ' . (int) $m['field_number'] . '</td><td>' . h($m['participant_a_name']) . ' vs ' . h($m['participant_b_name']) . '</td></tr>';
    }
    if (!$summary['next_matches']) {
        echo '<tr><td class="empty">Tous les matchs sont termines.</td></tr>';
    }
    echo '</tbody></table></section><section class="panel"><h2>Derniers resultats</h2>' . compact_results_table($summary['last_results']) . '</section><section class="panel"><h2>Classement</h2>' . standings_table(standings($id)) . '</section>';
    echo auto_refresh_script(5);
    layout('Vue mobile', ob_get_clean());
}

function qr_view(int $id): void
{
    find_tournament($id);
    header('Content-Type: image/svg+xml; charset=utf-8');
    header('Cache-Control: no-store');
    echo qr_svg(app_url('/t/' . $id));
}
