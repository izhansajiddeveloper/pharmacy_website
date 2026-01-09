<?php
// session_start();     
require_once "config/db.php";
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us - MediCare Pharma Management System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root {
            --primary-yellow: #704a08ff;
            --primary-yellow-light: #d49b0aff;
            --primary-yellow-dark: #d97706;
            --primary-gray: #6b7280;
            --primary-gray-light: #9ca3af;
            --primary-gray-dark: #4b5563;
        }

        body {
            background: linear-gradient(135deg, #fef3c7 0%, #f5f5f4 50%, #fef3c7 100%);
            min-height: 100vh;
        }

        .gradient-yellow {
            background: linear-gradient(135deg, var(--primary-yellow), var(--primary-yellow-light));
        }

        .gradient-gray {
            background: linear-gradient(135deg, var(--primary-gray), var(--primary-gray-light));
        }

        .gradient-mixed {
            background: linear-gradient(135deg, var(--primary-yellow), var(--primary-gray));
        }

        .glass-card {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }

        .stat-card {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.95), rgba(255, 255, 255, 0.85));
            border: 1px solid rgba(251, 191, 36, 0.3);
            box-shadow: 0 4px 20px rgba(251, 191, 36, 0.1);
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 30px rgba(251, 191, 36, 0.2);
            transition: all 0.3s ease;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-fade-in-up {
            animation: fadeInUp 0.6s ease-out forwards;
        }

        @keyframes float {

            0%,
            100% {
                transform: translateY(0);
            }

            50% {
                transform: translateY(-10px);
            }
        }

        .animate-float {
            animation: float 3s ease-in-out infinite;
        }

        .yellow-blob {
            position: absolute;
            width: 300px;
            height: 300px;
            background: linear-gradient(135deg, var(--primary-yellow), var(--primary-yellow-light));
            border-radius: 50%;
            filter: blur(40px);
            opacity: 0.2;
            z-index: -1;
        }

        .gray-blob {
            position: absolute;
            width: 250px;
            height: 250px;
            background: linear-gradient(135deg, var(--primary-gray), var(--primary-gray-light));
            border-radius: 50%;
            filter: blur(40px);
            opacity: 0.15;
            z-index: -1;
        }

        .gradient-text {
            background: linear-gradient(45deg, var(--primary-yellow), var(--primary-yellow-dark), var(--primary-gray));
            background-size: 200% 200%;
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            animation: gradient 3s ease infinite;
        }

        @keyframes gradient {
            0% {
                background-position: 0% 50%;
            }

            50% {
                background-position: 100% 50%;
            }

            100% {
                background-position: 0% 50%;
            }
        }

        .timeline-item::before {
            content: '';
            position: absolute;
            left: -20px;
            top: 10px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-yellow), var(--primary-yellow-light));
            box-shadow: 0 0 0 4px rgba(245, 158, 11, 0.2);
        }
    </style>
</head>

