<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Renewable Solar SCADA - Sign In</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preload" as="image" href="./assets/login-background.png?v=20260902b">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
        html,body{min-height:100%}
        body{
            font-family:'Inter',sans-serif;
            background-color:#0f172a;
            background-image:linear-gradient(rgba(15,23,42,.52),rgba(15,23,42,.52)),url('./assets/login-background.png?v=20260902b');
            background-repeat:no-repeat;
            background-position:center;
            background-size:cover;
            background-attachment:fixed;
        }
        .login-panel{background:rgba(255,255,255,.97);box-shadow:0 20px 50px rgba(15,23,42,.28)}
        @media(max-width:767px){body{background-attachment:scroll}}
    </style>
</head>
<body class="text-slate-800 antialiased min-h-screen flex items-center justify-center px-4 py-8">
    <main class="login-panel w-full max-w-sm rounded-xl border border-white/70 p-6 sm:p-8">
        <div class="text-center mb-7">
            <div class="w-12 h-12 bg-emerald-600 text-white rounded-xl mx-auto flex items-center justify-center text-xl shadow-md mb-3">
                <i class="fa-solid fa-solar-panel"></i>
            </div>
            <h1 class="text-xl font-bold text-slate-900">Solar SCADA</h1>
            <p class="text-xs text-slate-500 mt-1">Sign in to plant monitoring</p>
        </div>

        <form id="loginForm" class="space-y-4" autocomplete="on">
            <div>
                <label for="email" class="block text-xs font-semibold text-slate-700 mb-1.5">Email</label>
                <input type="email" id="email" autocomplete="username" required class="w-full h-11 px-3 text-sm bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-200 focus:border-emerald-500">
            </div>
            <div>
                <label for="password" class="block text-xs font-semibold text-slate-700 mb-1.5">Password</label>
                <input type="password" id="password" autocomplete="current-password" required class="w-full h-11 px-3 text-sm bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-200 focus:border-emerald-500">
            </div>
            <button type="submit" class="w-full h-11 px-4 text-sm text-white font-semibold rounded-lg bg-emerald-600 hover:bg-emerald-700 transition-colors mt-2">
                Sign In
            </button>
            <p id="login-error" class="text-red-500 text-xs text-center hidden font-medium" role="alert" aria-live="polite"></p>
        </form>
    </main>

    <script>
        document.getElementById('loginForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = e.target.querySelector('button[type="submit"]');
            const err = document.getElementById('login-error');
            err.classList.add('hidden');
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-2"></i>Signing in...';
            btn.disabled = true;
            btn.setAttribute('aria-busy','true');

            try {
                const res = await fetch('api.php?action=login', {
                    method:'POST',
                    headers:{'Content-Type':'application/json'},
                    body:JSON.stringify({
                        email:document.getElementById('email').value,
                        password:document.getElementById('password').value
                    })
                });
                const data = await res.json();
                if (data.status === 'success') {
                    localStorage.setItem('userRole',data.user.role);
                    localStorage.setItem('vs_token',data.token);
                    localStorage.setItem('vs_user',JSON.stringify(data.user));
                    sessionStorage.setItem('vs_token',data.token);
                    sessionStorage.setItem('vs_user',JSON.stringify(data.user));

                    if (data.user.role === 'admin') {
                        location.href='admin.php?token='+encodeURIComponent(data.token);
                    } else {
                        location.href='home.php?plant='+encodeURIComponent(data.user.plant_id)+'&token='+encodeURIComponent(data.token);
                    }
                    return;
                }
                err.innerHTML='<i class="fa-solid fa-circle-exclamation mr-1"></i>'+String(data.message||'Sign in failed');
                err.classList.remove('hidden');
            } catch (error) {
                console.error('Login Error:',error);
                err.textContent='Unable to sign in. Please try again.';
                err.classList.remove('hidden');
            } finally {
                btn.innerHTML='Sign In';
                btn.disabled=false;
                btn.removeAttribute('aria-busy');
            }
        });
    </script>
</body>
</html>
