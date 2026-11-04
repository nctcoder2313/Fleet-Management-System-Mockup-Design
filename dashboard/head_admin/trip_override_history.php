<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';
$activePage = 'emergency_overrides';
$pageTitle = 'Trip override history';
$overrideEvents = ['TRIP_FORCE_COMPLETED', 'TRIP_CANCELLED'];
$history = [];

if ($previewMode) {
    foreach ($_SESSION['head_admin_preview_data']['audit_logs'] as $log) {
        if (!in_array($log['event_type'], $overrideEvents, true)) {
            continue;
        }
        $details = json_decode((string) $log['details'], true);
        $history[] = [
            'event_type' => $log['event_type'],
            'created_at' => $log['created_at'],
            'actor_name' => $log['actor_name'],
            'details' => is_array($details) ? $details : [],
        ];
    }
} else {
    $statement = $pdo->query(
        "SELECT al.event_type, al.details, al.created_at, u.name AS actor_name
         FROM audit_logs al
         LEFT JOIN users u ON u.id = al.actor_user_id
         WHERE al.event_type IN ('TRIP_FORCE_COMPLETED', 'TRIP_CANCELLED')
         ORDER BY al.created_at DESC
         LIMIT 100"
    );
    foreach ($statement->fetchAll() as $log) {
        $details = json_decode((string) $log['details'], true);
        $log['details'] = is_array($details) ? $details : [];
        $history[] = $log;
    }
}

require __DIR__ . '/_layout_start.php';
?>
<section class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
    <div>
        <p class="text-sm font-medium text-slate-500">Fleet operations · Accountability</p>
        <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-900">Trip override history</h2>
        <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">Review emergency trip closures, including the acting Head Admin, affected vehicle, action, recorded time, and reason.</p>
    </div>
    <a href="<?= $escape(headAdminUrl('emergency_overrides.php')) ?>" class="inline-flex w-fit items-center rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Back to active trips</a>
</section>

<section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
    <div class="border-b border-slate-100 px-5 py-4 sm:px-6">
        <h2 class="font-bold text-slate-900"><?= count($history) ?> recorded override<?= count($history) === 1 ? '' : 's' ?></h2>
        <p class="mt-1 text-sm text-slate-500">The latest 100 emergency actions are shown.</p>
    </div>
    <?php if (!$history): ?>
        <p class="p-8 text-center text-sm text-slate-500">No emergency trip overrides have been recorded.</p>
    <?php else: ?>
        <div class="divide-y divide-slate-100">
            <?php foreach ($history as $entry): ?>
                <?php $details = $entry['details']; ?>
                <article class="px-5 py-4 sm:px-6">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="font-semibold text-slate-900"><?= $entry['event_type'] === 'TRIP_FORCE_COMPLETED' ? 'Trip force completed' : 'Trip cancelled' ?></p>
                            <p class="mt-1 text-sm text-slate-600">
                                <?= $escape($details['trip_reference'] ?? 'Trip') ?>
                                <?php if (isset($details['plate_number'])): ?> · <?= $escape($details['plate_number']) ?><?php endif; ?>
                                <?php if (isset($details['vehicle_name'])): ?> · <?= $escape($details['vehicle_name']) ?><?php endif; ?>
                            </p>
                            <p class="mt-1 text-xs text-slate-500">By <?= $escape($entry['actor_name'] ?? 'Former user') ?></p>
                        </div>
                        <time class="shrink-0 text-xs text-slate-500" datetime="<?= $escape(date(DATE_ATOM, strtotime($entry['created_at']))) ?>"><?= $escape(date('M j, Y \a\t g:i A', strtotime($entry['created_at']))) ?></time>
                    </div>
                    <p class="mt-3 rounded-lg bg-slate-50 px-3 py-2 text-sm leading-6 text-slate-700"><span class="font-semibold">Reason:</span> <?= $escape($details['reason'] ?? 'No reason recorded') ?></p>
                    <?php if (isset($details['vehicle_id'])): ?>
                        <a href="<?= $escape(headAdminUrl('vehicle_detail.php', ['id' => (int) $details['vehicle_id']])) ?>" class="mt-3 inline-flex text-sm font-semibold text-blue-700 hover:underline">Open vehicle profile</a>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/_layout_end.php'; ?>
