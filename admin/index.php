<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل الدخول - جذلة</title>
    
    <!-- Bootstrap 5 RTL -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" sizes="16x16" href="/assets/favicon-16x16.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="192x192" href="/assets/android-chrome-192x192.png">
    <link rel="icon" type="image/png" sizes="512x512" href="/assets/android-chrome-512x512.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/apple-touch-icon.png">
    <link rel="manifest" href="/assets/site.webmanifest">
    <meta name="theme-color" content="#4f46e5">
    
    <style>
        :root {
            --primary-color: #4f46e5;
            --primary-dark: #4338ca;
            --primary-light: #6366f1;
            --secondary-color: #10b981;
            --danger-color: #ef4444;
            --dark-bg: #1f2937;
            --light-bg: #f9fafb;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--light-bg);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        /* Animated Background */
        .bg-animation {
            position: absolute;
            width: 100%;
            height: 100%;
            overflow: hidden;
            z-index: -1;
        }

        .bg-animation::before {
            content: '';
            position: absolute;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, 
                rgba(79, 70, 229, 0.03) 0%,
                rgba(99, 102, 241, 0.03) 25%,
                rgba(139, 92, 246, 0.03) 50%,
                rgba(168, 85, 247, 0.03) 75%,
                rgba(79, 70, 229, 0.03) 100%
            );
            animation: gradientShift 20s ease infinite;
        }

        @keyframes gradientShift {
            0%, 100% { transform: translate(0, 0); }
            25% { transform: translate(-50%, 0); }
            50% { transform: translate(-50%, -50%); }
            75% { transform: translate(0, -50%); }
        }

        .floating-shape {
            position: absolute;
            opacity: 0.1;
            animation: float 20s infinite ease-in-out;
        }

        .shape-1 {
            width: 300px;
            height: 300px;
            background: var(--primary-color);
            border-radius: 50%;
            top: -150px;
            left: -150px;
            animation-duration: 25s;
        }

        .shape-2 {
            width: 200px;
            height: 200px;
            background: var(--secondary-color);
            border-radius: 50%;
            bottom: -100px;
            right: -100px;
            animation-duration: 20s;
            animation-delay: 5s;
        }

        .shape-3 {
            width: 150px;
            height: 150px;
            background: var(--primary-light);
            border-radius: 50%;
            top: 50%;
            left: 80%;
            animation-duration: 30s;
            animation-delay: 10s;
        }

        @keyframes float {
            0%, 100% { transform: translate(0, 0) scale(1); }
            25% { transform: translate(30px, -30px) scale(1.1); }
            50% { transform: translate(-20px, 20px) scale(0.9); }
            75% { transform: translate(20px, 30px) scale(1.05); }
        }

        /* Login Container */
        .login-container {
            width: 100%;
            max-width: 450px;
            padding: 20px;
            position: relative;
            z-index: 1;
        }

        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            padding: 40px;
            box-shadow: 
                0 20px 25px -5px rgba(0, 0, 0, 0.1),
                0 10px 10px -5px rgba(0, 0, 0, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.7);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .login-card:hover {
            transform: translateY(-5px);
            box-shadow: 
                0 25px 30px -5px rgba(0, 0, 0, 0.15),
                0 15px 15px -5px rgba(0, 0, 0, 0.06);
        }

        /* Logo Section */
        .logo-section {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            box-shadow: 0 10px 20px -5px rgba(79, 70, 229, 0.3);
            transition: transform 0.3s ease;
        }

        .logo:hover {
            transform: rotate(10deg) scale(1.1);
        }

        .logo i {
            font-size: 2.5rem;
            color: white;
        }

        .login-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--dark-bg);
            margin-bottom: 0.5rem;
        }

        .login-subtitle {
            color: #6b7280;
            font-size: 0.875rem;
        }

        /* Form Styling */
        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            font-weight: 600;
            color: #374151;
            margin-bottom: 8px;
            font-size: 0.875rem;
            display: block;
        }

        .input-group {
            position: relative;
        }

        .input-icon {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            font-size: 1.25rem;
            z-index: 2;
        }

        .form-control {
            padding: 12px 45px 12px 15px;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            font-size: 0.9375rem;
            transition: all 0.3s ease;
            background: #f9fafb;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            background: white;
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1);
        }

        .form-control.is-invalid {
            border-color: var(--danger-color);
        }

        .invalid-feedback {
            font-size: 0.875rem;
            margin-top: 5px;
            display: none;
        }

        .is-invalid ~ .invalid-feedback {
            display: block;
        }

        /* Remember Me */
        .remember-me {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }

        .form-check-input {
            width: 20px;
            height: 20px;
            margin-left: 8px;
            cursor: pointer;
            border: 2px solid #e5e7eb;
        }

        .form-check-input:checked {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .form-check-label {
            color: #6b7280;
            font-size: 0.875rem;
            cursor: pointer;
        }

        /* Submit Button */
        .btn-login {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px -5px rgba(79, 70, 229, 0.4);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .btn-login:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }

        .btn-login .spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 0.8s linear infinite;
            margin-left: 8px;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Alert Messages */
        .alert {
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 0.875rem;
            border: none;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
        }

        .alert i {
            font-size: 1.25rem;
        }

        /* Footer */
        .login-footer {
            text-align: center;
            margin-top: 30px;
            color: #6b7280;
            font-size: 0.875rem;
        }

        .login-footer a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease;
        }

        .login-footer a:hover {
            color: var(--primary-dark);
        }

        /* Demo Credentials */
        .demo-credentials {
            background: #f3f4f6;
            border-radius: 12px;
            padding: 15px;
            margin-top: 20px;
            font-size: 0.875rem;
        }

        .demo-credentials h6 {
            font-size: 0.875rem;
            font-weight: 600;
            margin-bottom: 10px;
            color: #374151;
        }

        .credential-item {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
            color: #6b7280;
        }

        .credential-item code {
            background: white;
            padding: 2px 8px;
            border-radius: 6px;
            color: var(--primary-color);
            font-weight: 600;
        }

        /* Responsive */
        @media (max-width: 480px) {
            .login-card {
                padding: 30px 20px;
            }
            
            .login-title {
                font-size: 1.5rem;
            }
        }

        /* Loading Overlay */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.9);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }

        .loading-overlay.active {
            display: flex;
        }

        .loader {
            width: 50px;
            height: 50px;
            border: 4px solid #f3f4f6;
            border-top-color: var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
    </style>
</head>
<body>
    <!-- Animated Background -->
    <div class="bg-animation">
        <div class="floating-shape shape-1"></div>
        <div class="floating-shape shape-2"></div>
        <div class="floating-shape shape-3"></div>
    </div>

    <!-- Login Container -->
    <div class="login-container">
        <div class="login-card">
            <!-- Logo Section -->
            <div class="logo-section">
                <div class="logo">
                    <i class="bi bi-camera-fill"></i>
                </div>
                <h1 class="login-title">مرحباً بك في جذلة</h1>
                <p class="login-subtitle">قم بتسجيل الدخول للوصول إلى لوحة التحكم</p>
            </div>

            <!-- Alert Messages -->
            <div id="alertContainer"></div>

            <!-- Login Form -->
            <form id="loginForm" method="POST" action="login_process.php">
                <div class="form-group">
                    <label class="form-label" for="username">اسم المستخدم</label>
                    <div class="input-group">
                        <i class="bi bi-person input-icon"></i>
                        <input 
                            type="text" 
                            class="form-control" 
                            id="username" 
                            name="username" 
                            placeholder="أدخل اسم المستخدم"
                            required
                            autocomplete="username"
                        >
                        <div class="invalid-feedback">
                            يرجى إدخال اسم المستخدم
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">كلمة المرور</label>
                    <div class="input-group">
                        <i class="bi bi-lock input-icon"></i>
                        <input 
                            type="password" 
                            class="form-control" 
                            id="password" 
                            name="password" 
                            placeholder="أدخل كلمة المرور"
                            required
                            autocomplete="current-password"
                        >
                        <div class="invalid-feedback">
                            يرجى إدخال كلمة المرور
                        </div>
                    </div>
                </div>

                <div class="remember-me">
                    <input type="checkbox" class="form-check-input" id="remember" name="remember">
                    <label class="form-check-label" for="remember">
                        تذكرني لمدة 30 يوماً
                    </label>
                </div>

                <button type="submit" class="btn-login" id="loginBtn">
                    تسجيل الدخول
                </button>
            </form>

            <!-- Demo Credentials -->
   
            <!-- Footer -->
            <div class="login-footer">
                <p>© 2024 جذلة. جميع الحقوق محفوظة.</p>
                <p>هل تحتاج مساعدة؟ <a href="#">تواصل معنا</a></p>
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loader"></div>
    </div>

    <script>
        // Form Validation and Submission
        document.getElementById('loginForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const username = document.getElementById('username');
            const password = document.getElementById('password');
            const loginBtn = document.getElementById('loginBtn');
            const alertContainer = document.getElementById('alertContainer');
            
            // Clear previous alerts
            alertContainer.innerHTML = '';
            
            // Reset validation states
            username.classList.remove('is-invalid');
            password.classList.remove('is-invalid');
            
            // Validate inputs
            let isValid = true;
            
            if (!username.value.trim()) {
                username.classList.add('is-invalid');
                isValid = false;
            }
            
            if (!password.value) {
                password.classList.add('is-invalid');
                isValid = false;
            }
            
            if (!isValid) return;
            
            // Show loading state
            loginBtn.disabled = true;
            loginBtn.innerHTML = 'جاري تسجيل الدخول <span class="spinner"></span>';
            
            try {
                // Submit form data
                const formData = new FormData(this);
                const response = await fetch('login_process.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Show success message
                    showAlert('تم تسجيل الدخول بنجاح! جاري التحويل...', 'success');
                    
                    // Show loading overlay
                    document.getElementById('loadingOverlay').classList.add('active');
                    
                    // Redirect after animation
                    setTimeout(() => {
                        window.location.href = result.redirect || 'dashboard.php';
                    }, 1000);
                } else {
                    // Show error message
                    showAlert(result.message || 'بيانات الدخول غير صحيحة', 'danger');
                    
                    // Shake animation
                    document.querySelector('.login-card').style.animation = 'shake 0.5s';
                    setTimeout(() => {
                        document.querySelector('.login-card').style.animation = '';
                    }, 500);
                }
            } catch (error) {
                console.error('Login error:', error);
                showAlert('حدث خطأ في الاتصال. يرجى المحاولة مرة أخرى.', 'danger');
            } finally {
                // Reset button state
                loginBtn.disabled = false;
                loginBtn.innerHTML = 'تسجيل الدخول';
            }
        });
        
        // Show Alert Function
        function showAlert(message, type) {
            const alertContainer = document.getElementById('alertContainer');
            const icon = type === 'success' ? 'check-circle-fill' : 'exclamation-triangle-fill';
            
            const alert = document.createElement('div');
            alert.className = `alert alert-${type} animate-slide-down`;
            alert.innerHTML = `
                <i class="bi bi-${icon}"></i>
                <span>${message}</span>
            `;
            
            alertContainer.appendChild(alert);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 300);
            }, 5000);
        }
        
        // Input Focus Effects
        document.querySelectorAll('.form-control').forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.querySelector('.input-icon').style.color = '#4f46e5';
            });
            
            input.addEventListener('blur', function() {
                this.parentElement.querySelector('.input-icon').style.color = '#9ca3af';
            });
            
            // Clear validation on input
            input.addEventListener('input', function() {
                this.classList.remove('is-invalid');
            });
        });
        
        // Auto-fill detection
        setTimeout(() => {
            document.querySelectorAll('.form-control').forEach(input => {
                if (input.value) {
                    input.parentElement.querySelector('.input-icon').style.color = '#4f46e5';
                }
            });
        }, 100);
        
        // Shake animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes shake {
                0%, 100% { transform: translateX(0); }
                10%, 30%, 50%, 70%, 90% { transform: translateX(-10px); }
                20%, 40%, 60%, 80% { transform: translateX(10px); }
            }
            
            @keyframes animate-slide-down {
                from {
                    opacity: 0;
                    transform: translateY(-20px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
            
            .animate-slide-down {
                animation: animate-slide-down 0.3s ease-out;
            }
        `;
        document.head.appendChild(style);
        
        // Remember me functionality
        if (localStorage.getItem('rememberUsername')) {
            document.getElementById('username').value = localStorage.getItem('rememberUsername');
            document.getElementById('remember').checked = true;
        }
        
        document.getElementById('remember').addEventListener('change', function() {
            if (this.checked) {
                localStorage.setItem('rememberUsername', document.getElementById('username').value);
            } else {
                localStorage.removeItem('rememberUsername');
            }
        });
        
        // Update remember username on input
        document.getElementById('username').addEventListener('input', function() {
            if (document.getElementById('remember').checked) {
                localStorage.setItem('rememberUsername', this.value);
            }
        });
    </script>
</body>
</html>