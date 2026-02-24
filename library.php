<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Health Library - WBMS-BHS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php include_once 'includes/tailwind_config.php'; ?>
</head>
<body class="bg-slate-50 min-h-full font-inter">
    <?php include_once 'includes/header.php'; ?>
    
    <div class="flex flex-col lg:flex-row min-h-[calc(100vh-4rem)]">
        <?php include_once 'includes/sidebar.php'; ?>
        
        <main class="flex-1 p-4 lg:p-8 space-y-8">
            <!-- Library Header -->
            <header class="bg-white p-8 rounded-[2.5rem] border border-slate-100 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-6">
                <div class="space-y-2">
                    <h1 class="text-3xl font-black text-slate-900 tracking-tight">Health Library</h1>
                    <p class="text-slate-500 text-sm font-medium">Validated health resources for mothers and caregivers</p>
                    <div class="inline-flex items-center gap-2 bg-emerald-50 px-3 py-1 rounded-full border border-emerald-100 mt-2">
                        <i class="fas fa-check-circle text-emerald-600 text-[10px]"></i>
                        <span class="text-[10px] font-black text-emerald-700 uppercase tracking-tighter">Official DOH Verified</span>
                    </div>
                </div>
            </header>

            <!-- Search Section -->
            <section class="bg-white p-6 rounded-[2rem] border border-slate-100 shadow-sm">
                <form method="GET" class="flex flex-col md:flex-row gap-4">
                    <div class="relative flex-1">
                        <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                        <input type="text" name="search" placeholder="Search resources (e.g., Nutrition, Vaccines...)" value="<?= htmlspecialchars($search) ?>" class="w-full bg-slate-50 border-none rounded-2xl py-4 pl-12 pr-4 text-sm font-bold focus:ring-2 focus:ring-health-600 transition-all">
                    </div>
                    <button type="submit" class="bg-health-600 text-white px-8 py-4 rounded-2xl font-black text-sm uppercase tracking-widest shadow-lg shadow-health-100 hover:bg-health-700 transition-all active:scale-95">
                        Filter Library
                    </button>
                    <?php if($search): ?>
                        <a href="library.php" class="flex items-center justify-center px-4 py-4 rounded-2xl bg-slate-100 text-slate-600 hover:bg-slate-200 transition-all">
                            <i class="fas fa-times"></i>
                        </a>
                    <?php endif; ?>
                </form>
            </section>

            <!-- Resources Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-8">
                <?php if (!empty($resources)): ?>
                    <?php foreach ($resources as $res): ?>
                    <div class="group bg-white rounded-[2.5rem] border border-slate-100 shadow-sm hover:shadow-xl hover:-translate-y-1 transition-all duration-500 overflow-hidden flex flex-col">
                        <div class="p-8 space-y-6 flex-1 flex flex-col">
                            <div class="flex justify-between items-start">
                                <div class="w-14 h-14 rounded-[2rem] bg-slate-50 flex items-center justify-center group-hover:bg-health-600 group-hover:text-white transition-all duration-500 shadow-inner">
                                    <i class="fas <?= $res['icon'] ?> text-xl"></i>
                                </div>
                                <span class="text-[9px] font-black uppercase tracking-[0.2em] bg-slate-100 px-3 py-1 rounded-full text-slate-500"><?= $res['type'] ?></span>
                            </div>
                            
                            <div class="space-y-2">
                                <h3 class="text-xl font-black text-slate-900 leading-tight group-hover:text-health-600 transition-colors"><?= $res['title'] ?></h3>
                                <p class="text-sm text-slate-500 font-medium line-clamp-3 leading-relaxed"><?= $res['description'] ?></p>
                            </div>

                            <div class="pt-6 border-t border-slate-50 mt-auto flex items-center justify-between">
                                <span class="text-[10px] font-black text-health-600 uppercase tracking-widest bg-health-50 px-3 py-1 rounded-lg border border-health-100"><?= $res['category'] ?></span>
                                <a href="#" class="text-xs font-black text-slate-900 group-hover:text-health-600 flex items-center gap-2">
                                    READ MORE <i class="fas fa-arrow-right text-[10px]"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-span-full py-24 flex flex-col items-center justify-center opacity-30 italic">
                        <i class="fas fa-book-open text-7xl mb-6"></i>
                        <p class="font-black text-lg uppercase tracking-widest">No matching resources found</p>
                        <a href="library.php" class="mt-4 text-health-600 font-bold underline underline-offset-4">Reset Library</a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Suggested for You Section -->
            <section class="pt-12">
                <div class="flex items-center gap-4 mb-8">
                    <h2 class="text-xl font-black text-slate-900 uppercase tracking-widest">Featured Guides</h2>
                    <div class="flex-1 h-px bg-slate-100"></div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <!-- Feature 1 -->
                    <div class="relative bg-slate-900 p-8 rounded-[3rem] overflow-hidden group shadow-2xl shadow-indigo-100 border border-indigo-500/10">
                        <div class="absolute top-0 right-0 w-64 h-64 bg-indigo-500/10 rounded-full blur-3xl -mr-32 -mt-32"></div>
                        <div class="absolute bottom-0 left-0 w-64 h-64 bg-emerald-500/10 rounded-full blur-3xl -ml-32 -mb-32"></div>
                        
                        <div class="relative z-10 space-y-6">
                            <span class="text-[10px] font-black text-indigo-400 uppercase tracking-[0.3em]">Must Watch</span>
                            <div class="space-y-2">
                                <h3 class="text-2xl font-black text-white leading-tight">Safe Pregnancy Prep</h3>
                                <p class="text-slate-400 text-sm font-medium opacity-80">Discover the 10 most important things to do before your third trimester.</p>
                            </div>
                            <button class="bg-white text-indigo-600 px-8 py-3 rounded-2xl font-black text-[10px] uppercase tracking-widest hover:bg-indigo-50 transition-all flex items-center gap-3 w-fit">
                                WATCH SESSIONS <i class="fas fa-play text-[8px]"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Feature 2 -->
                    <div class="relative bg-emerald-950 p-8 rounded-[3rem] overflow-hidden group shadow-2xl shadow-emerald-100 border border-emerald-500/10">
                        <div class="absolute top-0 right-0 w-64 h-64 bg-emerald-400/10 rounded-full blur-3xl -mr-32 -mt-32"></div>
                        <div class="absolute bottom-0 left-0 w-64 h-64 bg-teal-500/10 rounded-full blur-3xl -ml-32 -mb-32"></div>
                        
                        <div class="relative z-10 space-y-6">
                            <span class="text-[10px] font-black text-emerald-400 uppercase tracking-[0.3em]">Free Download</span>
                            <div class="space-y-2">
                                <h3 class="text-2xl font-black text-white leading-tight">Healthy Baby Nutrition</h3>
                                <p class="text-slate-400 text-sm font-medium opacity-80">Download our complementary feeding guide for babies aged 6-12 months.</p>
                            </div>
                            <button class="bg-emerald-500 text-white px-8 py-3 rounded-2xl font-black text-[10px] uppercase tracking-widest hover:bg-emerald-400 transition-all flex items-center gap-3 w-fit">
                                GET THE PDF <i class="fas fa-download text-[8px]"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>
</body>
</html>
