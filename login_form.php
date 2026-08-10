<?php
/**
 * Browser Login Form
 * Works on desktop and mobile
 * Submits to login.php API endpoint
 */
session_start();

// If already logged in, redirect to the proper dashboard
if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'] ?? '';
    if ($role === 'admin') {
        header('Location: admin_workspace.php');
        exit;
    }
    if ($role === 'super_admin') {
        header('Location: super_admin_dashboard.php');
        exit;
    }
    // Regular users or unknown roles: destroy session and show login
    session_unset();
    session_destroy();
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Anipet - Login</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #1b2a41;
            background-image:
                linear-gradient(rgba(12, 25, 44, .42), rgba(12, 25, 44, .42)),
                url('/anipet_admin_wallpaper.png');
            background-position: center;
            background-size: cover;
            background-repeat: no-repeat;
            background-attachment: fixed;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .login-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 400px;
            padding: 40px;
        }

        .logo {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo img {
            width: 96px;
            height: 96px;
            object-fit: contain;
            margin-bottom: 8px;
        }

        .logo h1 {
            color: #f2867e;
            font-size: 28px;
            margin-bottom: 5px;
        }

        .logo p {
            color: #999;
            font-size: 14px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }
        
        input[type="text"],
        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 5px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        input[type="text"]:focus,
        input[type="email"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: #f2867e;
        }

        .btn-login {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #f2867e 0%, #e56f66 100%);
            color: #1b2a41;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            display: none;
        }
        
        .loading {
            display: none;
            text-align: center;
            color: #f2867e;
            font-size: 14px;
        }

        .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #f2867e;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            animation: spin 1s linear infinite;
            margin: 0 auto 10px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <img src="/anipet_logo.jpg" alt="AniPet logo">
            <h1>AniPet</h1>
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
                    autocomplete="off"
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
                    autocomplete="off"
                >
            </div>
            
            <button type="submit" class="btn-login" id="loginBtn">Login</button>
            <input type="hidden" name="admin_login" value="1">
            <div class="loading" id="loading">
                <div class="spinner"></div>
                <p>Logging in...</p>
            </div>
        </form>
    </div>
    
    <script>
        const form = document.getElementById('loginForm');
        const emailInput = document.getElementById('email');
        const passwordInput = document.getElementById('password');
        const loginBtn = document.getElementById('loginBtn');
        const errorMsg = document.getElementById('errorMessage');
        const loading = document.getElementById('loading');
        
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const email = emailInput.value.trim();
            const password = passwordInput.value;
            
            if (!email || !password) {
                showError('Email/Username and password are required');
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
                    if (data.user && data.user.role === 'admin') {
                        window.location.href = 'admin_workspace.php';
                    } else if (data.user && data.user.role === 'super_admin') {
                        window.location.href = 'super_admin_dashboard.php';
                    } else {
                        showError('Admin access is required.');
                    }
                } else if (data.status === 'unverified') {
                    showError('Account not verified. Check your email.');
                } else {
                    showError(data.message || 'Login failed');
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
