<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';
$activePage = 'audit_logs';
$pageTitle = 'Audit history';
$eventLabels = [
    'ROLE_ACCESS_REQUESTED' => 'Access request submitted',
    'ROLE_ACCESS_GRANTED' => 'Temporary access granted',
    'ROLE_ACCESS_DENIED' => 'Access request denied',
    'USER_CREATED' => 'User account created',
    'USER_UPDATED' => 'User account updated',
    'USER_DEACTIVATED' => 'User account deactivated',
    'USER_REACTIVATED' => 'User account reactivated',
    'USER_PASSWORD_RESET' => 'User password reset',
    'ACCOUNT_REQUEST_APPROVED' => 'New account request approved',
    'ACCOUNT_REQUEST_REJECTED' => 'New account request rejected',
    'TRIP_FORCE_COMPLETED' => 'Trip force completed',
    'TRIP_CANCELLED' => 'Trip cancelled',
];
$requestedPeriod = $_GET['period'] ?? 'week';
$period = is_string($requestedPeriod) && in_array($requestedPeriod, ['today', 'week', 'month', 'all'], true)
    ? $requestedPeriod
    : 'week';
$requestedEvent = $_GET['event'] ?? '';
$eventFilter = is_string($requestedEvent) && (array_key_exists($requestedEvent, $eventLabels) || $requestedEvent === '')
    ? $requestedEvent
    : '';
$requestedSearch = $_GET['q'] ?? '';
$search = is_string($requestedSearch) ? trim($requestedSearch) : '';
$logs = [];

if ($previewMode) {
    $cutoff = match ($period) {
        'today' => strtotime('today'),
        'week' => strtotime('-7 days'),
        'month' => strtotime(date('Y-m-01 00:00:00')),
        'all' => 0,
    };
    $logs = array_values(array_filter(
        $_SESSION['head_admin_preview_data']['audit_logs'],
        static function (array $log) use ($cutoff, $eventFilter, $search): bool {
            $matchesPeriod = $cutoff === 0 || strtotime($log['created_at']) >= $cutoff;
            $matchesEvent = $eventFilter === '' || $log['event_type'] === $eventFilter;
            $matchesSearch = $search === '' || stripos(
                $log['event_type'] . ' ' . $log['actor_name'] . ' ' . $log['details'],
                $search
            ) !== false;
            return $matchesPeriod && $matchesEvent && $matchesSearch;
        }
    ));
} else {
    $conditions = [];
    $parameters = [];
    $periodSql = match ($period) {
        'today' => 'al.created_at >= CURRENT_DATE',
        'week' => 'al.created_at >= DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 7 DAY)',
        'month' => 'YEAR(al.created_at) = YEAR(CURRENT_DATE) AND MONTH(al.created_at) = MONTH(CURRENT_DATE)',
        'all' => null,
    };
    if ($periodSql !== null) {
        $conditions[] = $periodSql;
    }
    if ($eventFilter !== '') {
        $conditions[] = 'al.event_type = :event_type';
        $parameters['event_type'] = $eventFilter;
    }
    if ($search !== '') {
        $conditions[] = '(u.name LIKE :actor_search OR CAST(al.details AS CHAR) LIKE :details_search)';
        $parameters['actor_search'] = '%' . $search . '%';
        $parameters['details_search'] = '%' . $search . '%';
    }
    $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
    $statement = $pdo->prepare(
        "SELECT al.event_type, al.details, al.created_at, u.name AS actor_name
         FROM audit_logs al
         LEFT JOIN users u ON u.id = al.actor_user_id
         {$where}
         ORDER BY al.created_at DESC
         LIMIT 100"
    );
    $statement->execute($parameters);
    $logs = $statement->fetchAll();
}

require __DIR__ . '/_layout_start.php';
?>
<section>
    <p class="text-sm font-medium text-slate-500">Account and access administration</p>
    <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-900">Audit history</h2>
    <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">Review who performed key account and access actions and when they occurred. Expand an event to inspect its recorded details.</p>
</section>

