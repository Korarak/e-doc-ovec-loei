<?php
$user_id = $_SESSION['user_id'];
$query = "SELECT u.fullname, u.username, p.position_name, r.role_name, d.dep_name
          FROM user u
          LEFT JOIN position p ON u.position_id = p.position_id
          LEFT JOIN role r ON u.role_id = r.role_id
          LEFT JOIN department d ON u.dep_id = d.dep_id
          WHERE u.user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($fullname, $username, $position_name, $role_name, $dep_name);
$stmt->fetch();
$stmt->close();
?>
<div class="bg-white rounded-xl shadow-sm border border-gray-100 px-5 py-4 mb-6 flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-6">
    <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-full bg-brand-100 flex items-center justify-center text-brand-700 font-bold text-lg">
            <?php echo mb_substr($fullname ?? '', 0, 1); ?>
        </div>
        <div>
            <div class="font-semibold text-gray-800"><?php echo htmlspecialchars($fullname ?? ''); ?></div>
            <div class="text-xs text-gray-400"><?php echo htmlspecialchars($username ?? ''); ?></div>
        </div>
    </div>
    <div class="flex flex-wrap gap-2 sm:gap-4 text-sm text-gray-500">
        <span class="inline-flex items-center gap-1"><i class="bi bi-person-badge text-brand-500"></i> <?php echo htmlspecialchars($position_name ?? 'ไม่ระบุ'); ?></span>
        <span class="inline-flex items-center gap-1"><i class="bi bi-building text-sky-500"></i> <?php echo htmlspecialchars($dep_name ?? 'ไม่ระบุ'); ?></span>
        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-brand-50 text-brand-700 text-xs font-medium"><?php echo htmlspecialchars($role_name ?? 'ไม่ระบุ'); ?></span>
    </div>
</div>