<body class="min-h-screen overflow-x-hidden">
    <!-- Background Blobs -->
    <div class="yellow-blob top-20 right-10 animate-float"></div>
    <div class="gray-blob bottom-20 left-10 animate-float" style="animation-delay: 1s;"></div>
    <div class="yellow-blob bottom-40 right-40 animate-float" style="animation-delay: 2s; width: 200px; height: 200px;"></div>

    <!-- Navbar -->
    <?php include "includes/navbar.php"; ?>

    <!-- Hero Section -->
    <section class="gradient-yellow text-white relative overflow-hidden">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-24 text-center relative z-10">
            <h1 class="text-5xl md:text-6xl font-bold mb-6">About MediCare Pharma</h1>
            <p class="text-xl text-yellow-100 max-w-3xl mx-auto mb-8">
                Revolutionizing pharmacy management with our professional black & yellow themed system since 2015.
            </p>
            <div class="flex justify-center space-x-4">
                <a href="/auth/login.php" class="bg-white text-yellow-600 px-8 py-3 rounded-xl font-semibold hover:shadow-2xl transition-all duration-300 hover:scale-105">
                    <i class="fas fa-sign-in-alt mr-2"></i>Login to System
                </a>
                <a href="/contact.php" class="border-2 border-white text-white px-8 py-3 rounded-xl font-semibold hover:bg-white/10 transition-all duration-300">
                    <i class="fas fa-envelope mr-2"></i>Contact Us
                </a>
            </div>
        </div>
    </section>

    <!-- Mission & Vision -->
    <section class="py-24 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid lg:grid-cols-2 gap-12">
                <!-- Mission -->
                <div class="stat-card rounded-2xl p-8 animate-fade-in-up">
                    <div class="w-16 h-16 rounded-2xl gradient-yellow flex items-center justify-center mb-6">
                        <i class="fas fa-bullseye text-white text-2xl"></i>
                    </div>
                    <h3 class="text-3xl font-bold text-gray-800 mb-4">Our Mission</h3>
                    <p class="text-gray-600 mb-6 leading-relaxed">
                        To empower pharmacies with intuitive, reliable, and professional management software that
                        simplifies operations while enhancing patient care through technology.
                    </p>
                    <div class="space-y-4">
                        <div class="flex items-start space-x-3">
                            <i class="fas fa-check-circle text-yellow-500 mt-1"></i>
                            <span class="text-gray-700">Simplify pharmacy operations</span>
                        </div>
                        <div class="flex items-start space-x-3">
                            <i class="fas fa-check-circle text-yellow-500 mt-1"></i>
                            <span class="text-gray-700">Enhance patient care experience</span>
                        </div>
                        <div class="flex items-start space-x-3">
                            <i class="fas fa-check-circle text-yellow-500 mt-1"></i>
                            <span class="text-gray-700">Provide real-time business insights</span>
                        </div>
                    </div>
                </div>

                <!-- Vision -->
                <div class="stat-card rounded-2xl p-8 animate-fade-in-up" style="animation-delay: 0.2s">
                    <div class="w-16 h-16 rounded-2xl gradient-gray flex items-center justify-center mb-6">
                        <i class="fas fa-eye text-white text-2xl"></i>
                    </div>
                    <h3 class="text-3xl font-bold text-gray-800 mb-4">Our Vision</h3>
                    <p class="text-gray-600 mb-6 leading-relaxed">
                        To be the most trusted pharmacy management platform worldwide, recognized for innovation,
                        reliability, and excellence in healthcare technology solutions.
                    </p>
                    <div class="space-y-4">
                        <div class="flex items-start space-x-3">
                            <i class="fas fa-star text-yellow-500 mt-1"></i>
                            <span class="text-gray-700">Global pharmacy technology leader</span>
                        </div>
                        <div class="flex items-start space-x-3">
                            <i class="fas fa-star text-yellow-500 mt-1"></i>
                            <span class="text-gray-700">Innovation in healthcare tech</span>
                        </div>
                        <div class="flex items-start space-x-3">
                            <i class="fas fa-star text-yellow-500 mt-1"></i>
                            <span class="text-gray-700">Trusted by healthcare professionals</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Our Story -->
    <section class="py-24 bg-gradient-to-b from-yellow-50 to-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-4xl md:text-5xl font-bold mb-6">
                    Our <span class="gradient-text">Journey</span>
                </h2>
                <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                    From a small startup to a leading pharmacy management solution provider.
                </p>
            </div>

            <!-- Timeline -->
            <div class="relative max-w-4xl mx-auto">
                <!-- Timeline line -->
                <div class="absolute left-8 md:left-1/2 h-full w-1 bg-gradient-to-b from-yellow-400 to-gray-400 transform -translate-x-1/2"></div>

                <!-- Timeline items -->
                <div class="space-y-12">
                    <!-- 2015 -->
                    <div class="relative timeline-item">
                        <div class="md:flex items-center">
                            <div class="md:w-1/2 md:pr-12 md:text-right mb-4 md:mb-0">
                                <div class="inline-block stat-card px-6 py-4 rounded-xl">
                                    <div class="text-2xl font-bold text-yellow-600 mb-2">2015</div>
                                    <h3 class="text-xl font-bold text-gray-800">Foundation</h3>
                                    <p class="text-gray-600">Founded with a vision to modernize pharmacy management</p>
                                </div>
                            </div>
                            <div class="md:w-1/2 md:pl-12">
                                <div class="hidden md:block"></div>
                            </div>
                        </div>
                    </div>

                    <!-- 2017 -->
                    <div class="relative timeline-item">
                        <div class="md:flex items-center">
                            <div class="md:w-1/2 md:pr-12">
                                <div class="hidden md:block"></div>
                            </div>
                            <div class="md:w-1/2 md:pl-12 mb-4 md:mb-0">
                                <div class="inline-block stat-card px-6 py-4 rounded-xl">
                                    <div class="text-2xl font-bold text-gray-600 mb-2">2017</div>
                                    <h3 class="text-xl font-bold text-gray-800">Fi=t Major Release</h3>
                                    <p class="text-gray-600">Launched our fi=t comprehensive pharmacy management system</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 2019 -->
                    <div class="relative timeline-item">
                        <div class="md:flex items-center">
                            <div class="md:w-1/2 md:pr-12 md:text-right mb-4 md:mb-0">
                                <div class="inline-block stat-card px-6 py-4 rounded-xl">
                                    <div class="text-2xl font-bold text-yellow-600 mb-2">2019</div>
                                    <h3 class="text-xl font-bold text-gray-800">Cloud Migration</h3>
                                    <p class="text-gray-600">Successfully migrated to cloud infrastructure</p>
                                </div>
                            </div>
                            <div class="md:w-1/2 md:pl-12">
                                <div class="hidden md:block"></div>
                            </div>
                        </div>
                    </div>

                    <!-- 2021 -->
                    <div class="relative timeline-item">
                        <div class="md:flex items-center">
                            <div class="md:w-1/2 md:pr-12">
                                <div class="hidden md:block"></div>
                            </div>
                            <div class="md:w-1/2 md:pl-12 mb-4 md:mb-0">
                                <div class="inline-block stat-card px-6 py-4 rounded-xl">
                                    <div class="text-2xl font-bold text-gray-600 mb-2">2021</div>
                                    <h3 class="text-xl font-bold text-gray-800">Mobile App Launch</h3>
                                    <p class="text-gray-600">Released mobile applications for iOS and Android</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 2023 -->
                    <div class="relative timeline-item">
                        <div class="md:flex items-center">
                            <div class="md:w-1/2 md:pr-12 md:text-right mb-4 md:mb-0">
                                <div class="inline-block stat-card px-6 py-4 rounded-xl">
                                    <div class="text-2xl font-bold text-yellow-600 mb-2">2023</div>
                                    <h3 class="text-xl font-bold text-gray-800">Ve=ion 2.0</h3>
                                    <p class="text-gray-600">Launched our black & yellow themed professional system</p>
                                </div>
                            </div>
                            <div class="md:w-1/2 md:pl-12">
                                <div class="hidden md:block"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Team Section -->
    <section class="py-24 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-4xl md:text-5xl font-bold mb-6">
                    Meet Our <span class="gradient-text">Leade=hip</span>
                </h2>
                <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                    Experienced professionals dedicated to pharmacy technology innovation.
                </p>
            </div>

            <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-8">
                <!-- Team Member 1 -->
                <div class="stat-card rounded-2xl p-6 text-center animate-fade-in-up">
                    <div class="w-32 h-32 gradient-yellow rounded-full mx-auto mb-6 flex items-center justify-center text-white text-4xl font-bold">
                        DR
                    </div>
                    <h3 class="text-xl font-bold text-gray-800 mb-2">Dr. Rajesh Kumar</h3>
                    <p class="text-yellow-600 font-medium mb-3">Founder & CEO</p>
                    <p class="text-gray-600 text-sm mb-4">Pharmacist with 15+ yea= of experience in healthcare technology</p>
                    <div class="flex justify-center space-x-3">
                        <a href="#" class="text-gray-400 hover:text-yellow-600">
                            <i class="fab fa-linkedin-in"></i>
                        </a>
                        <a href="#" class="text-gray-400 hover:text-yellow-600">
                            <i class="fab fa-twitter"></i>
                        </a>
                    </div>
                </div>

                <!-- Team Member 2 -->
                <div class="stat-card rounded-2xl p-6 text-center animate-fade-in-up" style="animation-delay: 0.1s">
                    <div class="w-32 h-32 gradient-gray rounded-full mx-auto mb-6 flex items-center justify-center text-white text-4xl font-bold">
                        AP
                    </div>
                    <h3 class="text-xl font-bold text-gray-800 mb-2">Anita Patel</h3>
                    <p class="text-gray-600 font-medium mb-3">CTO</p>
                    <p class="text-gray-600 text-sm mb-4">Software architect specializing in healthcare systems</p>
                    <div class="flex justify-center space-x-3">
                        <a href="#" class="text-gray-400 hover:text-yellow-600">
                            <i class="fab fa-linkedin-in"></i>
                        </a>
                        <a href="#" class="text-gray-400 hover:text-yellow-600">
                            <i class="fab fa-github"></i>
                        </a>
                    </div>
                </div>

                <!-- Team Member 3 -->
                <div class="stat-card rounded-2xl p-6 text-center animate-fade-in-up" style="animation-delay: 0.2s">
                    <div class="w-32 h-32 gradient-yellow rounded-full mx-auto mb-6 flex items-center justify-center text-white text-4xl font-bold">
                        SM
                    </div>
                    <h3 class="text-xl font-bold text-gray-800 mb-2">Sanjay Mehta</h3>
                    <p class="text-yellow-600 font-medium mb-3">Head of Operations</p>
                    <p class="text-gray-600 text-sm mb-4">Former pharmacy chain manager with operational expertise</p>
                    <div class="flex justify-center space-x-3">
                        <a href="#" class="text-gray-400 hover:text-yellow-600">
                            <i class="fab fa-linkedin-in"></i>
                        </a>
                        <a href="#" class="text-gray-400 hover:text-yellow-600">
                            <i class="fab fa-twitter"></i>
                        </a>
                    </div>
                </div>

                <!-- Team Member 4 -->
                <div class="stat-card rounded-2xl p-6 text-center animate-fade-in-up" style="animation-delay: 0.3s">
                    <div class="w-32 h-32 gradient-gray rounded-full mx-auto mb-6 flex items-center justify-center text-white text-4xl font-bold">
                        PP
                    </div>
                    <h3 class="text-xl font-bold text-gray-800 mb-2">Priya Sharma</h3>
                    <p class="text-gray-600 font-medium mb-3">Customer Success Lead</p>
                    <p class="text-gray-600 text-sm mb-4">Ensuring pharmacy success with dedicated support</p>
                    <div class="flex justify-center space-x-3">
                        <a href="#" class="text-gray-400 hover:text-yellow-600">
                            <i class="fab fa-linkedin-in"></i>
                        </a>
                        <a href="#" class="text-gray-400 hover:text-yellow-600">
                            <i class="fas fa-envelope"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Values Section -->
    <section class="py-24 bg-gradient-to-b from-gray-900 to-gray-800 text-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-4xl md:text-5xl font-bold mb-6">
                    Our <span class="text-yellow-400">Values</span>
                </h2>
                <p class="text-xl text-gray-300 max-w-3xl mx-auto">
                    The principles that guide everything we do at MediCare Pharma.
                </p>
            </div>

            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
                <!-- Value 1 -->
                <div class="glass-card p-8 rounded-2xl">
                    <div class="w-16 h-16 rounded-2xl gradient-yellow flex items-center justify-center mb-6">
                        <i class="fas fa-lightbulb text-white text-2xl"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-white mb-4">Innovation</h3>
                    <p class="text-gray-300">
                        Continuously improving our technology to stay ahead of pharmacy industry needs.
                    </p>
                </div>

                <!-- Value 2 -->
                <div class="glass-card p-8 rounded-2xl">
                    <div class="w-16 h-16 rounded-2xl gradient-gray flex items-center justify-center mb-6">
                        <i class="fas fa-handshake text-white text-2xl"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-white mb-4">Integrity</h3>
                    <p class="text-gray-300">
                        Maintaining transparency and honesty in all our operations and partne=hips.
                    </p>
                </div>

                <!-- Value 3 -->
                <div class="glass-card p-8 rounded-2xl">
                    <div class="w-16 h-16 rounded-2xl gradient-yellow flex items-center justify-center mb-6">
                        <i class="fas fa-heart text-white text-2xl"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-white mb-4">Customer Care</h3>
                    <p class="text-gray-300">
                        Putting pharmacies and their patients at the center of everything we build.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="py-24 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-8">
                <div class="stat-card rounded-2xl p-8 text-center">
                    <div class="text-4xl font-bold text-yellow-600 mb-2">500+</div>
                    <div class="text-gray-600">Pharmacies</div>
                    <div class="text-sm text-gray-400 mt-2">Trusting our system</div>
                </div>

                <div class="stat-card rounded-2xl p-8 text-center">
                    <div class="text-4xl font-bold text-gray-600 mb-2">50+</div>
                    <div class="text-gray-600">Team Membe=</div>
                    <div class="text-sm text-gray-400 mt-2">Dedicated professionals</div>
                </div>

                <div class="stat-card rounded-2xl p-8 text-center">
                    <div class="text-4xl font-bold text-yellow-600 mb-2">8+</div>
                    <div class="text-gray-600">Yea=</div>
                    <div class="text-sm text-gray-400 mt-2">Of innovation</div>
                </div>

                <div class="stat-card rounded-2xl p-8 text-center">
                    <div class="text-4xl font-bold text-gray-600 mb-2">24/7</div>
                    <div class="text-gray-600">Support</div>
                    <div class="text-sm text-gray-400 mt-2">Always available</div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="py-24 gradient-yellow text-white relative overflow-hidden">
        <div class="max-w-4xl mx-auto px-4 text-center relative z-10">
            <h2 class="text-4xl md:text-5xl font-bold mb-6">
                Ready to Transform Your Pharmacy?
            </h2>
            <p class="text-xl text-yellow-100 mb-10 max-w-2xl mx-auto">
                Join hundreds of successful pharmacies using our professional management system.
            </p>
            <div class="flex flex-col sm:flex-row gap-6 justify-center items-center">
                <a href="/auth/login.php"
                    class="bg-white text-yellow-600 px-10 py-4 rounded-xl font-bold text-lg hover:shadow-2xl transition-all duration-300 hover:scale-105 flex items-center space-x-3">
                    <i class="fas fa-sign-in-alt"></i>
                    <span>Login Now</span>
                </a>
                <a href="/contact.php"
                    class="border-2 border-white text-white px-10 py-4 rounded-xl font-bold text-lg hover:bg-white/10 transition-all duration-300 flex items-center space-x-3">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Schedule Demo</span>
                </a>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <?php include "includes/footer.php"; ?>

    <script>
        // Add scroll animations
        document.addEventListener('DOMContentLoaded', function() {
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            };

            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('animate-fade-in-up');
                    }
                });
            }, observerOptions);

            // Observe all stat cards and timeline items
            document.querySelectorAll('.stat-card, .timeline-item, .glass-card').forEach(el => {
                observer.observe(el);
            });
        });
    </script>
</body>

</html>