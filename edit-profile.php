<?php
// edit-profile.php - แก้ไขข้อมูลส่วนตัว (แก้ไขการดึงอีเมลให้แสดงผลถูกต้อง)
session_start();
require_once __DIR__ . '/config.php';

// นิยามฟังก์ชัน h() เพื่อป้องกัน Error และเพิ่มความปลอดภัย
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

if (!isset($_SESSION['user_name'])) {
    header("Location: login.php");
    exit;
}

$pdo = getPDO();
$userId = $_SESSION['user_id'];
$errors = [];
$success = null;

// ดึงข้อมูลทั้งหมดของผู้ใช้มาแสดงผล (ใช้วิธี SELECT * จะดึงอีเมลมาโชว์ได้เสมอ)
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fname       = trim($_POST['first_name'] ?? '');
    $lname       = trim($_POST['last_name'] ?? '');
    $email       = trim($_POST['email'] ?? '');
    $phone       = trim($_POST['phone'] ?? '');
    $company     = trim($_POST['company_name'] ?? '');
    $address     = trim($_POST['address'] ?? '');
    $subdistrict = trim($_POST['subdistrict'] ?? '');
    $district    = trim($_POST['district'] ?? '');
    $province    = trim($_POST['province'] ?? '');
    $zipcode     = trim($_POST['zipcode'] ?? '');

    if (empty($fname) || empty($email)) {
        $errors[] = "กรุณากรอกชื่อและอีเมลให้ครบถ้วน";
    } else {
        try {
            // อัปเดตข้อมูลลง Database
            $update = $pdo->prepare("UPDATE users SET 
                first_name = ?, last_name = ?, email = ?, phone = ?, 
                company_name = ?, address = ?, subdistrict = ?, district = ?, province = ?, zipcode = ? 
                WHERE id = ?");
            
            if ($update->execute([$fname, $lname, $email, $phone, $company, $address, $subdistrict, $district, $province, $zipcode, $userId])) {
                $_SESSION['user_name'] = $fname; 
                $success = "บันทึกข้อมูลส่วนตัวเรียบร้อยแล้ว";
                
                // อัปเดตค่าที่แสดงบนฟอร์มทันที
                $user['first_name'] = $fname;
                $user['last_name'] = $lname;
                $user['email'] = $email;
                $user['phone'] = $phone;
                $user['company_name'] = $company;
                $user['address'] = $address;
                $user['subdistrict'] = $subdistrict;
                $user['district'] = $district;
                $user['province'] = $province;
                $user['zipcode'] = $zipcode;
            } else {
                $errors[] = "เกิดข้อผิดพลาดในการบันทึกข้อมูล";
            }
        } catch (PDOException $e) {
            // แจ้งเตือนถ้าตารางใน Database ยังไม่มีคอลัมน์เก็บที่อยู่
            $errors[] = "ไม่สามารถบันทึกได้! โปรดตรวจสอบว่าคุณได้เพิ่มคอลัมน์ที่อยู่ (address, subdistrict, etc.) ลงในตาราง users แล้วหรือยัง";
        }
    }
}
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>แก้ไขข้อมูลส่วนตัว - <?php echo h($user['first_name'] ?? 'User'); ?></title>
  <link rel="stylesheet" href="styles.css" />
  <style>
    .edit-page { padding: 48px 0; background: #f9fbff; min-height: 80vh; }
    .edit-layout { display: grid; grid-template-columns: 280px 1fr; gap: 32px; align-items: start; }
    
    .card { background: #fff; border-radius: 16px; padding: 32px; box-shadow: 0 12px 32px rgba(9,30,45,0.04); border: 1px solid rgba(11,47,74,0.03); }
    
    /* เมนูด้านข้าง */
    .side-menu { list-style: none; padding: 0; margin: 0; }
    .side-menu li { margin-bottom: 8px; }
    .side-menu a { display: flex; align-items: center; gap: 12px; padding: 12px 16px; text-decoration: none; color: var(--navy); font-weight: 700; border-radius: 10px; transition: 0.2s; }
    .side-menu a:hover, .side-menu a.active { background: #f0f7ff; color: var(--blue); }

    /* ฟอร์ม */
    .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 24px; }
    .form-group { display: flex; flex-direction: column; }
    
    label { display: block; font-weight: 800; margin-bottom: 8px; color: var(--navy); font-size: 0.95rem; }
    input { width: 100%; padding: 14px 16px; border-radius: 10px; border: 1px solid rgba(11,47,74,0.15); font-family: inherit; font-size: 1rem; background: #fcfcfd; box-sizing: border-box; transition: all 0.3s ease; }
    input:focus { outline: none; border-color: var(--blue); background: #fff; box-shadow: 0 0 0 4px rgba(30,144,255,0.1); }
    input::placeholder { color: #bbb; }
    
    /* ทำให้ช่องอีเมลดูเป็นสีเทานิดๆ หากต้องการแก้ไขไม่ได้ ให้เติม readonly ไปที่ช่อง input นะครับ */
    input[readonly] { background-color: #f4f6f8; color: #888; cursor: not-allowed; }

    .btn-save { background: linear-gradient(90deg, var(--blue), var(--teal)); color: #fff; border: 0; padding: 16px 36px; border-radius: 10px; cursor: pointer; font-weight: 800; font-size: 1.05rem; box-shadow: 0 8px 20px rgba(30,144,255,0.2); transition: 0.2s; margin-top: 10px;}
    .btn-save:hover { transform: translateY(-2px); box-shadow: 0 12px 24px rgba(30,144,255,0.3); }

    /* Danger Zone */
    .danger-zone { margin-top: 48px; padding-top: 32px; border-top: 2px dashed #f0f2f5; }
    .btn-delete { display: inline-flex; align-items: center; gap: 8px; color: #ff4d4f; background: #fff1f0; border: 1px solid rgba(255,77,79,0.2); padding: 12px 24px; border-radius: 10px; cursor: pointer; font-weight: 700; text-decoration: none; font-size: 0.95rem; transition: 0.2s; }
    .btn-delete:hover { background: #ff4d4f; color: #fff; }

    .icon-primary { color: var(--blue); }

    /* Responsive */
    @media (max-width: 900px) { 
        .edit-layout { grid-template-columns: 1fr; } 
    }
    @media (max-width: 600px) {
        .form-grid { grid-template-columns: 1fr; gap: 16px; } 
    }
  </style>
</head>
<body>
  <?php if (file_exists(__DIR__ . '/navbar.php')) include __DIR__ . '/navbar.php'; ?>

  <main class="container edit-page">
    <div class="edit-layout">
      
      <aside class="card" style="padding: 24px;">
        <ul class="side-menu">
          <li><a href="dashboard.php">หน้าจัดการบัญชี</a></li>
          <li><a href="edit-profile.php" class="active">แก้ไขข้อมูลส่วนตัว</a></li>
          <li><a href="order.php">ประวัติการสั่งซื้อ</a></li>
          <li><a href="change-password.php">เปลี่ยนรหัสผ่าน</a></li>
        </ul>
      </aside>

      <div class="card">
        <h2 style="margin-top:0; font-weight:900; color:var(--navy); display:flex; align-items:center; gap:12px; margin-bottom: 28px;">
          <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="icon-primary"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 1 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
          จัดการข้อมูลส่วนตัว
        </h2>

        <?php if(!empty($errors)): ?>
          <div style="background:#fff7f7; color:#ff4d4f; padding:16px; border-radius:12px; margin-bottom:24px; font-weight:700; border:1px solid rgba(255,77,79,0.2);">
            <?php echo implode('<br>', $errors); ?>
          </div>
        <?php endif; ?>

        <?php if($success): ?>
          <div style="background:#f6fff6; color:#127a3b; padding:16px; border-radius:12px; margin-bottom:24px; font-weight:700; border:1px solid rgba(18,122,59,0.1); display:flex; align-items:center; gap:10px;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
            <?php echo h($success); ?>
          </div>
        <?php endif; ?>

        <form method="post" autocomplete="off">
          
          <div class="form-grid">
            <div class="form-group">
              <label>ชื่อจริง</label>
              <input type="text" name="first_name" value="<?php echo h($user['first_name'] ?? ''); ?>" required placeholder="ชื่อ">
            </div>
            <div class="form-group">
              <label>นามสกุล</label>
              <input type="text" name="last_name" value="<?php echo h($user['last_name'] ?? ''); ?>" placeholder="นามสกุล">
            </div>
          </div>

          <div class="form-grid">
            <div class="form-group">
              <label>ชื่อบริษัท (ไม่บังคับ)</label>
              <input type="text" name="company_name" value="<?php echo h($user['company_name'] ?? ''); ?>" placeholder="กรอกชื่อบริษัท">
            </div>
            <div class="form-group">
              <label>อีเมลติดต่อ (Email)</label>
              <input type="email" name="email" value="<?php echo h($user['email'] ?? ''); ?>" required placeholder="example@mail.com">
            </div>
          </div>

          <div class="form-grid">
            <div class="form-group">
              <label>เบอร์โทรศัพท์</label>
              <input type="text" name="phone" value="<?php echo h($user['phone'] ?? ''); ?>" placeholder="08x-xxx-xxxx">
            </div>
            <div class="form-group">
              <label>ที่อยู่ปัจจุบัน</label>
              <input type="text" name="address" value="<?php echo h($user['address'] ?? ''); ?>" placeholder="บ้านเลขที่, หมู่, ซอย, ถนน">
            </div>
          </div>

          <div class="form-grid">
            <div class="form-group">
              <label>ตำบล / แขวง</label>
              <input type="text" name="subdistrict" value="<?php echo h($user['subdistrict'] ?? ''); ?>" placeholder="ตำบล / แขวง">
            </div>
            <div class="form-group">
              <label>อำเภอ / เขต</label>
              <input type="text" name="district" value="<?php echo h($user['district'] ?? ''); ?>" placeholder="อำเภอ / เขต">
            </div>
          </div>

          <div class="form-grid">
            <div class="form-group">
              <label>จังหวัด</label>
              <input type="text" name="province" value="<?php echo h($user['province'] ?? ''); ?>" placeholder="จังหวัด">
            </div>
            <div class="form-group">
              <label>รหัสไปรษณีย์</label>
              <input type="text" name="zipcode" value="<?php echo h($user['zipcode'] ?? ''); ?>" placeholder="รหัสไปรษณีย์">
            </div>
          </div>

          <button type="submit" class="btn-save">บันทึกการเปลี่ยนแปลง</button>
        </form>

        <div class="danger-zone">
          <h4 style="color: #ff4d4f; margin: 0 0 8px 0; font-weight: 800; display: flex; align-items: center; gap: 8px;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
            พื้นที่อันตราย
          </h4>
          <p style="color: var(--muted); font-size: 0.9rem; margin-bottom: 20px;">เมื่อดำเนินการลบบัญชีแล้ว ข้อมูลและประวัติทั้งหมดจะถูกลบอย่างถาวรและไม่สามารถกู้คืนได้</p>
          <a href="delete-account.php" class="btn-delete" onclick="return confirm('❗ ยืนยันการลบบัญชีอย่างถาวร?\nข้อมูลทั้งหมดของคุณจะหายไปและไม่สามารถกู้คืนได้อีก');">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
            ลบบัญชีผู้ใช้งาน
          </a>
        </div>
      </div>

    </div>
  </main>

  <?php if (file_exists(__DIR__ . '/footer.php')) include __DIR__ . '/footer.php'; ?>
  <script src="script.js"></script>
</body>
</html>