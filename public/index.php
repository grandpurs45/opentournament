<?php

declare(strict_types=1);

if (PHP_SAPI === 'cli-server') {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $file = dirname(__DIR__) . $path;
    if (is_file($file)) {
        return false;
    }
}

require_once dirname(__DIR__) . '/app/bootstrap.php';

$path = request_path();

if ($path === '/') {
    dashboard_view();
    return;
}

if ($path === '/tournaments/create') {
    if (is_post()) {
        $id = create_tournament([
            'name' => post_string('name'),
            'event_date' => post_string('event_date'),
            'plugin_key' => post_string('plugin_key', 'generic'),
            'format' => post_string('format', 'pools'),
            'number_of_fields' => post_int('number_of_fields', 1),
        ]);
        redirect_to('/admin/' . $id);
    }
    create_tournament_view();
    return;
}

if (preg_match('#^/admin/(\d+)$#', $path, $m)) {
    admin_overview_view((int) $m[1]);
    return;
}

if (preg_match('#^/admin/(\d+)/settings$#', $path, $m) && is_post()) {
    $id = (int) $m[1];
    update_tournament($id, [
        'name' => post_string('name'),
        'event_date' => post_string('event_date'),
        'format' => post_string('format', 'pools'),
        'number_of_fields' => post_int('number_of_fields', 1),
        'targetScore' => post_string('targetScore'),
        'winPoints' => post_string('winPoints'),
        'lossPoints' => post_string('lossPoints'),
    ]);
    redirect_to('/admin/' . $id);
}

if (preg_match('#^/admin/(\d+)/delete$#', $path, $m) && is_post()) {
    if (post_string('confirm_delete') === '1') {
        delete_tournament((int) $m[1]);
    } else {
        flash('Suppression annulee : confirmation requise.');
    }
    redirect_to('/');
}

if (preg_match('#^/admin/(\d+)/participants$#', $path, $m)) {
    $id = (int) $m[1];
    if (is_post()) {
        add_participant($id, [
            'name' => post_string('name'),
            'type' => post_string('type', 'team'),
            'players' => post_string('players'),
            'color' => post_string('color'),
            'emoji' => post_string('emoji'),
        ]);
        redirect_to('/admin/' . $id . '/participants');
    }
    participants_view($id);
    return;
}

if (preg_match('#^/admin/(\d+)/participants/import$#', $path, $m) && is_post()) {
    $id = (int) $m[1];
    import_participants($id, post_string('participants'));
    redirect_to('/admin/' . $id . '/participants');
}

if (preg_match('#^/admin/(\d+)/participants/delete$#', $path, $m) && is_post()) {
    $id = (int) $m[1];
    delete_participant($id, post_int('participant_id'));
    redirect_to('/admin/' . $id . '/participants');
}

if (preg_match('#^/admin/(\d+)/generate-pools$#', $path, $m) && is_post()) {
    $id = (int) $m[1];
    generate_pools($id, post_string('force') === '1');
    redirect_to('/admin/' . $id);
}

if (preg_match('#^/admin/(\d+)/generate-matches$#', $path, $m) && is_post()) {
    $id = (int) $m[1];
    generate_matches($id, post_string('force') === '1');
    redirect_to('/admin/' . $id . '/matches');
}

if (preg_match('#^/admin/(\d+)/matches$#', $path, $m)) {
    matches_view((int) $m[1]);
    return;
}

if (preg_match('#^/admin/(\d+)/matches/(\d+)/draft$#', $path, $m) && is_post()) {
    $result = save_score_draft((int) $m[1], (int) $m[2], post_int('score_a'), post_int('score_b'));
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($result, JSON_THROW_ON_ERROR);
    return;
}

if (preg_match('#^/admin/(\d+)/matches/(\d+)/score$#', $path, $m) && is_post()) {
    $id = (int) $m[1];
    save_score($id, (int) $m[2], post_int('score_a'), post_int('score_b'));
    redirect_to('/admin/' . $id . '/matches');
}

if (preg_match('#^/admin/(\d+)/matches/(\d+)/clear$#', $path, $m) && is_post()) {
    $id = (int) $m[1];
    clear_score($id, (int) $m[2]);
    redirect_to('/admin/' . $id . '/matches');
}

if (preg_match('#^/admin/(\d+)/standings$#', $path, $m)) {
    standings_view((int) $m[1]);
    return;
}

if (preg_match('#^/display/(\d+)$#', $path, $m)) {
    display_view((int) $m[1]);
    return;
}

if (preg_match('#^/t/(\d+)$#', $path, $m)) {
    mobile_view((int) $m[1]);
    return;
}

if (preg_match('#^/qr/(\d+)$#', $path, $m)) {
    qr_view((int) $m[1]);
    return;
}

if (preg_match('#^/export/(\d+)/(participants|matches|standings)$#', $path, $m)) {
    $id = (int) $m[1];
    if ($m[2] === 'participants') {
        $rows = array_map(static fn($p) => [$p['id'], $p['name'], $p['type'], $p['players']], participants($id));
        export_csv('participants.csv', ['id', 'name', 'type', 'players'], $rows);
    }
    if ($m[2] === 'matches') {
        $rows = array_map(static fn($x) => [$x['scheduled_order'], $x['pool_name'], $x['field_number'], $x['participant_a_name'], $x['participant_b_name'], $x['score_a'], $x['score_b'], $x['status']], matches_for_tournament($id));
        export_csv('matches.csv', ['order', 'pool', 'field', 'participant_a', 'participant_b', 'score_a', 'score_b', 'status'], $rows);
    }
    $rows = array_map(static fn($s) => [$s['participant'], $s['played'], $s['wins'], $s['losses'], $s['ranking_points'], $s['scored'], $s['conceded'], $s['diff']], standings($id));
    export_csv('standings.csv', ['participant', 'played', 'wins', 'losses', 'points', 'scored', 'conceded', 'diff'], $rows);
}

http_response_code(404);
layout('Page introuvable', '<section class="panel"><h1>Page introuvable</h1><p>La route demandee n existe pas.</p></section>');