<section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
    <form method="get" class="grid gap-3 sm:grid-cols-[12rem_16rem_minmax(12rem,1fr)_auto]">
        <?php if ($previewMode): ?><input type="hidden" name="preview" value="1"><?php endif; ?>
        <label class="sr-only" for="period-filter">Filter by time period</label>
        <select id="period-filter" name="period" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm">
            <?php foreach (['today' => 'Today', 'week' => 'Past 7 days', 'month' => 'This month', 'all' => 'All available'] as $value => $label): ?>
                <option value="<?= $escape($value) ?>" <?= $period === $value ? 'selected' : '' ?>><?= $escape($label) ?></option>
            <?php endforeach; ?>
        </select>
        <label class="sr-only" for="event-filter">Filter by event type</label>
        <select id="event-filter" name="event" class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm">
            <option value="">All event types</option>
            <?php foreach ($eventLabels as $event => $label): ?>
                <option value="<?= $escape($event) ?>" <?= $eventFilter === $event ? 'selected' : '' ?>><?= $escape($label) ?></option>
            <?php endforeach; ?>
        </select>
        <label class="sr-only" for="audit-search">Search audit history</label>
        <input id="audit-search" type="search" name="q" value="<?= $escape($search) ?>" placeholder="Search actor or event details" class="rounded-lg border border-slate-300 px-3 py-2.5 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
        <button type="submit" class="rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Apply filters</button>
    </form>
</section>

<section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-5 py-4 sm:px-6">
        <div>
            <h2 class="font-bold text-slate-900">Recorded events</h2>
            <p class="mt-1 text-sm text-slate-500"><?= count($logs) ?> matching event<?= count($logs) === 1 ? '' : 's' ?><?= !$previewMode && count($logs) === 100 ? ' (latest 100 shown)' : '' ?></p>
        </div>
        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600"><?= $escape(['today' => 'Today', 'week' => 'Past 7 days', 'month' => 'This month', 'all' => 'All available'][$period]) ?></span>
    </div>
    <?php if (!$logs): ?>
        <p class="p-10 text-center text-sm text-slate-500">No audit events match the selected filters.</p>
    <?php else: ?>
        <div class="divide-y divide-slate-100">
            <?php foreach ($logs as $log): ?>
                <article class="px-5 py-4 sm:px-6">
                    <?php
                    $decodedDetails = json_decode((string) $log['details'], true);
                    $decodedDetails = is_array($decodedDetails) ? $decodedDetails : [];
                    $formattedDetails = json_encode($decodedDetails, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                    ?>
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="font-semibold text-slate-900"><?= $escape($eventLabels[$log['event_type']] ?? ucwords(strtolower(str_replace('_', ' ', $log['event_type'])))) ?></p>
                            <p class="mt-1 text-sm text-slate-500">Performed by <?= $escape($log['actor_name'] ?? 'System') ?></p>
                            <?php if (isset($decodedDetails['user_name'], $decodedDetails['user_email'])): ?>
                                <p class="mt-1 text-sm text-slate-700">Account: <?= $escape($decodedDetails['user_name']) ?> (<?= $escape($decodedDetails['user_email']) ?>)</p>
                            <?php endif; ?>
                            <?php if (isset($decodedDetails['trip_reference'], $decodedDetails['plate_number'])): ?>
                                <p class="mt-1 text-sm text-slate-700">Trip <?= $escape($decodedDetails['trip_reference']) ?> · Vehicle <?= $escape($decodedDetails['plate_number']) ?></p>
                                <?php if (isset($decodedDetails['reason'])): ?>
                                    <p class="mt-1 text-sm text-slate-600">Reason: <?= $escape($decodedDetails['reason']) ?></p>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                        <time class="shrink-0 text-xs text-slate-500" datetime="<?= $escape(date(DATE_ATOM, strtotime($log['created_at']))) ?>"><?= $escape(date('M j, Y \a\t g:i A', strtotime($log['created_at']))) ?></time>
                    </div>
                    <?php if ($formattedDetails !== false && $decodedDetails): ?>
                        <details class="mt-3 text-sm">
                            <summary class="w-fit cursor-pointer font-medium text-blue-700 hover:text-blue-900">View event details</summary>
                            <pre class="mt-2 overflow-x-auto rounded-lg bg-slate-50 p-3 text-xs leading-5 text-slate-700"><?= $escape($formattedDetails) ?></pre>
                        </details>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/_layout_end.php'; ?>
