<?php
session_start();
include 'db.php';

/* ===========================
   FETCH SPA NAME + LOGO
=========================== */
$company_name = "Wellness Spa";
$company_logo = "../images/lap-logo.JPG";

$settings = $conn->query("SELECT spa_name AS company_name, logo_path AS company_logo FROM settings LIMIT 1");
if ($settings && $settings->num_rows > 0) {
    $s = $settings->fetch_assoc();
    $company_name = $s['company_name'];
    if (!empty($s['company_logo'])) {
        $company_logo = "../" . $s['company_logo'];
    }
}

/* ===========================
   HANDLE LOGIN
=========================== */
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST["username"]);
    $password = trim($_POST["password"]);

    $stmt = $conn->prepare("
        SELECT 
            u.id,
            u.username,
            u.password,
            u.role,
            u.employee_id,
            e.is_active
        FROM users u
        LEFT JOIN employees e ON e.id = u.employee_id
        WHERE u.username = ?
        LIMIT 1
    ");

    $stmt->bind_param("s", $username);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows === 1) {
        $user = $res->fetch_assoc();

        // ✅ Verify password (bcrypt / argon2)
        if (!password_verify($password, $user["password"])) {
            $error = "Incorrect password.";
        }
        // ✅ Block inactive employees
        elseif ($user["employee_id"] && intval($user["is_active"]) === 0) {
            $error = "Your account has been deactivated. Please contact admin.";
        }
        // ✅ Login success
        else {
            $_SESSION["user_id"] = $user["id"];
            $_SESSION["username"] = $user["username"];
            $_SESSION["role"] = $user["role"];

            if ($user["role"] === 'cashier') {
                header("Location: ../cashier/index.php");
            } else {
                header("Location: ../dashboard.php"); // admin
            }
            exit;
        }
    } else {
        $error = "Username not found.";
    }

    $stmt->close();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($company_name) ?> - Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/jpeg" href="<?= htmlspecialchars($company_logo) ?>">

    <!-- Google Font -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&display=swap" rel="stylesheet">
    <script>
        // Auto dark mode based on system preference
        if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
            document.documentElement.classList.add('dark');
        }
    </script>

    <!-- Tailwind -->
    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        body {
            font-family: 'Inter', sans-serif;
        }

        /* Soft gradient background */
        .spa-bg {
            background: linear-gradient(135deg, #c3ffe6 0%, #e6d4ff 100%);
        }

        /* Glass effect */
        .glass-card {
            backdrop-filter: blur(20px);
            background: rgba(255, 255, 255, 0.7);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.4);
            animation: fadeIn 0.8s ease;
        }

        .dark .glass-card {
            background: rgba(31, 41, 55, 0.7);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        /* Fade-in */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Focus glow */
        .input-focus:focus {
            box-shadow: 0 0 0 3px rgba(72, 207, 173, 0.4);
            border-color: #48cfad;
        }
    </style>
</head>

<body class="spa-bg dark:bg-gradient-to-br dark:from-gray-900 dark:to-gray-800
             min-h-screen flex items-center justify-center px-4 py-8 sm:p-6 transition-colors">

    <div class="glass-card w-full max-w-md px-6 py-8 sm:p-10 rounded-3xl">

        <!-- Logo -->
        <div class="flex justify-center mb-6">
            <img src="<?= htmlspecialchars($company_logo) ?>"
                class="w-20 h-20 sm:w-24 sm:h-24 object-contain rounded-full shadow-md">

        </div>

        <h1 class="text-xl sm:text-2xl font-semibold text-gray-700 dark:text-gray-100 text-center"><?= htmlspecialchars($company_name) ?></h1>
        <p class="text-center text-gray-500 dark:text-gray-400 text-xs sm:text-sm mb-6">Welcome — Please log in</p>

        <?php if (!empty($error)): ?>
            <div class="mb-4 p-3 text-sm bg-red-100 text-red-600 border border-red-300 rounded-md">
                <?= $error ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-5">

            <div>
                <label class="text-gray-700 dark:text-gray-300 text-sm font-medium">Username</label>
                <input type="text" name="username" required
                    class="input-focus mt-1 w-full px-4 py-2.5 sm:py-3 border border-gray-300 dark:border-gray-600
                   rounded-xl outline-none text-sm sm:text-base pr-12
                   bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-100">
            </div>
            <div>
                <label class="text-gray-700 dark:text-gray-300 text-sm font-medium">Password</label>

                <div class="relative">
                    <input
                        type="password"
                        name="password"
                        id="password"
                        required
                        class="input-focus mt-1 w-full px-4 py-2.5 sm:py-3 border border-gray-300 dark:border-gray-600
                   rounded-xl outline-none text-sm sm:text-base pr-12
                   bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-100">

                    <!-- Animated Toggle -->
                    <button
                        type="button"
                        id="togglePassword"
                        class="absolute inset-y-0 right-3 flex items-center justify-center
                   text-gray-500 dark:text-gray-400
                   transition-transform duration-200 active:scale-90">
                        <i data-lucide="eye-closed" class="w-5 h-5"></i>
                    </button>
                </div>
            </div>
            <button type="submit"
                class="w-full py-2.5 sm:py-3 rounded-xl bg-teal-500 text-white text-base sm:text-lg font-semibold hover:bg-teal-600 transition">
                Login
            </button>
        </form>

        <p class="text-center text-gray-400 text-[11px] sm:text-xs mt-6 sm:mt-8">
            © <?= date('Y') ?> <?= htmlspecialchars($company_name) ?> — All Rights Reserved
        </p>
    </div>

    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        lucide.createIcons();

        const toggleBtn = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');

        toggleBtn.addEventListener('click', () => {
            const isPassword = passwordInput.type === 'password';

            passwordInput.type = isPassword ? 'text' : 'password';

            // Swap icon with animation
            toggleBtn.innerHTML = `
            <i data-lucide="${isPassword ? 'eye' : 'eye-closed'}"
               class="w-5 h-5 transition-transform duration-200
                      ${isPassword ? 'rotate-180' : 'rotate-0'}">
            </i>
        `;

            lucide.createIcons();
        });
    </script>


</body>

</html>