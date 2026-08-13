<?php
/**
 * AniPet Admin / Super Admin Login
 * IMPORTANT: this file must be saved as UTF-8 WITHOUT BOM.
 */
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'] ?? '';

    if ($role === 'admin') {
        header('Location: admin_workspace.php');
        exit;
    }

    if (in_array($role, ['super_admin', 'super'], true)) {
        header('Location: super_admin_dashboard.php');
        exit;
    }

    session_unset();
    session_destroy();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AniPet - Staff Login</title>
    <link rel="icon" type="image/jpeg" href="/anipet_logo.jpg">

    <style>
        :root {
            --cream: #f3e7d3;
            --cream-card: rgba(255, 250, 239, .88);
            --cream-field: rgba(255, 252, 245, .82);
            --brown: #3d2415;
            --brown-soft: #8a5a34;
            --brown-hover: #704524;
            --tan: #cfa57c;
            --border: rgba(138, 90, 52, .42);
            --coral: #ef8178;
            --danger-bg: rgba(166, 62, 53, .11);
            --danger: #8f2f28;
        }

        * {
            box-sizing: border-box;
        }

        html, body {
            min-height: 100%;
            margin: 0;
        }

        body {
            min-height: 100vh;
            font-family: "Segoe UI", Arial, sans-serif;
            color: var(--brown);
            background:
                linear-gradient(
                    rgba(255, 245, 225, .18),
                    rgba(255, 245, 225, .18)
                ),
                url("/anipet_app_wallpaper.png") center / cover fixed no-repeat;
            display: grid;
            place-items: center;
            padding: 24px;
        }

        .page-shell {
            width: min(1080px, 100%);
            display: grid;
            grid-template-columns: minmax(0, 1.15fr) minmax(360px, .85fr);
            gap: 46px;
            align-items: center;
        }

        .welcome-panel {
            padding: 46px;
            border: 1px solid rgba(138, 90, 52, .18);
            border-radius: 30px;
            background: rgba(255, 250, 239, .38);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            box-shadow: 0 20px 60px rgba(71, 44, 24, .10);
        }

        .welcome-panel img {
            width: 112px;
            height: 112px;
            object-fit: cover;
            border-radius: 50%;
            background: #fff;
            padding: 5px;
            border: 3px solid rgba(138, 90, 52, .72);
            box-shadow: 0 8px 22px rgba(61, 36, 21, .16);
        }

        .welcome-panel h1 {
            margin: 22px 0 10px;
            font-size: clamp(2.3rem, 5vw, 4.4rem);
            line-height: .96;
            color: var(--brown);
        }

        .welcome-panel h1 span {
            color: var(--coral);
        }

        .welcome-panel p {
            max-width: 540px;
            margin: 0;
            font-size: 1.08rem;
            line-height: 1.7;
            color: rgba(61, 36, 21, .75);
        }

        .login-card {
            width: 100%;
            padding: 34px;
            border-radius: 26px;
            border: 1px solid rgba(138, 90, 52, .24);
            background: var(--cream-card);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            box-shadow: 0 24px 65px rgba(61, 36, 21, .18);
        }

        .mobile-logo {
            text-align: center;
            margin-bottom: 28px;
        }

        .mobile-logo img {
            width: 96px;
            height: 96px;
            object-fit: cover;
            border-radius: 50%;
            background: #fff;
            padding: 4px;
            border: 3px solid rgba(138, 90, 52, .72);
            box-shadow: 0 7px 18px rgba(61, 36, 21, .14);
        }

        .mobile-logo h2 {
            margin: 8px 0 3px;
            font-size: 1.8rem;
            color: var(--brown);
        }

        .mobile-logo h2 span {
            color: var(--coral);
        }

        .mobile-logo p {
            margin: 0;
            color: rgba(61, 36, 21, .62);
        }

        .form-group {
            margin-bottom: 18px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 700;
            color: var(--brown);
        }

        input[type="text"],
        input[type="password"] {
            width: 100%;
            height: 52px;
            padding: 0 15px;
            border-radius: 14px;
            border: 1.5px solid var(--border);
            background: var(--cream-field);
            color: var(--brown);
            font: inherit;
            outline: none;
            transition: .18s ease;
        }

        input::placeholder {
            color: rgba(61, 36, 21, .48);
        }

        input:focus {
            border-color: var(--brown-soft);
            box-shadow: 0 0 0 4px rgba(138, 90, 52, .10);
            background: rgba(255, 252, 245, .96);
        }

        .btn-login {
            width: 100%;
            min-height: 52px;
            border: 0;
            border-radius: 14px;
            background: var(--brown-soft);
            color: #fffaf1;
            font-size: 1rem;
            font-weight: 800;
            cursor: pointer;
            transition: .18s ease;
        }

        .btn-login:hover {
            background: var(--brown-hover);
            transform: translateY(-1px);
        }

        .staff-note {
            margin: 18px 0 0;
            text-align: center;
            font-size: .82rem;
            color: rgba(61, 36, 21, .58);
        }

        .error-message {
            display: none;
            margin-bottom: 18px;
            padding: 12px 14px;
            border: 1px solid rgba(143, 47, 40, .22);
            border-radius: 13px;
            background: var(--danger-bg);
            color: var(--danger);
            font-weight: 650;
            line-height: 1.45;
        }

        .loading {
            display: none;
            text-align: center;
            margin-top: 16px;
            color: var(--brown-soft);
            font-weight: 650;
        }

        .spinner {
            width: 30px;
            height: 30px;
            margin: 0 auto 8px;
            border-radius: 50%;
            border: 3px solid rgba(138, 90, 52, .18);
            border-top-color: var(--brown-soft);
            animation: spin .8s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        @media (max-width: 820px) {
            body {
                padding: 18px;
            }

            .page-shell {
                grid-template-columns: 1fr;
                max-width: 480px;
            }

            .welcome-panel {
                display: none;
            }

            .login-card {
                padding: 28px 22px;
                background: rgba(255, 250, 239, .82);
            }
        }
    </style>
</head>

<body>
    <main class="page-shell">
        <section class="welcome-panel">
            <img src="/anipet_logo.jpg" alt="AniPet logo">
            <h1>Ani<span>Pet</span></h1>
            <p>
                Animal Adoption and Pet Pound Management System.
                Sign in to manage pets, adoption applications,
                appointments, users, reports, and other pound operations.
            </p>
        </section>

        <section class="login-card">
            <div class="mobile-logo">
                <img src="/anipet_logo.jpg" alt="AniPet logo">
                <h2>Ani<span>Pet</span></h2>
                <p>Adopt a Pet, Gain a Friend.</p>
            </div>

            <div class="error-message" id="errorMessage"></div>

            <form id="loginForm">
                <div class="form-group">
                    <label for="email">Email or Username</label>
                    <input
                        type="text"
                        id="email"
                        name="email"
                        placeholder="Enter your email or username"
                        required
                        autocomplete="username"
                    >
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="Enter your password"
                        required
                        autocomplete="current-password"
                    >
                </div>

                <input type="hidden" name="admin_login" value="1">

                <button type="submit" class="btn-login" id="loginBtn">
                    Login
                </button>

                <div class="loading" id="loading">
                    <div class="spinner"></div>
                    <div>Logging in...</div>
                </div>
            </form>

            <p class="staff-note">
                Authorized AniPet staff accounts only.
            </p>
        </section>
    </main>

    <script>
        const form = document.getElementById('loginForm');
        const emailInput = document.getElementById('email');
        const passwordInput = document.getElementById('password');
        const loginBtn = document.getElementById('loginBtn');
        const errorMsg = document.getElementById('errorMessage');
        const loading = document.getElementById('loading');

        form.addEventListener('submit', async (event) => {
            event.preventDefault();

            const email = emailInput.value.trim();
            const password = passwordInput.value;

            if (!email || !password) {
                showError('Email/Username and password are required.');
                return;
            }

            loginBtn.style.display = 'none';
            loading.style.display = 'block';
            errorMsg.style.display = 'none';

            try {
                const response = await fetch('login.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: new URLSearchParams(new FormData(form)).toString()
                });

                const data = await response.json();

                if (data.status === 'success') {
                    const role = data.user?.role;

                    if (role === 'admin') {
                        window.location.href = 'admin_workspace.php';
                        return;
                    }

                    if (role === 'super_admin' || role === 'super') {
                        window.location.href = 'super_admin_dashboard.php';
                        return;
                    }

                    showError('Admin access is required.');
                } else if (data.status === 'unverified') {
                    showError('Account not verified. Check your email.');
                } else {
                    showError(data.message || 'Login failed.');
                }
            } catch (error) {
                showError('Network error: ' + error.message);
            } finally {
                loginBtn.style.display = 'block';
                loading.style.display = 'none';
            }
        });

        function showError(message) {
            errorMsg.textContent = message;
            errorMsg.style.display = 'block';
        }
    </script>
</body>
</html>