<?php
$password_hash = '';
$plain_password = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && !empty($_POST['password'])) {
    $plain_password = $_POST['password'];
    // Membuat hash dari password menggunakan algoritma default (saat ini BCRYPT)
    $password_hash = password_hash($plain_password, PASSWORD_DEFAULT);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PHP Password Hash Generator</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f4f7f6;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            font-family: sans-serif;
        }
        .container {
            max-width: 600px;
        }
        .card {
            border: none;
            border-radius: 1rem;
            box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.1);
        }
        .word-wrap {
            word-wrap: break-word;
            background-color: #e9ecef;
            padding: 1rem;
            border-radius: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-body p-5">
                <h3 class="card-title text-center mb-4">Password Hash Generator</h3>
                <form action="hash_test.php" method="POST">
                    <div class="mb-3">
                        <label for="password" class="form-label">Masukkan Password:</label>
                        <input type="text" class="form-control" id="password" name="password" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Generate Hash</button>
                </form>

                <?php if ($password_hash): ?>
                <div class="mt-5">
                    <hr>
                    <h4 class="mt-4">Hasil:</h4>
                    <p><strong>Password Asli:</strong> <?= htmlspecialchars($plain_password) ?></p>
                    <p><strong>Hash yang Dihasilkan (untuk disalin ke database):</strong></p>
                    <div class="p-3 bg-light border rounded word-wrap">
                        <code><?= htmlspecialchars($password_hash) ?></code>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>