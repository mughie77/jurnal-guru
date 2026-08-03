<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

authorize_role(['admin']);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Metode request tidak didukung.']);
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Token CSRF tidak valid.']);
    exit;
}

$action = $_POST['action'] ?? '';
$output = [];
$return_var = 0;

switch ($action) {
    case 'status':
        exec('git status 2>&1', $output, $return_var);
        exec('git log -1 --oneline 2>&1', $log_output);
        $output_str = implode("\n", $output);
        if (!empty($log_output)) {
            $output_str .= "\n\nLast Commit:\n" . implode("\n", $log_output);
        }
        echo json_encode([
            'success' => $return_var === 0,
            'message' => $return_var === 0 ? 'Status Git berhasil diambil.' : 'Gagal mengambil status Git.',
            'log' => $output_str
        ]);
        break;

    case 'pull':
        exec('git pull origin $(git rev-parse --abbrev-ref HEAD) 2>&1', $output, $return_var);
        echo json_encode([
            'success' => $return_var === 0,
            'message' => $return_var === 0 ? 'Pembaruan berhasil ditarik dari GitHub!' : 'Gagal menarik pembaruan.',
            'log' => implode("\n", $output)
        ]);
        break;

    case 'push':
        $commit_msg = trim($_POST['commit_message'] ?? '');
        if ($commit_msg === '') {
            $commit_msg = "Pembaruan otomatis dari Panel Pengaturan Sistem - " . date('Y-m-d H:i:s');
        }

        // Run staging, commit, and push in sequence
        $commands = [
            'git add . 2>&1',
            'git commit -m "' . escapeshellcmd($commit_msg) . '" 2>&1',
            'git push origin $(git rev-parse --abbrev-ref HEAD) 2>&1'
        ];

        $all_outputs = [];
        $overall_success = true;

        foreach ($commands as $cmd) {
            $cmd_output = [];
            $cmd_return = 0;
            exec($cmd, $cmd_output, $cmd_return);
            $all_outputs[] = "> " . $cmd;
            $all_outputs[] = implode("\n", $cmd_output);

            // Note: If commit has nothing to commit, return code might be non-zero (1). We can tolerate that if git push succeeds or if there are no changes.
            if ($cmd_return !== 0 && strpos($cmd, 'git commit') === false) {
                $overall_success = false;
            }
        }

        echo json_encode([
            'success' => $overall_success,
            'message' => $overall_success ? 'Pembaruan berhasil dikirim ke GitHub!' : 'Ada kendala saat mengirim pembaruan ke GitHub.',
            'log' => implode("\n", $all_outputs)
        ]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Aksi Git tidak valid.']);
        break;
}
