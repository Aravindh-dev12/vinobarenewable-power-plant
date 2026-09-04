<?php
require_once __DIR__ . '/check_auth.php';
require_once __DIR__ . '/plant_config.php';
$currentPlant = normalize_plant_id($_GET['plant'] ?? ($user['plant_id'] ?? 'vinoba-1'));
if (!is_valid_plant_id($currentPlant)) $currentPlant = 'vinoba-1';
$plantInfo = plant_info($currentPlant) ?? plant_catalog()['vinoba-1'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($plantInfo['name']); ?> - Reports</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="dashboard-ui.css?v=8" data-dashboard-ui>
    <script src="sidebar-control.js?v=10" defer></script>
</head>
<body class="h-full bg-slate-50 text-slate-800 font-sans">
<div class="min-h-screen flex relative">
    <div id="overlay" class="fixed inset-0 bg-slate-900/40 hidden z-30 md:hidden"></div>
    <div id="sidebar-container"></div>
    <main class="flex-1 flex flex-col w-full md:ml-64 overflow-x-hidden">
        <header class="bg-white p-4 sm:px-6 flex items-center gap-3 sticky top-0 z-20 border-b border-slate-200 shadow-sm">
            <button id="menuBtn" class="md:hidden text-emerald-600 text-2xl" aria-label="Open menu">&#9776;</button>
            <h2 class="text-xl font-black text-slate-800 tracking-tight">Reports</h2>
        </header>
        <div class="p-4 sm:p-6 w-full max-w-[1200px] mx-auto flex-1 flex items-center justify-center">
            <section class="w-full max-w-xl bg-white rounded-2xl border border-slate-200 shadow-sm p-8 sm:p-10 text-center">
                <div class="w-16 h-16 mx-auto rounded-2xl bg-amber-50 border border-amber-200 text-amber-600 flex items-center justify-center text-2xl"><i class="fa-solid fa-screwdriver-wrench"></i></div>
                <h1 class="text-2xl font-black mt-5">Reports Under Development</h1>
                <p class="text-sm text-slate-500 mt-3">The report module is temporarily disabled while the next report format is being prepared.</p>
                <div class="mt-6 text-xs font-bold text-slate-500 bg-slate-50 rounded-lg border border-slate-200 px-4 py-3"><?php echo htmlspecialchars($plantInfo['name']); ?></div>
            </section>
        </div>
    </main>
</div>
</body>
</html>
