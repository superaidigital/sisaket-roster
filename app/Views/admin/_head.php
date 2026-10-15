<!doctype html>
<html lang="th"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=e($pageTitle ?? 'จัดการข้อมูลตารางเวร')?> | อบจ.ศรีสะเกษ</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Kanit:wght@500;600&family=Sarabun:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/roster.css"><link rel="stylesheet" href="css/admin.css">
</head><body>
<header class="app-topbar admin-topbar">
  <div class="d-flex align-items-center gap-3"><div class="brand-bubble small-brand">✚</div>
    <div><div class="app-title">ระบบตารางปฏิบัติงาน</div><small class="text-secondary">จัดการข้อมูลหน่วยบริการและผู้ปฏิบัติงาน</small></div>
  </div>
  <div class="d-flex align-items-center gap-2 flex-wrap">
    <span class="small user-label"><i class="bi bi-person-circle"></i> <?=e($user['full_name'])?> (<?=e($user['role'])?>)</span>
    <a class="btn btn-outline-primary btn-sm" href="index.php?c=roster&a=index"><i class="bi bi-calendar3"></i> กลับตารางเวร</a>
    <form action="index.php?c=auth&a=logout" method="post" class="m-0"><input type="hidden" name="csrf_token" value="<?=e(\App\Core\Csrf::token())?>"><button class="btn btn-outline-secondary btn-sm"><i class="bi bi-box-arrow-right"></i> ออก</button></form>
  </div>
</header>
<main class="admin-main container-fluid">
  <div class="admin-heading"><div><div class="eyebrow">ROSTER ADMINISTRATION</div><h1><?=e($pageTitle ?? 'จัดการข้อมูล')?></h1>
    <p class="text-secondary mb-0">สำหรับหน่วยบริการ รพ.สต. ในสังกัดองค์การบริหารส่วนจังหวัดศรีสะเกษ</p></div>
  </div>
  <nav class="admin-nav mb-3" aria-label="เมนูจัดการข้อมูล">
    <a href="index.php?c=admin&a=index" class="<?=($active ?? '')==='dashboard'?'active':''?>"><i class="bi bi-grid"></i> ภาพรวม</a>
    <a href="index.php?c=admin&a=staff" class="<?=($active ?? '')==='staff'?'active':''?>"><i class="bi bi-people"></i> จัดการบุคลากร</a>
    <a href="index.php?c=admin&a=staff_import" class="<?=($active ?? '')==='staff_import'?'active':''?>"><i class="bi bi-file-earmark-arrow-up"></i> นำเข้าบุคลากร</a>
    <a href="index.php?c=admin&a=hospitals" class="<?=($active ?? '')==='hospitals'?'active':''?>"><i class="bi bi-hospital"></i> จัดการหน่วยบริการ</a>
    <a href="index.php?c=duty&amp;a=index"><i class="bi bi-calendar2-week"></i> เวร ย/บ/ร (DEV)</a>
    <a href="index.php?c=duty_rates&amp;a=index" class="<?=($active ?? '')==='duty_rates'?'active':''?>"><i class="bi bi-cash-coin"></i> ข้อเสนออัตราค่าตอบแทน</a>
    <a href="index.php?c=admin&a=skills" class="<?=($active ?? '')==='skills'?'active':''?>"><i class="bi bi-award"></i> ทักษะเวร</a>
  </nav>
  <?php if(!empty($flash)): ?><div class="alert alert-<?=e($flash['type']==='success'?'success':'danger')?> d-flex align-items-start gap-2" role="alert">
    <i class="bi <?=($flash['type']==='success'?'bi-check-circle':'bi-exclamation-triangle')?>"></i><span><?=e($flash['message'])?></span></div><?php endif; ?>
