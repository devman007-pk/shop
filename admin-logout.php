<?php
// admin-logout.php - หน้าสำหรับออกจากระบบแอดมิน
session_start();

// ลบตัวแปร Session ทั้งหมด
$_SESSION = array();

// ถ้ามีการใช้คุกกี้ Session ให้ลบทิ้งด้วย
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// ทำลาย Session
session_destroy();

// เด้งกลับไปหน้าล็อกอินแอดมิน
header("Location: admin-login.php");
exit;
?>