<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="LoeiTech E-Sign System — ระบบสารบรรณอิเล็กทรอนิกส์">
    <title>LoeiTech E-Sign</title>
    <link rel="icon" type="image/x-icon" href="images/logo/loeitech-logo.ico">

    <!-- TailwindCSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Kanit', 'sans-serif'] },
                    colors: {
                        brand:   { 50:'#f0fdf4', 100:'#dcfce7', 200:'#bbf7d0', 300:'#86efac', 400:'#4ade80', 500:'#22c55e', 600:'#16a34a', 700:'#15803d', 800:'#166534', 900:'#14532d' },
                        sidebar: '#1e293b',
                        sidebarHover: '#334155',
                    }
                }
            }
        }
    </script>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Bootstrap Icons (icon font only) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <!-- DataTables CSS (unstyled version — no bootstrap dependency) -->
    <link href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css" rel="stylesheet">

    <!-- SweetAlert2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.min.css" rel="stylesheet">

    <style>
        /* DataTables overrides for Tailwind look */
        .dataTables_wrapper { font-family: 'Kanit', sans-serif; }
        .dataTables_wrapper .dataTables_filter input {
            border: 1px solid #d1d5db; border-radius: 0.5rem; padding: 0.4rem 0.75rem; outline: none;
            font-size: 0.875rem;
        }
        .dataTables_wrapper .dataTables_filter input:focus {
            border-color: #22c55e; box-shadow: 0 0 0 2px rgba(34,197,94,0.2);
        }
        .dataTables_wrapper .dataTables_length select {
            border: 1px solid #d1d5db; border-radius: 0.5rem; padding: 0.3rem 0.5rem; outline: none;
            font-size: 0.875rem;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button {
            border: 1px solid #e5e7eb !important; border-radius: 0.375rem !important; margin: 0 2px !important;
            padding: 4px 10px !important; font-size: 0.8rem !important;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: #22c55e !important; border-color: #22c55e !important; color: white !important;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            background: #f0fdf4 !important; border-color: #22c55e !important; color: #15803d !important;
        }
        .dataTables_wrapper .dataTables_info { font-size: 0.8rem; color: #6b7280; }
        table.dataTable thead th { border-bottom: none !important; }
        table.dataTable tbody td { border-top: none !important; }
        
        /* Tablet optimization */
        @media (min-width: 1024px) and (max-width: 1279px) {
            .tablet-grid { grid-template-columns: repeat(2, minmax(0, 1fr)) !important; }
        }
    </style>
</head>
<body class="bg-gray-50 font-sans min-h-screen flex flex-col">

    <!-- ── Mobile/Tablet Top Bar ── -->
    <header class="xl:hidden bg-sidebar text-white flex items-center justify-between px-4 py-3 sticky top-0 z-40 shadow-lg">
        <a href="dashboard.php" class="flex items-center gap-2 text-white font-bold text-lg">
            <i class="bi bi-shield-check text-brand-400"></i> E-Sign System
        </a>
        <button id="sidebarToggle" class="text-white p-2 rounded-lg hover:bg-sidebarHover transition-colors">
            <i class="bi bi-list text-2xl"></i>
        </button>
    </header>

    <div class="flex flex-1 overflow-hidden">
        <!-- ── Sidebar ── -->
        <aside id="sidebar" class="fixed xl:sticky top-0 left-0 z-50 xl:z-0 h-screen w-64 bg-sidebar text-white flex flex-col transform -translate-x-full xl:translate-x-0 transition-transform duration-300 ease-in-out flex-shrink-0">

            <!-- Sidebar Header -->
            <div class="px-5 py-5 border-b border-slate-700/50 flex items-center justify-between flex-shrink-0">
                <a href="dashboard.php" class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-lg bg-brand-500 flex items-center justify-center shadow-lg">
                        <i class="bi bi-shield-check text-white text-lg"></i>
                    </div>
                    <div>
                        <div class="font-bold text-sm text-white">E-Sign System</div>
                        <div class="text-[11px] text-slate-400"><?php echo htmlspecialchars($_SESSION['inst_name'] ?? 'สถาบัน'); ?></div>
                    </div>
                </a>
                <!-- Close button for mobile/tablet -->
                <button id="sidebarClose" class="xl:hidden text-slate-400 hover:text-white">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>

            <!-- Sidebar Navigation -->
            <div class="flex-1 overflow-y-auto px-3 py-4 space-y-1">
                <?php include "base_sidebarmenu.php" ?>
            </div>

            <!-- Sidebar Footer -->
            <div class="px-4 py-4 border-t border-slate-700/50 flex-shrink-0">
                <?php if (isset($_SESSION['fullname'])): ?>
                <div class="flex items-center gap-3 mb-3">
                    <div class="w-8 h-8 rounded-full bg-brand-600 flex items-center justify-center text-white text-sm font-bold">
                        <?php echo mb_substr($_SESSION['fullname'] ?? 'U', 0, 1); ?>
                    </div>
                    <div class="min-w-0">
                        <div class="text-sm font-medium text-white truncate"><?php echo htmlspecialchars($_SESSION['fullname'] ?? ''); ?></div>
                        <div class="text-xs text-slate-400">ออนไลน์</div>
                    </div>
                </div>
                <?php endif; ?>
                <a href="logout.php" onclick="confirmLogout(event, 'logout.php')" class="flex items-center gap-2 px-3 py-2 text-sm text-red-400 hover:text-red-300 hover:bg-red-500/10 rounded-lg transition-colors w-full">
                    <i class="bi bi-box-arrow-right"></i> ออกจากระบบ
                </a>
            </div>
        </aside>

        <!-- Sidebar Overlay (mobile/tablet) -->
        <div id="sidebarOverlay" class="fixed inset-0 bg-black/50 z-40 hidden xl:hidden"></div>

        <!-- ── Main Content ── -->
        <main class="flex-1 overflow-y-auto">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
                <?php echo $content ?? ''; ?>
            </div>

            <!-- Footer -->
            <footer class="border-t border-gray-200 mt-8 py-4 text-center text-sm text-gray-400">
                &copy; <?php echo date('Y'); ?> Loei Technical College E-Sign System
            </footer>
        </main>
    </div>

    <!-- ── Scripts ── -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>

    <script>
    // Sidebar toggle (mobile/tablet)
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const toggleBtn = document.getElementById('sidebarToggle');
    const closeBtn = document.getElementById('sidebarClose');

    function openSidebar() {
        sidebar.classList.remove('-translate-x-full');
        overlay.classList.remove('hidden');
    }
    function closeSidebar() {
        sidebar.classList.add('-translate-x-full');
        overlay.classList.add('hidden');
    }

    if (toggleBtn) toggleBtn.addEventListener('click', openSidebar);
    if (closeBtn) closeBtn.addEventListener('click', closeSidebar);
    if (overlay) overlay.addEventListener('click', closeSidebar);

    // DataTables init
    $(document).ready(function() {
        if ($.fn.DataTable) {
            $('.datatable').DataTable({
                "language": {
                    "url": "https://cdn.datatables.net/plug-ins/1.13.6/i18n/th.json"
                },
                "dom": '<"flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-4"lf>rt<"flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mt-4"ip>',
            });
        }

        // SweetAlert for success/error URL params
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('msg') === 'success') {
            Swal.fire({ icon: 'success', title: 'สำเร็จ', text: 'ดำเนินการเสร็จสิ้นเรียบร้อย', timer: 2000, showConfirmButton: false });
            window.history.replaceState({}, document.title, window.location.pathname);
        } else if (urlParams.get('error') === 'unauthorized') {
            Swal.fire({ icon: 'error', title: 'ไม่ได้รับสิทธิ์', text: 'คุณไม่มีสิทธิ์เข้าถึงหน้านี้', timer: 3000, showConfirmButton: false });
            window.history.replaceState({}, document.title, window.location.pathname);
        } else if (urlParams.get('error') === 'csrf_error') {
            Swal.fire({ icon: 'error', title: 'เซสชันหมดอายุ', text: 'กรุณาลองใหม่อีกครั้ง (CSRF Mismatch)', timer: 3000, showConfirmButton: false });
            window.history.replaceState({}, document.title, window.location.pathname);
        } else if (urlParams.get('error') === 'db_error') {
            Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: 'ไม่สามารถบันทึกข้อมูลได้ (อาจมีข้อมูลซ้ำ)', timer: 3000, showConfirmButton: false });
            window.history.replaceState({}, document.title, window.location.pathname);
        } else if (urlParams.get('error') === 'fk_error') {
            Swal.fire({ icon: 'error', title: 'ไม่สามารถลบข้อมูลได้', text: 'ข้อมูลนี้ถูกใช้งานอยู่ในส่วนอื่นของระบบ ไม่สามารถลบได้ในขณะนี้', confirmButtonText: 'ตกลง' });
            window.history.replaceState({}, document.title, window.location.pathname);
        } else if (urlParams.get('msg') === 'error') {
            const reason = urlParams.get('reason');
            let msgs = 'เกิดข้อผิดพลาดในการทำรายการ';
            if (reason === 'invalid_type') msgs = 'รองรับเฉพาะไฟล์รูปภาพ PNG หรือ JPG เท่านั้น';
            else if (reason === 'move_failed') msgs = 'ไม่สามารถอัปโหลดไฟล์เข้าระบบได้ (อาจเกิดจากการตั้งค่าโฟลเดอร์ใน Docker)';
            else if (reason === 'upload_err') msgs = 'ไฟล์มีขนาดใหญ่เกินไป หรือเกิดการขัดข้องขณะอัปโหลด';
            
            Swal.fire({ icon: 'error', title: 'ผิดพลาด', text: msgs, timer: 3000, showConfirmButton: false });
            window.history.replaceState({}, document.title, window.location.pathname);
        }
    });

    // Global helper functions
    function confirmDelete(url) {
        Swal.fire({
            title: 'คุณแน่ใจหรือไม่?', text: "คุณจะไม่สามารถกู้คืนข้อมูลนี้ได้!", icon: 'warning',
            showCancelButton: true, confirmButtonColor: '#ef4444', cancelButtonColor: '#6b7280',
            confirmButtonText: 'ใช่, ลบเลย!', cancelButtonText: 'ยกเลิก'
        }).then((r) => { if (r.isConfirmed) window.location.href = url; });
    }

    function confirmLogout(event, url) {
        event.preventDefault();
        Swal.fire({
            title: 'ออกจากระบบ?', text: "คุณต้องการออกจากระบบใช่หรือไม่", icon: 'question',
            showCancelButton: true, confirmButtonColor: '#ef4444', cancelButtonColor: '#6b7280',
            confirmButtonText: 'ออกจากระบบ', cancelButtonText: 'ยกเลิก'
        }).then((r) => { if (r.isConfirmed) window.location.href = url; });
    }

    function confirmDeleteForm(formId) {
        Swal.fire({
            title: 'คุณแน่ใจหรือไม่?', text: "คุณกำลังลบข้อมูลนี้ ห้ามกู้คืน!", icon: 'warning',
            showCancelButton: true, confirmButtonColor: '#ef4444', cancelButtonColor: '#6b7280',
            confirmButtonText: 'ใช่, ลบเลย!', cancelButtonText: 'ยกเลิก'
        }).then((r) => { if (r.isConfirmed) document.getElementById(formId).submit(); });
    }

    // Modal helpers (vanilla JS replacement for Bootstrap modals)
    function openModal(id) {
        const modal = document.getElementById(id);
        if (modal) {
            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }
    }
    function closeModal(id) {
        const modal = document.getElementById(id);
        if (modal) {
            modal.classList.add('hidden');
            document.body.style.overflow = '';
        }
    }
    // Close modal on overlay click
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal-overlay')) {
            e.target.classList.add('hidden');
            document.body.style.overflow = '';
        }
    });
    // Close modal on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal-overlay:not(.hidden)').forEach(m => {
                m.classList.add('hidden');
            });
            document.body.style.overflow = '';
        }
    });
    </script>
</body>
</html>