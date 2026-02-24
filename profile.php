<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Kibenes eBirth</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php include_once 'includes/tailwind_config.php'; ?>
</head>
<body class="bg-slate-50 min-h-full font-inter">
    <?php include_once 'includes/header.php'; ?>
    
    <div class="flex flex-col lg:flex-row min-h-[calc(100vh-4rem)]">
        <?php include_once 'includes/sidebar.php'; ?>
        
        <main class="flex-1 p-4 lg:p-8 space-y-8">
            <!-- Profile Header -->
            <header class="bg-white p-8 rounded-[2.5rem] border border-slate-100 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-6">
                <div class="flex items-center gap-6">
                    <div class="w-20 h-20 rounded-[2rem] bg-gradient-to-br from-health-600 to-medical-600 flex items-center justify-center text-white text-3xl font-black shadow-lg shadow-health-100">
                        <?= strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)) ?>
                    </div>
                    <div class="space-y-1">
                        <h1 class="text-3xl font-black text-slate-900 tracking-tight">Account Settings</h1>
                        <p class="text-slate-500 text-sm font-medium">Manage your personal information and security</p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <div class="bg-slate-100 px-4 py-2 rounded-2xl border border-slate-200">
                        <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest block">Role</span>
                        <span class="text-xs font-bold text-slate-700"><?= ucfirst($user['role']) ?></span>
                    </div>
                    <div class="bg-emerald-50 px-4 py-2 rounded-2xl border border-emerald-100">
                        <span class="text-[10px] font-black text-emerald-400 uppercase tracking-widest block">Status</span>
                        <span class="text-xs font-bold text-emerald-700"><?= ucfirst($user['status']) ?></span>
                    </div>
                </div>
            </header>

            <?php if ($message): ?>
            <div class="bg-emerald-50 border border-emerald-100 p-4 rounded-2xl flex items-center gap-3 animate-slide-in">
                <i class="fas fa-check-circle text-emerald-600"></i>
                <p class="text-sm font-bold text-emerald-800"><?= htmlspecialchars($message) ?></p>
            </div>
            <?php endif; ?>

            <?php if ($error): ?>
            <div class="bg-rose-50 border border-rose-100 p-4 rounded-2xl flex items-center gap-3 animate-slide-in">
                <i class="fas fa-exclamation-triangle text-rose-600"></i>
                <p class="text-sm font-bold text-rose-800"><?= htmlspecialchars($error) ?></p>
            </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Personal Info Column -->
                <section class="space-y-8">
                    <div class="bg-white p-8 rounded-[2.5rem] border border-slate-100 shadow-sm space-y-8">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-health-50 text-health-600 flex items-center justify-center">
                                <i class="fas fa-user text-sm"></i>
                            </div>
                            <h2 class="text-xl font-black text-slate-900 uppercase tracking-tight">Personal Details</h2>
                        </div>

                        <form method="POST" action="" class="space-y-6">
                            <div class="grid grid-cols-2 gap-4">
                                <div class="space-y-2">
                                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">First Name</label>
                                    <input type="text" name="first_name" value="<?= htmlspecialchars($user['first_name']) ?>" required class="w-full bg-slate-50 border-none rounded-2xl py-3 px-4 text-sm font-bold focus:ring-2 focus:ring-health-600 transition-all">
                                </div>
                                <div class="space-y-2">
                                    <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Last Name</label>
                                    <input type="text" name="last_name" value="<?= htmlspecialchars($user['last_name']) ?>" required class="w-full bg-slate-50 border-none rounded-2xl py-3 px-4 text-sm font-bold focus:ring-2 focus:ring-health-600 transition-all">
                                </div>
                            </div>

                            <div class="space-y-2">
                                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Email Address</label>
                                <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required class="w-full bg-slate-50 border-none rounded-2xl py-3 px-4 text-sm font-bold focus:ring-2 focus:ring-health-600 transition-all">
                            </div>

                            <div class="space-y-2">
                                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Phone Number</label>
                                <input type="tel" name="phone" value="<?= htmlspecialchars($user['phone']) ?>" class="w-full bg-slate-50 border-none rounded-2xl py-3 px-4 text-sm font-bold focus:ring-2 focus:ring-health-600 transition-all">
                            </div>

                            <div class="space-y-2">
                                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Username</label>
                                <input type="text" value="<?= htmlspecialchars($user['username']) ?>" disabled class="w-full bg-slate-100 border-none rounded-2xl py-3 px-4 text-sm font-bold text-slate-400">
                            </div>

                            <button type="submit" class="w-full bg-health-600 text-white py-4 rounded-2xl font-black text-sm uppercase tracking-widest shadow-lg shadow-health-100 hover:bg-health-700 transition-all active:scale-[0.98]">
                                Update Profile
                            </button>
                        </form>
                    </div>
                </section>

                <!-- Security Column -->
                <section class="space-y-8">
                    <!-- Password Change -->
                    <div class="bg-white p-8 rounded-[2.5rem] border border-slate-100 shadow-sm space-y-8">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center">
                                <i class="fas fa-key text-sm"></i>
                            </div>
                            <h2 class="text-xl font-black text-slate-900 uppercase tracking-tight">Security & Credentials</h2>
                        </div>

                        <form method="POST" action="change_password.php" id="passwordForm" class="space-y-6">
                            <div class="space-y-2">
                                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Current Password</label>
                                <input type="password" id="current_password" name="current_password" required class="w-full bg-slate-50 border-none rounded-2xl py-3 px-4 text-sm font-bold focus:ring-2 focus:ring-health-600 transition-all">
                                <p class="hidden text-[10px] font-bold text-rose-500 ml-1" id="current_password_warning"></p>
                            </div>

                            <div class="space-y-2">
                                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">New Password</label>
                                <input type="password" id="new_password" name="new_password" required minlength="6" class="w-full bg-slate-50 border-none rounded-2xl py-3 px-4 text-sm font-bold focus:ring-2 focus:ring-health-600 transition-all">
                                <p class="text-[10px] font-bold text-slate-400 ml-1">Minimum 6 characters</p>
                                <p class="hidden text-[10px] font-bold text-rose-500 ml-1" id="new_password_warning"></p>
                            </div>

                            <div class="space-y-2">
                                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Confirm New Password</label>
                                <input type="password" id="confirm_password" name="confirm_password" required class="w-full bg-slate-50 border-none rounded-2xl py-3 px-4 text-sm font-bold focus:ring-2 focus:ring-health-600 transition-all">
                                <p class="hidden text-[10px] font-bold text-rose-500 ml-1" id="confirm_password_warning"></p>
                            </div>

                            <button type="submit" class="w-full bg-indigo-600 text-white py-4 rounded-2xl font-black text-sm uppercase tracking-widest shadow-lg shadow-indigo-100 hover:bg-indigo-700 transition-all active:scale-[0.98]">
                                Change Password
                            </button>
                        </form>
                    </div>

                    <!-- Security Question -->
                    <div class="bg-white p-8 rounded-[2.5rem] border border-slate-100 shadow-sm space-y-8">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center">
                                <i class="fas fa-shield-alt text-sm"></i>
                            </div>
                            <h2 class="text-xl font-black text-slate-900 uppercase tracking-tight">Recovery Plan</h2>
                        </div>

                        <?php if (!$hasSecurityQuestion): ?>
                        <div class="bg-amber-50 border border-amber-100 p-6 rounded-3xl space-y-3">
                            <div class="flex items-center gap-2">
                                <i class="fas fa-info-circle text-amber-600"></i>
                                <p class="text-[10px] font-black text-amber-800 uppercase tracking-widest">Action Required</p>
                            </div>
                            <p class="text-xs font-semibold text-amber-700 leading-relaxed">Set up a security question to enable autonomous password recovery.</p>
                        </div>

                        <form method="POST" action="" class="space-y-6">
                            <div class="space-y-2">
                                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Security Question</label>
                                <select name="security_question" id="security_question" required class="w-full bg-slate-50 border-none rounded-2xl py-3 px-4 text-sm font-bold focus:ring-2 focus:ring-health-600 transition-all">
                                    <option value="">Select a security question</option>
                                    <?php foreach ($security_questions as $key => $question): ?>
                                        <option value="<?php echo htmlspecialchars($key); ?>"><?php echo htmlspecialchars($question); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="space-y-2">
                                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Your Answer</label>
                                <input type="text" name="security_answer" id="security_answer" required class="w-full bg-slate-50 border-none rounded-2xl py-3 px-4 text-sm font-bold focus:ring-2 focus:ring-health-600 transition-all">
                            </div>

                            <button type="submit" class="w-full bg-slate-900 text-white py-4 rounded-2xl font-black text-sm uppercase tracking-widest hover:bg-slate-800 transition-all">
                                Set Security Recovery
                            </button>
                        </form>
                        <?php else: ?>
                        <div class="space-y-6">
                            <div class="bg-emerald-50 border border-emerald-100 p-6 rounded-3xl flex items-center gap-4">
                                <div class="w-12 h-12 rounded-full bg-white flex items-center justify-center text-emerald-600 shadow-sm border border-emerald-100">
                                    <i class="fas fa-check-double"></i>
                                </div>
                                <div>
                                    <p class="text-xs font-black text-emerald-800 uppercase tracking-widest">Active Protection</p>
                                    <p class="text-[10px] font-bold text-emerald-600 opacity-80">Security question is configured</p>
                                </div>
                            </div>

                            <div class="p-6 bg-slate-50 rounded-3xl border border-slate-100">
                                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest block mb-2">My Recovery Question</label>
                                <p class="text-sm font-bold text-slate-700"><?php echo htmlspecialchars($security_questions[$user['security_question']] ?? 'Unknown question'); ?></p>
                            </div>

                            <div class="bg-slate-100 p-4 rounded-2xl">
                                <p class="text-[10px] font-medium text-slate-500 text-center italic">
                                    <i class="fas fa-lock text-[8px] mr-1"></i> Contact administrator to change security question
                                </p>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </section>
            </div>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const passwordForm = document.getElementById('passwordForm');
            const fields = ['current_password', 'new_password', 'confirm_password'];
            
            passwordForm.addEventListener('submit', function(e) {
                let isValid = true;
                
                fields.forEach(fieldId => {
                    const field = document.getElementById(fieldId);
                    const warning = document.getElementById(fieldId + '_warning');
                    
                    if (!field.value.trim()) {
                        warning.textContent = "This field is required";
                        warning.classList.remove('hidden');
                        isValid = false;
                    } else if (fieldId === 'new_password' && field.value.length < 6) {
                        warning.textContent = "Minimum 6 characters required";
                        warning.classList.remove('hidden');
                        isValid = false;
                    } else {
                        warning.classList.add('hidden');
                    }
                });
                
                const newPass = document.getElementById('new_password').value;
                const confirmPass = document.getElementById('confirm_password').value;
                if (newPass && confirmPass && newPass !== confirmPass) {
                    const confirmWarning = document.getElementById('confirm_password_warning');
                    confirmWarning.textContent = "Passwords do not match";
                    confirmWarning.classList.remove('hidden');
                    isValid = false;
                }
                
                if (!isValid) e.preventDefault();
            });

            // Clear warnings on input
            fields.forEach(fieldId => {
                document.getElementById(fieldId).addEventListener('input', function() {
                    document.getElementById(fieldId + '_warning').classList.add('hidden');
                });
            });
        });
    </script>
</body>
</html>
