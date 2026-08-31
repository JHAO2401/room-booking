<?php
require_once __DIR__ . '/../includes/functions.php';

if (current_user()) redirect('/index.php');

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        $errors[] = 'Incorrect email or password.';
    } else {
        $_SESSION['user'] = [
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role'],
            'user_type' => $user['user_type'],
        ];
        redirect('/index.php');
    }
}

$page_title = 'Log in';
include __DIR__ . '/../includes/header.php';
?>

<div class="form-narrow panel">
  <h1>Log in</h1>
  <p class="text-muted">Welcome back.</p>

  <?php foreach ($errors as $err): ?>
    <div class="flash error"><?= e($err) ?></div>
  <?php endforeach; ?>

  <form method="post" class="form-grid" style="margin-top:16px;">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <div class="field">
      <label for="email">Email</label>
      <input type="email" id="email" name="email" value="<?= e($_POST['email'] ?? '') ?>" required>
    </div>
    <div class="field">
      <label for="password">Password</label>
      <div class="password-field">
        <input type="password" id="password" name="password" required>
        <button type="button" class="password-toggle" data-target="password" aria-label="Show password">Show</button>
      </div>
    </div>
    <button type="submit" class="btn btn-block">Log in</button>
  </form>
  <p class="text-muted" style="margin-top:16px;">No account yet? <a href="/login_register/register.php" style="text-decoration:underline;">Sign up</a></p>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
