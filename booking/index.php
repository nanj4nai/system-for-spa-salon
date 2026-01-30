<?php
session_start();
require_once "../php/db.php";

$isLoggedIn = isset($_SESSION['user_id']);
$userRole  = $_SESSION['role'] ?? null;

$client = $_SESSION['booking_client'] ?? null;


$settings = $conn->query("SELECT * FROM settings LIMIT 1")->fetch_assoc();

$spaName = $settings['spa_name'] ?? 'My Wellness Spa';
$logoPath = $settings['logo_path'] ?? null;
$spaContact = $settings['contact_number'] ?? '';

$logoUrl = null;
if (!empty($logoPath)) {
    $logoUrl = '../' . ltrim($logoPath, '/');
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Book Appointment | <?= htmlspecialchars($spaName) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php if ($logoUrl): ?>
        <link rel="icon" href="<?= htmlspecialchars($logoUrl) ?>?v=<?= time() ?>">
    <?php endif; ?>

    <!-- Tailwind -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>

    <!-- Poppins Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        poppins: ['Poppins', 'sans-serif']
                    }
                }
            }
        }
    </script>
    <style>
        @keyframes slide-in {
            from {
                opacity: 0;
                transform: translateY(-8px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-slide-in {
            animation: slide-in 0.25s ease-out;
        }

        @keyframes fade-in {
            from {
                opacity: 0;
                transform: translateY(12px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-fade-in {
            animation: fade-in 0.35s ease-out both;
        }

        .typing-cursor {
            display: inline-block;
            margin-left: 2px;
            color: #4f46e5;
            /* indigo-600 */
            animation: blink 1s steps(2, start) infinite;
        }

        @keyframes blink {

            0%,
            50% {
                opacity: 1;
            }

            51%,
            100% {
                opacity: 0;
            }
        }
    </style>

</head>

<body class="font-poppins bg-gradient-to-br from-indigo-50 via-white to-pink-50 min-h-screen">

    <!-- Header -->
    <header class="w-full py-4 sm:py-6">
        <div class="max-w-6xl mx-auto px-4 flex items-center justify-between gap-3">

            <div class="flex items-center gap-3 min-w-0">
                <?php if ($logoUrl): ?>
                    <img src="<?= htmlspecialchars($logoUrl) ?>"
                        alt="<?= htmlspecialchars($spaName) ?>"
                        class="h-9 w-9 sm:h-10 sm:w-10 rounded-full object-cover">
                <?php else: ?>
                    <div class="h-9 w-9 sm:h-10 sm:w-10 rounded-full bg-indigo-600 flex items-center justify-center text-white font-semibold">
                        <?= strtoupper(substr($spaName, 0, 1)) ?>
                    </div>
                <?php endif; ?>

                <div class="min-w-0">
                    <h1 class="text-base sm:text-lg font-semibold text-gray-800 truncate">
                        <?= htmlspecialchars($spaName) ?>
                    </h1>
                    <p class="text-xs text-gray-500">
                        Online Booking
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-4">

                <!-- Staff Login (small & subtle) -->
                <a href="../php/login.php"
                    class="text-sm font-medium text-indigo-600 hover:text-indigo-700
              inline-flex items-center gap-1">
                    <i data-lucide="user" class="w-4 h-4"></i>
                    Staff Login
                </a>

                <?php if ($spaContact): ?>
                    <span class="hidden sm:flex items-center gap-2 text-sm text-gray-500 whitespace-nowrap">
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.5"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M2.25 6.75c0 8.284 6.716 15 15 15h1.5a1.5 1.5 0 001.5-1.5v-2.25a1.5 1.5 0 00-1.5-1.5h-2.25a1.5 1.5 0 00-1.5 1.5v.75a12.03 12.03 0 01-9-9h.75a1.5 1.5 0 001.5-1.5V3.75a1.5 1.5 0 00-1.5-1.5H3.75a1.5 1.5 0 00-1.5 1.5v3z" />
                        </svg>
                        <?= htmlspecialchars($spaContact) ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>
    </header>
    <!-- Main -->
    <main class="max-w-6xl mx-auto px-4 py-6">
        <div class="grid md:grid-cols-2 gap-10 md:items-center">
            <!-- Left / Branding -->
            <section
                id="introScreen"
                class="
                    flex flex-col justify-center
                    px-2 py-6 md:py-12
                    text-center md:text-left

                    md:block
                ">
                <h2 class="text-4xl sm:text-5xl font-semibold text-gray-800 leading-tight">
                    <span id="typingText"></span><span class="typing-cursor">|</span>
                </h2>

                <p class="mt-6 text-base sm:text-lg text-gray-600 max-w-md mx-auto md:mx-0">
                    Schedule your wellness session in just a few easy steps.
                    Choose your service, therapist, and preferred time.
                </p>

                <div class="mt-8 space-y-4 text-gray-600">
                    <!-- item -->
                    <div class="flex items-center gap-3 justify-center md:justify-start">
                        <i data-lucide="sparkles" class="w-6 h-6 text-indigo-600"></i>
                        <span>Experienced & certified therapists</span>
                    </div>

                    <div class="flex items-center gap-3 justify-center md:justify-start">
                        <i data-lucide="credit-card" class="w-6 h-6 text-indigo-600"></i>
                        <span>Secure online payment options</span>
                    </div>

                    <div class="flex items-center gap-3 justify-center md:justify-start">
                        <i data-lucide="mail" class="w-6 h-6 text-indigo-600"></i>
                        <span>Email confirmation after approval</span>
                    </div>
                </div>

                <!-- Mobile CTA -->
                <button
                    id="startBookingBtn"
                    class="
                            mt-10 md:hidden
                            w-full max-w-sm mx-auto
                            rounded-xl bg-indigo-600 px-6 py-4
                            text-white font-semibold text-lg
                            hover:bg-indigo-700 transition
                        ">
                    Get started →
                </button>
            </section>

            <!-- Booking Card -->
            <section
                id="formScreen"
                class="hidden md:block bg-white rounded-3xl shadow-xl p-6 sm:p-8 w-full
                        animate-fade-in
                    ">
                <!-- Progress -->
                <div class="mb-6 sm:mb-8">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-full bg-indigo-600 text-white flex items-center justify-center font-semibold text-sm sm:text-base">
                            1
                        </div>
                        <div class="flex-1 h-2 bg-gray-200 rounded-full overflow-hidden">
                            <div
                                class="h-2 bg-indigo-600 rounded-full transition-all duration-300"
                                style="width: calc(100% / 6 * 1);">
                            </div>
                        </div>
                        <span class="text-xs sm:text-sm text-gray-500 whitespace-nowrap">
                            Step 1 of 6
                        </span>

                    </div>

                    <h3 class="text-xl sm:text-2xl font-semibold text-gray-800">
                        Your Information
                    </h3>
                    <p class="text-sm sm:text-base text-gray-500">
                        We’ll use this to contact you about your appointment.
                    </p>
                </div>

                <!-- Error -->
                <div id="errorBox"
                    class="hidden mb-5 rounded-xl bg-red-50 border border-red-200
                            text-red-700 px-4 py-3 text-sm">
                </div>

                <!-- Form -->
                <form id="clientForm" class="space-y-6">

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Full Name
                        </label>
                        <input
                            type="text"
                            name="full_name"

                            placeholder="Juan Dela Cruz"
                            class="w-full rounded-xl border border-gray-300 px-4 py-3 sm:py-3.5
                            focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Email Address
                        </label>
                        <input
                            type="email"
                            name="email"

                            placeholder="juan@email.com"
                            class="w-full rounded-xl border border-gray-300 px-4 py-3 sm:py-3.5
                            focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Contact Number
                        </label>
                        <input
                            type="text"
                            name="contact_number"

                            placeholder="+63 9XX XXX XXXX"
                            class="w-full rounded-xl border border-gray-300 px-4 py-3 sm:py-3.5
                            focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    </div>

                    <!-- Honeypot field (bots will fill this, humans won't) -->
                    <input
                        type="text"
                        name="website"
                        tabindex="-1"
                        autocomplete="off"
                        class="hidden">
                    <button
                        type="submit"
                        class="w-full rounded-xl bg-indigo-600 px-6 py-3.5 sm:py-4
                            text-white font-semibold text-base sm:text-lg
                            hover:bg-indigo-700 transition
                            focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        Continue to Services →
                    </button>
                </form>
            </section>
        </div>
    </main>
    <div id="toastContainer"
        class="fixed top-5 right-5 z-50 flex flex-col gap-3">
    </div>

    <!-- Footer -->
    <footer class="py-6 text-center text-sm text-gray-400">
        © <?= date('Y') ?> <?= htmlspecialchars($spaName) ?>. All rights reserved.
    </footer>

    <script src="js/client.js" defer></script>
    <script>
        lucide.createIcons();
        window.BOOKING_CLIENT = <?= json_encode($client) ?>;
        document.addEventListener("DOMContentLoaded", () => {
            const textEl = document.getElementById("typingText");

            const lines = [
                "Take a breath.<br>You’re in good hands."
            ];

            let lineIndex = 0;
            let charIndex = 0;
            let isDeleting = false;

            const typingSpeed = 70; // typing speed
            const deletingSpeed = 40; // deleting speed
            const pauseAfterTyping = 1800;
            const pauseAfterDeleting = 600;

            function typeLoop() {
                const current = lines[lineIndex];
                const plainText = current.replace(/<br>/g, "\n");

                if (!isDeleting) {
                    charIndex++;
                } else {
                    charIndex--;
                }

                const visibleText = plainText.substring(0, charIndex)
                    .replace(/\n/g, "<br>");

                textEl.innerHTML = visibleText;

                if (!isDeleting && charIndex === plainText.length) {
                    setTimeout(() => isDeleting = true, pauseAfterTyping);
                } else if (isDeleting && charIndex === 0) {
                    isDeleting = false;
                    lineIndex = (lineIndex + 1) % lines.length;
                    setTimeout(() => {}, pauseAfterDeleting);
                }

                const speed = isDeleting ? deletingSpeed : typingSpeed;
                setTimeout(typeLoop, speed);
            }

            typeLoop();
        });
    </script>

</body>

</html>