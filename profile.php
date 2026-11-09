<?php
declare(strict_types=1);

$pdo = require __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/rbac.php';
startSecureSession();
$user = authenticatedUser($pdo);
if (!$user) {
    header('Location: login.php');
    exit;
}

$error = '';
$notice = '';
$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrfToken();
    $upload = $_FILES['profile_photo'] ?? null;

    if (!is_array($upload) || !isset($upload['error'], $upload['tmp_name']) || !is_string($upload['tmp_name'])) {
        $error = 'Choose a profile picture to upload.';
    } elseif ($upload['error'] !== UPLOAD_ERR_OK) {
        $error = match ($upload['error']) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The picture is too large. Choose an image no larger than 3 MB.',
            UPLOAD_ERR_NO_FILE => 'Choose a profile picture to upload.',
            default => 'The picture could not be uploaded. Please try again.',
        };
    } elseif (!is_uploaded_file($upload['tmp_name'])) {
        $error = 'The uploaded file could not be verified.';
    } else {
        $fileSize = filesize($upload['tmp_name']);
        $imageInfo = getimagesize($upload['tmp_name']);
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            throw new RuntimeException('The PHP fileinfo extension is required for profile photo uploads.');
        }
        $mimeType = finfo_file($finfo, $upload['tmp_name']);
        finfo_close($finfo);

        $allowedImages = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];
        if ($fileSize === false || $fileSize > 3 * 1024 * 1024) {
            $error = 'The picture must be no larger than 3 MB.';
        } elseif ($imageInfo === false || !is_string($mimeType)
            || !isset($allowedImages[$mimeType])
            || ($imageInfo['mime'] ?? '') !== $mimeType
            || $imageInfo[0] < 1 || $imageInfo[1] < 1
            || $imageInfo[0] > 5000 || $imageInfo[1] > 5000
        ) {
            $error = 'Choose a valid JPEG, PNG, or WebP image up to 5000 × 5000 pixels.';
        } else {
            $directory = __DIR__ . '/storage/profile_photos';
            if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
                throw new RuntimeException('Could not create the profile photo storage directory.');
            }

            $filename = bin2hex(random_bytes(16)) . '.' . $allowedImages[$mimeType];
            $destination = $directory . DIRECTORY_SEPARATOR . $filename;
            if (!move_uploaded_file($upload['tmp_name'], $destination)) {
                throw new RuntimeException('Could not store the uploaded profile photo.');
            }

            try {
                $update = $pdo->prepare('UPDATE users SET profile_photo = :profile_photo WHERE id = :id');
                $update->execute(['profile_photo' => $filename, 'id' => $user['id']]);
            } catch (PDOException $exception) {
                unlink($destination);
                error_log('Profile photo database update failed: ' . $exception->getMessage());
                $error = 'The picture was uploaded but could not be saved to your profile. Please try again.';
            }

            if ($error === '') {
                $oldFilename = $user['profile_photo'] ?? '';
                if (is_string($oldFilename) && preg_match('/\A[a-f0-9]{32}\.(jpg|png|webp)\z/', $oldFilename)) {
                    $oldPath = $directory . DIRECTORY_SEPARATOR . $oldFilename;
                    if (is_file($oldPath)) {
                        unlink($oldPath);
                    }
                }
                header('Location: profile.php?saved=1');
                exit;
            }
        }
    }
}

if (($_GET['saved'] ?? '') === '1') {
    $notice = 'Your profile picture has been updated.';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My profile | Fleet Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="dashboard/shared-ui.css">
</head>
<body class="min-h-screen bg-slate-100 p-4 text-slate-800 sm:p-8">
    <main class="dashboard-content mx-auto max-w-2xl space-y-5">
        <a class="inline-flex rounded-lg bg-white px-4 py-2 text-sm font-semibold text-blue-700 shadow-sm transition hover:bg-blue-50" href="<?= $escape(dashboardPathForRole(effectiveRole($pdo, $user))) ?>">Back to dashboard</a>
        <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            <p class="text-sm font-semibold text-blue-700">Account settings</p>
            <h1 class="mt-1 text-2xl font-bold text-slate-900">My profile</h1>
            <p class="mt-2 text-sm text-slate-600">Your profile picture is visible in your signed-in workspace.</p>

            <?php if ($notice !== ''): ?>
                <p role="status" class="mt-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900"><?= $escape($notice) ?></p>
            <?php endif; ?>
            <?php if ($error !== ''): ?>
                <p role="alert" class="mt-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900"><?= $escape($error) ?></p>
            <?php endif; ?>

            <div class="mt-6 flex items-center gap-4">
                <?php if (!empty($user['profile_photo'])): ?>
                    <img class="h-20 w-20 rounded-full border border-slate-200 object-cover" src="profile-photo.php" alt="<?= $escape($user['name']) ?> profile picture">
                <?php else: ?>
                    <span class="grid h-20 w-20 place-items-center rounded-full bg-blue-100 text-2xl font-bold text-blue-800"><?= $escape(strtoupper(substr((string) $user['name'], 0, 1))) ?></span>
                <?php endif; ?>
                <div>
                    <p class="font-semibold text-slate-900"><?= $escape($user['name']) ?></p>
                    <p class="text-sm text-slate-500"><?= $escape(ucwords(str_replace('_', ' ', (string) effectiveRole($pdo, $user)))) ?></p>
                </div>
            </div>

            <form method="post" enctype="multipart/form-data" class="mt-7 space-y-4">
                <input type="hidden" name="csrf_token" value="<?= $escape(csrfToken()) ?>">
                <label class="block text-sm font-medium text-slate-700" for="profile_photo">Upload a profile picture</label>
                <input class="block w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm file:mr-4 file:rounded-md file:border-0 file:bg-blue-50 file:px-3 file:py-2 file:font-semibold file:text-blue-700" id="profile_photo" name="profile_photo" type="file" accept="image/jpeg,image/png,image/webp" required>
                <p class="text-xs leading-5 text-slate-500">JPEG, PNG, or WebP; maximum 3 MB and 5000 × 5000 pixels.</p>
                <button class="rounded-lg bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-800" type="submit">Save picture</button>
            </form>
        </section>
    </main>
</body>
</html>
