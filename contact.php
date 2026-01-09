<?php
// session_start();
require_once "config/db.php";

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $subject = mysqli_real_escape_string($conn, $_POST['subject']);
    $message = mysqli_real_escape_string($conn, $_POST['message']);

    // In a real application, you would:
    // 1. Send email using PHPMailer or similar
    // 2. Save to database
    // 3. Send notification

    // For demo purposes, we'll just show success message
    $success = "Thank you for your message! We'll get back to you within 24 hours.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us - MediCare Pharma Management System</title>
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

        .form-input:focus {
            box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.1);
            border-color: var(--primary-yellow);
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
            <h1 class="text-5xl md:text-6xl font-bold mb-6">Contact Us</h1>
            <p class="text-xl text-yellow-100 max-w-3xl mx-auto mb-8">
                Have questions? Our team is here to help you transform your pharmacy operations.
            </p>
            <div class="flex justify-center space-x-4">
                <a href="mailto:support@medicarepharma.com" class="bg-white text-yellow-600 px-8 py-3 rounded-xl font-semibold hover:shadow-2xl transition-all duration-300 hover:scale-105">
                    <i class="fas fa-envelope mr-2"></i>Email Us
                </a>
                <a href="tel:+11234567890" class="border-2 border-white text-white px-8 py-3 rounded-xl font-semibold hover:bg-white/10 transition-all duration-300">
                    <i class="fas fa-phone mr-2"></i>Call Now
                </a>
            </div>
        </div>
    </section>

    <!-- Contact Form & Info -->
    <section class="py-24 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid lg:grid-cols-2 gap-12">
                <!-- Contact Info -->
                <div class="space-y-8">
                    <div class="text-center lg:text-left mb-12">
                        <h2 class="text-4xl md:text-5xl font-bold mb-6">
                            Get in <span class="gradient-text">Touch</span>
                        </h2>
                        <p class="text-xl text-gray-600">
                            We're always happy to hear from pharmacies looking to improve their operations.
                        </p>
                    </div>

                    <!-- Contact Cards -->
                    <div class="space-y-6">
                        <div class="stat-card p-8 rounded-2xl animate-fade-in-up">
                            <div class="flex items-center space-x-6">
                                <div class="w-16 h-16 rounded-2xl gradient-yellow flex items-center justify-center">
                                    <i class="fas fa-map-marker-alt text-white text-2xl"></i>
                                </div>
                                <div>
                                    <h3 class="text-xl font-bold text-gray-800 mb-2">Visit Our Office</h3>
                                    <p class="text-gray-600">123 Pharmacy Street, Medical District</p>
                                    <p class="text-gray-600">Healthcare City, HC 12345</p>
                                    <a href="#" class="text-yellow-600 hover:text-yellow-700 text-sm font-medium inline-flex items-center mt-2">
                                        <i class="fas fa-directions mr-2"></i> Get Directions
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div class="stat-card p-8 rounded-2xl animate-fade-in-up" style="animation-delay: 0.1s">
                            <div class="flex items-center space-x-6">
                                <div class="w-16 h-16 rounded-2xl gradient-gray flex items-center justify-center">
                                    <i class="fas fa-phone-alt text-white text-2xl"></i>
                                </div>
                                <div>
                                    <h3 class="text-xl font-bold text-gray-800 mb-2">Call Us</h3>
                                    <p class="text-gray-600">General Inquiries: <a href="tel:+11234567890" class="text-yellow-600 hover:text-yellow-700 font-medium">(123) 456-7890</a></p>
                                    <p class="text-gray-600">Support: <a href="tel:+11234567891" class="text-yellow-600 hover:text-yellow-700 font-medium">(123) 456-7891</a></p>
                                    <p class="text-gray-600 text-sm mt-2">Mon-Fri: 9AM-6PM | Sat: 10AM-4PM</p>
                                </div>
                            </div>
                        </div>

                        <div class="stat-card p-8 rounded-2xl animate-fade-in-up" style="animation-delay: 0.2s">
                            <div class="flex items-center space-x-6">
                                <div class="w-16 h-16 rounded-2xl gradient-yellow flex items-center justify-center">
                                    <i class="fas fa-envelope text-white text-2xl"></i>
                                </div>
                                <div>
                                    <h3 class="text-xl font-bold text-gray-800 mb-2">Email Us</h3>
                                    <p class="text-gray-600">General: <a href="mailto:info@medicarepharma.com" class="text-yellow-600 hover:text-yellow-700 font-medium">info@medicarepharma.com</a></p>
                                    <p class="text-gray-600">Support: <a href="mailto:support@medicarepharma.com" class="text-yellow-600 hover:text-yellow-700 font-medium">support@medicarepharma.com</a></p>
                                    <p class="text-gray-600">Sales: <a href="mailto:sales@medicarepharma.com" class="text-yellow-600 hover:text-yellow-700 font-medium">sales@medicarepharma.com</a></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Business Hou= -->
                    <div class="stat-card p-8 rounded-2xl mt-8">
                        <h3 class="text-xl font-bold text-gray-800 mb-6">Business Hou=</h3>
                        <div class="space-y-4">
                            <div class="flex justify-between items-center">
                                <span class="text-gray-700">Monday - Friday</span>
                                <span class="font-medium text-gray-800">9:00 AM - 6:00 PM</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-700">Saturday</span>
                                <span class="font-medium text-gray-800">10:00 AM - 4:00 PM</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-700">Sunday</span>
                                <span class="font-medium text-gray-800 text-red-500">Closed</span>
                            </div>
                        </div>
                        <div class="mt-6 pt-6 border-t border-gray-100">
                            <p class="text-sm text-gray-600">
                                <i class="fas fa-info-circle text-yellow-500 mr-2"></i>
                                Emergency support available 24/7 for system issues
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Contact Form -->
                <div class="glass-card p-8 rounded-2xl">
                    <h3 class="text-3xl font-bold text-gray-800 mb-2">Send Us a Message</h3>
                    <p class="text-gray-600 mb-8">Fill out the form below and we'll get back to you soon.</p>

                    <?php if ($success): ?>
                        <div class="mb-8 bg-gradient-to-r from-green-50 to-green-100 border-l-4 border-green-500 p-6 rounded-lg">
                            <div class="flex items-center">
                                <div class="w-12 h-12 rounded-full bg-green-100 flex items-center justify-center mr-4">
                                    <i class="fas fa-check-circle text-green-600 text-xl"></i>
                                </div>
                                <div>
                                    <h4 class="font-bold text-green-800 text-lg">Message Sent Successfully!</h4>
                                    <p class="text-green-700"><?php echo htmlspecialchars($success); ?></p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($error): ?>
                        <div class="mb-8 bg-gradient-to-r from-red-50 to-red-100 border-l-4 border-red-500 p-6 rounded-lg">
                            <div class="flex items-center">
                                <div class="w-12 h-12 rounded-full bg-red-100 flex items-center justify-center mr-4">
                                    <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                                </div>
                                <div>
                                    <h4 class="font-bold text-red-800 text-lg">Error</h4>
                                    <p class="text-red-700"><?php echo htmlspecialchars($error); ?></p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <form method="POST" class="space-y-6">
                        <div class="grid md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-gray-700 mb-2 font-medium">
                                    <i class="fas fa-user text-yellow-500 mr-2"></i>
                                    Full Name *
                                </label>
                                <input type="text"
                                    name="name"
                                    class="form-input w-full px-4 py-3 rounded-lg border border-gray-300 focus:border-yellow-500 focus:ring-2 focus:ring-yellow-200 transition"
                                    placeholder="Your name"
                                    required>
                            </div>

                            <div>
                                <label class="block text-gray-700 mb-2 font-medium">
                                    <i class="fas fa-envelope text-yellow-500 mr-2"></i>
                                    Email Address *
                                </label>
                                <input type="email"
                                    name="email"
                                    class="form-input w-full px-4 py-3 rounded-lg border border-gray-300 focus:border-yellow-500 focus:ring-2 focus:ring-yellow-200 transition"
                                    placeholder="your@email.com"
                                    required>
                            </div>
                        </div>

                        <div>
                            <label class="block text-gray-700 mb-2 font-medium">
                                <i class="fas fa-tag text-yellow-500 mr-2"></i>
                                Subject *
                            </label>
                            <input type="text"
                                name="subject"
                                class="form-input w-full px-4 py-3 rounded-lg border border-gray-300 focus:border-yellow-500 focus:ring-2 focus:ring-yellow-200 transition"
                                placeholder="How can we help?"
                                required>
                        </div>

                        <div>
                            <label class="block text-gray-700 mb-2 font-medium">
                                <i class="fas fa-comment text-yellow-500 mr-2"></i>
                                Message *
                            </label>
                            <textarea name="message"
                                rows="6"
                                class="form-input w-full px-4 py-3 rounded-lg border border-gray-300 focus:border-yellow-500 focus:ring-2 focus:ring-yellow-200 transition"
                                placeholder="Tell us about your pharmacy needs..."
                                required></textarea>
                        </div>

                        <div class="flex items-center">
                            <input type="checkbox"
                                id="newsletter"
                                name="newsletter"
                                class="w-4 h-4 text-yellow-600 border-gray-300 rounded focus:ring-yellow-500"
                                checked>
                            <label for="newsletter" class="ml-2 text-gray-700 text-sm">
                                Subscribe to our newsletter for updates and tips
                            </label>
                        </div>

                        <button type="submit"
                            class="w-full gradient-yellow text-white py-4 rounded-xl font-bold text-lg hover:shadow-xl transition-all duration-300 hover:scale-[1.02] flex items-center justify-center">
                            <i class="fas fa-paper-plane mr-3"></i>
                            Send Message
                        </button>

                        <p class="text-center text-gray-500 text-sm">
                            <i class="fas fa-shield-alt text-yellow-500 mr-2"></i>
                            Your information is secure and will never be shared with third parties.
                        </p>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <!-- FAQ Section -->
    <section class="py-24 bg-gradient-to-b from-yellow-50 to-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-4xl md:text-5xl font-bold mb-6">
                    Frequently Asked <span class="gradient-text">Questions</span>
                </h2>
                <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                    Find quick answe= to common questions about our pharmacy system.
                </p>
            </div>

            <div class="grid md:grid-cols-2 gap-8 max-w-4xl mx-auto">
                <!-- FAQ 1 -->
                <div class="stat-card p-6 rounded-2xl animate-fade-in-up">
                    <div class="flex items-start space-x-4">
                        <div class="w-10 h-10 rounded-lg gradient-yellow flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-question text-white"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-gray-800 mb-2">How long does setup take?</h3>
                            <p class="text-gray-600">Most pharmacies are up and running within 24 hou=. Our setup wizard guides you through the process step by step.</p>
                        </div>
                    </div>
                </div>

                <!-- FAQ 2 -->
                <div class="stat-card p-6 rounded-2xl animate-fade-in-up" style="animation-delay: 0.1s">
                    <div class="flex items-start space-x-4">
                        <div class="w-10 h-10 rounded-lg gradient-gray flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-question text-white"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-gray-800 mb-2">Is there a free trial?</h3>
                            <p class="text-gray-600">Yes! We offer a 14-day free trial with full access to all features. No credit card required.</p>
                        </div>
                    </div>
                </div>

                <!-- FAQ 3 -->
                <div class="stat-card p-6 rounded-2xl animate-fade-in-up" style="animation-delay: 0.2s">
                    <div class="flex items-start space-x-4">
                        <div class="w-10 h-10 rounded-lg gradient-yellow flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-question text-white"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-gray-800 mb-2">Do you provide training?</h3>
                            <p class="text-gray-600">Absolutely. We provide comprehensive training resources, video tutorials, and live training sessions for your team.</p>
                        </div>
                    </div>
                </div>

                <!-- FAQ 4 -->
                <div class="stat-card p-6 rounded-2xl animate-fade-in-up" style="animation-delay: 0.3s">
                    <div class="flex items-start space-x-4">
                        <div class="w-10 h-10 rounded-lg gradient-gray flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-question text-white"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-gray-800 mb-2">Can I import existing data?</h3>
                            <p class="text-gray-600">Yes, we support CSV imports for medicines, custome=, and supplie=. Our support team can help with data migration.</p>
                        </div>
                    </div>
                </div>

                <!-- FAQ 5 -->
                <div class="stat-card p-6 rounded-2xl animate-fade-in-up" style="animation-delay: 0.4s">
                    <div class="flex items-start space-x-4">
                        <div class="w-10 h-10 rounded-lg gradient-yellow flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-question text-white"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-gray-800 mb-2">Is the system mobile-friendly?</h3>
                            <p class="text-gray-600">Yes, our system is fully responsive and works perfectly on tablets and smartphones. We also offer dedicated mobile apps.</p>
                        </div>
                    </div>
                </div>

                <!-- FAQ 6 -->
                <div class="stat-card p-6 rounded-2xl animate-fade-in-up" style="animation-delay: 0.5s">
                    <div class="flex items-start space-x-4">
                        <div class="w-10 h-10 rounded-lg gradient-gray flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-question text-white"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-gray-800 mb-2">What about data security?</h3>
                            <p class="text-gray-600">We use enterprise-grade security with encryption, regular backups, and secure data cente= to protect your information.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="text-center mt-12">
                <p class="text-gray-600">
                    Still have questions?
                    <a href="mailto:support@medicarepharma.com" class="text-yellow-600 font-semibold hover:text-yellow-700 ml-1">
                        Email our support team
                    </a>
                </p>
            </div>
        </div>
    </section>

    <!-- Map Section -->
    <section class="py-24 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-4xl md:text-5xl font-bold mb-6">
                    Find Our <span class="gradient-text">Office</span>
                </h2>
                <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                    Visit us at our headquarte= or connect with our regional offices.
                </p>
            </div>

            <div class="grid lg:grid-cols-3 gap-8 mb-12">
                <!-- Headquarte= -->
                <div class="stat-card p-8 rounded-2xl text-center">
                    <div class="w-16 h-16 rounded-2xl gradient-yellow flex items-center justify-center mx-auto mb-6">
                        <i class="fas fa-building text-white text-2xl"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-800 mb-4">Headquarte=</h3>
                    <p class="text-gray-600 mb-4">
                        123 Pharmacy Street<br>
                        Medical District<br>
                        Healthcare City, HC 12345
                    </p>
                    <a href="#" class="text-yellow-600 font-medium inline-flex items-center">
                        <i class="fas fa-map-marker-alt mr-2"></i>
                        View on Map
                    </a>
                </div>

                <!-- Regional Office 1 -->
                <div class="stat-card p-8 rounded-2xl text-center">
                    <div class="w-16 h-16 rounded-2xl gradient-gray flex items-center justify-center mx-auto mb-6">
                        <i class="fas fa-map text-white text-2xl"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-800 mb-4">Regional Office - North</h3>
                    <p class="text-gray-600 mb-4">
                        456 Medical Plaza<br>
                        North Business Park<br>
                        Healthcare City, HC 67890
                    </p>
                    <a href="#" class="text-yellow-600 font-medium inline-flex items-center">
                        <i class="fas fa-map-marker-alt mr-2"></i>
                        View on Map
                    </a>
                </div>

                <!-- Regional Office 2 -->
                <div class="stat-card p-8 rounded-2xl text-center">
                    <div class="w-16 h-16 rounded-2xl gradient-yellow flex items-center justify-center mx-auto mb-6">
                        <i class="fas fa-map-signs text-white text-2xl"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-800 mb-4">Regional Office - South</h3>
                    <p class="text-gray-600 mb-4">
                        789 Health Avenue<br>
                        South Innovation Center<br>
                        Medical City, MC 24680
                    </p>
                    <a href="#" class="text-yellow-600 font-medium inline-flex items-center">
                        <i class="fas fa-map-marker-alt mr-2"></i>
                        View on Map
                    </a>
                </div>
            </div>

            <!-- Map Placeholder -->
            <div class="glass-card rounded-2xl overflow-hidden">
                <div class="aspect-video bg-gradient-to-br from-yellow-50 to-gray-50 flex items-center justify-center">
                    <div class="text-center">
                        <div class="w-20 h-20 rounded-2xl gradient-yellow flex items-center justify-center mx-auto mb-6">
                            <i class="fas fa-map-marker-alt text-white text-3xl"></i>
                        </div>
                        <h3 class="text-2xl font-bold text-gray-800 mb-2">Interactive Map</h3>
                        <p class="text-gray-600">Location map would appear here</p>
                        <div class="mt-6">
                            <a href="#" class="gradient-yellow text-white px-6 py-3 rounded-xl font-semibold inline-flex items-center hover:shadow-xl transition-all duration-300">
                                <i class="fas fa-external-link-alt mr-2"></i>
                                Open in Google Maps
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="py-24 gradient-yellow text-white relative overflow-hidden">
        <div class="max-w-4xl mx-auto px-4 text-center relative z-10">
            <h2 class="text-4xl md:text-5xl font-bold mb-6">
                Ready to Get Started?
            </h2>
            <p class="text-xl text-yellow-100 mb-10 max-w-2xl mx-auto">
                Contact us today to schedule a pe=onalized demo or start your free trial.
            </p>
            <div class="flex flex-col sm:flex-row gap-6 justify-center items-center">
                <a href="/auth/login.php"
                    class="bg-white text-yellow-600 px-10 py-4 rounded-xl font-bold text-lg hover:shadow-2xl transition-all duration-300 hover:scale-105 flex items-center space-x-3">
                    <i class="fas fa-play-circle"></i>
                    <span>Start Free Trial</span>
                </a>
                <a href="tel:+11234567890"
                    class="border-2 border-white text-white px-10 py-4 rounded-xl font-bold text-lg hover:bg-white/10 transition-all duration-300 flex items-center space-x-3">
                    <i class="fas fa-phone-alt"></i>
                    <span>Call Now: (123) 456-7890</span>
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

            const observer = new Inte=ectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isInte=ecting) {
                        entry.target.classList.add('animate-fade-in-up');
                    }
                });
            }, observerOptions);

            // Observe all stat cards
            document.querySelectorAll('.stat-card').forEach(el => {
                observer.observe(el);
            });
        });
    </script>
</body>

</html>