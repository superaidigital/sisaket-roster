<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>เปลี่ยนรหัสผ่านเริ่มต้น — ระบบจัดตารางเวร</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Kanit:wght@500;600&family=Sarabun:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="css/roster.css">
</head>
<body class="login-body">
<main class="login-card shadow-sm">
  <div class="brand-bubble" aria-hidden="true">✚</div>
  <h1 class="h4 fw-bold">เปลี่ยนรหัสผ่าน</h1>
  <p class="text-secondary">ระบบจัดการตารางปฏิบัติงาน รพ.สต. อบจ.ศรีสะเกษ</p>
  <?php if ((int)$user['must_change_password'] === 1): ?>
  <div class="alert alert-warning" role="status">บัญชีนี้ใช้รหัสผ่านเริ่มต้นสำหรับทดสอบ กรุณาตั้งรหัสผ่านใหม่ก่อนเข้าสู่ระบบจัดตารางเวร</div>
  <?php endif; ?>
  <?php if ($error): ?><div class="alert alert-danger" role="alert"><?=e($error)?></div><?php endif; ?>
  <p class="small text-start">บัญชี: <strong><?=e($user['email'])?></strong></p>
  <form method="post" action="index.php?c=auth&amp;a=do_change_password">
    <input type="hidden" name="csrf_token" value="<?=e(\App\Core\Csrf::token())?>">
    <div class="mb-3"><label for="current_password" class="form-label">รหัสผ่านเดิม</label><input class="form-control" id="current_password" name="current_password" type="password" autocomplete="current-password" required autofocus></div>
    <div class="mb-3"><label for="new_password" class="form-label">รหัสผ่านใหม่ (อย่างน้อย 12 ตัวอักษร)</label><input class="form-control" id="new_password" name="new_password" type="password" minlength="12" maxlength="128" autocomplete="new-password" required></div>
    <div class="mb-3"><label for="confirm_password" class="form-label">ยืนยันรหัสผ่านใหม่</label><input class="form-control" id="confirm_password" name="confirm_password" type="password" minlength="12" maxlength="128" autocomplete="new-password" required></div>
    <button class="btn btn-primary w-100" type="submit">บันทึกรหัสผ่านใหม่และเข้าสู่ระบบ</button>
  </form>
  <form class="mt-3 text-center" method="post" action="index.php?c=auth&amp;a=logout">
    <input type="hidden" name="csrf_token" value="<?=e(\App\Core\Csrf::token())?>">
    <button class="btn btn-link btn-sm text-secondary" type="submit">ออกจากระบบ</button>
  </form>
</main>
</body>
</html>
