<?php
require_once __DIR__ . '/../includes/functions.php';

if (current_user()) redirect('/index.php');

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm'] ?? '';
    $user_type = $_POST['user_type'] ?? '';

    if ($name === '' || $email === '' || $password === '') {
        $errors[] = 'Please fill in all fields.';
    }
    if (!in_array($user_type, ['student', 'faculty'], true)) {
        $errors[] = 'Please choose whether you are registering as a Student or Faculty.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }

    if (!$errors) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = 'An account with this email already exists.';
        }
    }

    if (!$errors) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash, role, user_type) VALUES (?, ?, ?, "user", ?)');
        $stmt->execute([$name, $email, $hash, $user_type]);
        flash_set('Account created. Please log in.', 'success');
        redirect('/login_register/login.php');
    }
}

$page_title = 'Sign up';
include __DIR__ . '/../includes/header.php';
?>

<div class="form-narrow panel">
  <h1>Create an account</h1>
  <p class="text-muted">Sign up to book discussion rooms.</p>

  <?php foreach ($errors as $err): ?>
    <div class="flash error"><?= e($err) ?></div>
  <?php endforeach; ?>

  <form method="post" class="form-grid" style="margin-top:16px;">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <div class="field">
      <label for="name">Full name</label>
      <input type="text" id="name" name="name" value="<?= e($_POST['name'] ?? '') ?>" required>
    </div>
    <div class="field">
      <label for="email">Email</label>
      <input type="email" id="email" name="email" value="<?= e($_POST['email'] ?? '') ?>" required>
    </div>
    <div class="field">
      <label for="password">Password</label>
      <div class="password-field">
        <input type="password" id="password" name="password" required minlength="8">
        <button type="button" class="password-toggle" data-target="password" aria-label="Show password">Show</button>
      </div>
    </div>
    <div class="field">
      <label for="confirm">Confirm password</label>
      <div class="password-field">
        <input type="password" id="confirm" name="confirm" required minlength="8">
        <button type="button" class="password-toggle" data-target="confirm" aria-label="Show password">Show</button>
      </div>
    </div>
    <div class="field">
      <label>Register as</label>
      <div class="role-select">
        <div class="role-option">
          <input type="radio" id="role_student" name="user_type" value="student" <?= (($_POST['user_type'] ?? 'student') === 'student') ? 'checked' : '' ?>>
          <label for="role_student"><span class="role-icon">🎓</span> Student</label>
        </div>
        <div class="role-option">
          <input type="radio" id="role_faculty" name="user_type" value="faculty" <?= (($_POST['user_type'] ?? '') === 'faculty') ? 'checked' : '' ?>>
          <label for="role_faculty"><span class="role-icon">🧑‍🏫</span> Faculty</label>
        </div>
      </div>
      <span class="text-muted" style="font-size:0.78rem;">Only Faculty accounts can book internal-only rooms.</span>
    </div>
    <button type="submit" class="btn btn-block">Create account</button>
  </form>
  <p class="text-muted" style="margin-top:16px;">Already have an account? <a href="/login_register/login.php" style="text-decoration:underline;">Log in</a></p>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
