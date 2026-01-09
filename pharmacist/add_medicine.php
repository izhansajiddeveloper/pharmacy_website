<?php
require_once "../config/db.php";
require_once "../includes/auth_check.php";

// Only pharmacist can add medicines
if ($_SESSION['role'] !== 'pharmacist') {
    $_SESSION['error'] = "You don't have permission to add medicines";
    header("Location: medicines.php");
    exit;
}

$success = false;
$error = '';

// Fetch categories, types, and unique generic names
$categories = mysqli_query($conn, "SELECT * FROM medicine_categories ORDER BY name");
$types = mysqli_query($conn, "SELECT * FROM medicine_types ORDER BY name");
$generic_names_result = mysqli_query($conn, "SELECT DISTINCT generic_name FROM medicines WHERE generic_name != '' ORDER BY generic_name");

if (isset($_POST['submit'])) {
    $name = trim($_POST['name'] ?? '');
    $generic_name = trim($_POST['generic_name'] ?? '');
    $category_id = intval($_POST['category_id'] ?? 0);
    $type_id = intval($_POST['type_id'] ?? 0);
    $description = trim($_POST['description'] ?? '');

    // Validate required fields
    if (empty($name) || empty($generic_name) || empty($category_id) || empty($type_id)) {
        $error = "Please fill in all required fields.";
    } else {
        // Use prepared statement — matches EXACT table structure
        $stmt = $conn->prepare("
            INSERT INTO medicines (name, generic_name, category_id, type_id, description, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");

        if ($stmt) {
            $stmt->bind_param('sssis', $name, $generic_name, $category_id, $type_id, $description);

            if ($stmt->execute()) {
                $success = true;
                $new_medicine_id = $stmt->insert_id;
                // Use PRG to avoid resubmission and clear POST
                header("Location: add_medicine.php?success=1&medicine_id=" . $new_medicine_id);
                exit;
            } else {
                $error = "Database error: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $error = "Failed to prepare statement: " . $conn->error;
        }
    }
}

// Handle success via redirect (PRG pattern)
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $success = true;
    $new_medicine_id = intval($_GET['medicine_id'] ?? 0);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Medicine - MediCare Pharma</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-yellow: #f59e0b;
            --primary-yellow-light: #fbbf24;
            --primary-yellow-dark: #d97706;
            --primary-gray: #6b7280;
            --primary-gray-light: #9ca3af;
            --primary-gray-dark: #4b5563;
            --accent-teal: #14b8a6;
            --accent-purple: #8b5cf6;
            --accent-blue: #3b82f6;
        }

        body {
            background: linear-gradient(135deg, #fef3c7 0%, #f5f5f4 50%, #fef3c7 100%);
            min-height: 100vh;
        }

        .glass-card {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }

        .glass-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 16px 48px rgba(0, 0, 0, 0.15);
            transition: all 0.3s ease;
        }

        .gradient-yellow {
            background: linear-gradient(135deg, var(--primary-yellow), var(--primary-yellow-light));
        }

        .gradient-text {
            background: linear-gradient(45deg, #f59e0b, #d97706);
            background-clip: text;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .form-input:focus {
            box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.2);
            border-color: var(--primary-yellow);
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

        .badge-category {
            background: linear-gradient(135deg, var(--accent-blue), #1d4ed8);
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }

        .badge-type {
            background: linear-gradient(135deg, var(--accent-purple), #7c3aed);
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }

        .relative-select {
            position: relative;
        }

        .relative-select select {
            appearance: none;
            padding-right: 2.5rem;
        }

        .relative-select i {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            pointer-events: none;
            color: #9ca3af;
        }
    </style>
</head>

<body class="min-h-screen overflow-x-hidden">
    <!-- Background Blobs -->
    <div class="yellow-blob top-20 right-10"></div>
    <div class="gray-blob bottom-20 left-10"></div>

    <!-- Navbar -->
    <?php include "../includes/navbar.php"; ?>

    <div class="flex">
        <!-- Sidebar -->
        <?php include "includes/pharmacist_sidebar.php"; ?>

        <!-- Main Content -->
        <main class="flex-1 overflow-hidden p-6">
            <!-- Success Message -->
            <?php if ($success): ?>
                <div class="glass-card rounded-2xl p-6 mb-6 animate-fade-in-up bg-gradient-to-r from-green-50 to-green-100 border-l-4 border-green-500">
                    <div class="flex items-center">
                        <div class="w-12 h-12 rounded-full bg-green-100 flex items-center justify-center mr-4 shadow">
                            <i class="fas fa-check-circle text-green-600 text-xl"></i>
                        </div>
                        <div class="flex-1">
                            <h3 class="text-lg font-bold text-gray-800 mb-1">Medicine Added Successfully!</h3>
                            <p class="text-gray-600 mb-3">New medicine has been added with ID:
                                <span class="font-semibold text-yellow-600">MED-<?php echo str_pad($new_medicine_id, 6, '0', STR_PAD_LEFT); ?></span>
                            </p>
                            <div class="flex space-x-3">
                                <a href="medicines.php" class="inline-flex items-center space-x-2 text-yellow-600 hover:text-yellow-800 font-medium px-4 py-2 bg-yellow-50 rounded-lg">
                                    <i class="fas fa-arrow-left"></i>
                                    <span>Back to Medicines</span>
                                </a>
                                <a href="add_stock.php?medicine_id=<?php echo $new_medicine_id; ?>" class="inline-flex items-center space-x-2 text-green-600 hover:text-green-800 font-medium px-4 py-2 bg-green-50 rounded-lg">
                                    <i class="fas fa-plus"></i>
                                    <span>Add Initial Stock</span>
                                </a>
                                <a href="add_medicine.php" class="inline-flex items-center space-x-2 text-blue-600 hover:text-blue-800 font-medium px-4 py-2 bg-blue-50 rounded-lg">
                                    <i class="fas fa-plus-circle"></i>
                                    <span>Add Another</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Error Message -->
            <?php if ($error): ?>
                <div class="glass-card rounded-2xl p-6 mb-6 animate-fade-in-up bg-gradient-to-r from-red-50 to-red-100 border-l-4 border-red-500">
                    <div class="flex items-center">
                        <div class="w-12 h-12 rounded-full bg-red-100 flex items-center justify-center mr-4 shadow">
                            <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-gray-800 mb-1">Error Adding Medicine</h3>
                            <p class="text-red-600"><?php echo htmlspecialchars($error); ?></p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Page Header -->
            <div class="glass-card rounded-2xl p-6 mb-6 animate-fade-in-up">
                <div class="flex flex-col lg:flex-row lg:items-center justify-between">
                    <div>
                        <h1 class="text-3xl lg:text-4xl font-bold text-gray-800 mb-2">
                            Add New <span class="gradient-text">Medicine</span>
                        </h1>
                        <p class="text-gray-600 flex items-center space-x-2">
                            <i class="fas fa-pills text-yellow-500"></i>
                            <span>Register a new medicine to the pharmacy inventory</span>
                            <span class="text-gray-400 mx-2">•</span>
                            <i class="fas fa-user-md text-blue-500"></i>
                            <span>Pharmacist Access</span>
                        </p>
                    </div>
                    <div class="mt-4 lg:mt-0 flex space-x-3">
                        <a href="medicines.php"
                            class="px-6 py-3 border border-yellow-200 text-gray-700 rounded-xl hover:bg-yellow-50 transition font-bold flex items-center space-x-2 shadow-sm">
                            <i class="fas fa-arrow-left"></i>
                            <span>Back to Medicines</span>
                        </a>
                        <button type="button" onclick="resetForm()"
                            class="px-6 py-3 border border-gray-200 text-gray-700 rounded-xl hover:bg-gray-50 transition font-bold flex items-center space-x-2 shadow-sm">
                            <i class="fas fa-redo"></i>
                            <span>Reset Form</span>
                        </button>
                    </div>
                </div>
            </div>

            <div class="grid lg:grid-cols-3 gap-6">
                <!-- Left Column - Form -->
                <div class="lg:col-span-2">
                    <div class="glass-card rounded-2xl overflow-hidden animate-fade-in-up" style="animation-delay: 0.1s">
                        <div class="px-6 py-4 border-b border-yellow-100 bg-gradient-to-r from-yellow-50 to-yellow-25">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-800">Medicine Details</h3>
                                    <p class="text-sm text-gray-600">Fill in the required information</p>
                                </div>
                                <div class="text-xs font-medium text-yellow-600 bg-yellow-100 px-3 py-1 rounded-full">
                                    <i class="fas fa-asterisk text-xs mr-1"></i> Required Fields
                                </div>
                            </div>
                        </div>
                        <form method="POST" class="p-6" id="medicineForm">
                            <div class="space-y-8">
                                <!-- Basic Information Section -->
                                <div>
                                    <h4 class="text-lg font-semibold text-gray-800 mb-4 flex items-center space-x-2">
                                        <i class="fas fa-info-circle text-blue-500"></i>
                                        <span>Basic Information</span>
                                    </h4>

                                    <div class="grid md:grid-cols-2 gap-6">

                                        <!-- Medicine Name -->
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                                <span class="flex items-center space-x-2">
                                                    <i class="fas fa-pills text-yellow-500 text-sm"></i>
                                                    <span>Medicine Name *</span>
                                                </span>
                                            </label>

                                            <input type="text"
                                                name="name"
                                                value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>"
                                                class="w-full px-4 py-3 rounded-xl border border-yellow-200 focus:border-yellow-500 focus:ring-2 focus:ring-yellow-200 focus:outline-none transition bg-white/80 shadow-sm"
                                                placeholder="Enter medicine brand name"
                                                required>

                                            <p class="text-xs text-gray-500 mt-2 flex items-center space-x-1">
                                                <i class="fas fa-lightbulb text-yellow-500"></i>
                                                <span>Brand/trade name of the medicine</span>
                                            </p>
                                        </div>

                                        <!-- Generic Name Dropdown -->
                                        <div class="relative">
                                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                                <span class="flex items-center space-x-2">
                                                    <i class="fas fa-dna text-blue-500 text-sm"></i>
                                                    <span>Generic Name *</span>
                                                </span>
                                            </label>

                                            <select name="generic_name"
                                                class="w-full appearance-none px-4 py-3 pr-10 rounded-xl border border-yellow-200 focus:border-yellow-500 focus:ring-2 focus:ring-yellow-200 focus:outline-none transition bg-white/80 shadow-sm"
                                                required>
                                                <option value="">Select Generic Name</option>
                                                <?php while ($gn = mysqli_fetch_assoc($generic_names_result)): ?>
                                                    <option value="<?php echo htmlspecialchars($gn['generic_name']); ?>"
                                                        <?php echo (isset($_POST['generic_name']) && $_POST['generic_name'] === $gn['generic_name']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($gn['generic_name']); ?>
                                                    </option>
                                                <?php endwhile; ?>
                                            </select>

                                            <!-- Dropdown Icon -->
                                            <i class="fas fa-chevron-down absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none"></i>

                                            <p class="text-xs text-gray-500 mt-2 flex items-center space-x-1">
                                                <i class="fas fa-lightbulb text-blue-500"></i>
                                                <span>Scientific/chemical name</span>
                                            </p>
                                        </div>

                                    </div>
                                </div>


                                <!-- Classification Section -->
                                <div>
                                    <h4 class="text-lg font-semibold text-gray-800 mb-4 flex items-center space-x-2">
                                        <i class="fas fa-tags text-purple-500"></i>
                                        <span>Classification</span>
                                    </h4>

                                    <div class="grid md:grid-cols-2 gap-6">

                                        <!-- Category -->
                                        <div class="relative">
                                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                                <span class="flex items-center space-x-2">
                                                    <i class="fas fa-tag text-teal-500 text-sm"></i>
                                                    <span>Category *</span>
                                                </span>
                                            </label>

                                            <select name="category_id"
                                                class="w-full appearance-none px-4 py-3 pr-10 rounded-xl border border-yellow-200 focus:border-yellow-500 focus:ring-2 focus:ring-yellow-200 focus:outline-none transition bg-white/80 shadow-sm"
                                                required>
                                                <option value="">Select Category</option>
                                                <?php
                                                mysqli_data_seek($categories, 0);
                                                while ($cat = mysqli_fetch_assoc($categories)): ?>
                                                    <option value="<?php echo $cat['id']; ?>"
                                                        <?php echo (isset($_POST['category_id']) && $_POST['category_id'] == $cat['id']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($cat['name']); ?>
                                                    </option>
                                                <?php endwhile; ?>
                                            </select>

                                            <!-- Dropdown Icon -->
                                            <i class="fas fa-chevron-down absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none"></i>
                                        </div>

                                        <!-- Type -->
                                        <div class="relative">
                                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                                <span class="flex items-center space-x-2">
                                                    <i class="fas fa-prescription-bottle-alt text-purple-500 text-sm"></i>
                                                    <span>Type *</span>
                                                </span>
                                            </label>

                                            <select name="type_id"
                                                class="w-full appearance-none px-4 py-3 pr-10 rounded-xl border border-yellow-200 focus:border-yellow-500 focus:ring-2 focus:ring-yellow-200 focus:outline-none transition bg-white/80 shadow-sm"
                                                required>
                                                <option value="">Select Type</option>
                                                <?php
                                                mysqli_data_seek($types, 0);
                                                while ($type = mysqli_fetch_assoc($types)): ?>
                                                    <option value="<?php echo $type['id']; ?>"
                                                        <?php echo (isset($_POST['type_id']) && $_POST['type_id'] == $type['id']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($type['name']); ?>
                                                    </option>
                                                <?php endwhile; ?>
                                            </select>

                                            <!-- Dropdown Icon -->
                                            <i class="fas fa-chevron-down absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none"></i>
                                        </div>

                                    </div>
                                </div>


                                <!-- Manufacturer & Strength Section -->
                                <div>
                                    <h4 class="text-lg font-semibold text-gray-800 mb-4 flex items-center space-x-2">
                                        <i class="fas fa-industry text-green-500"></i>
                                        <span>Manufacturing Details</span>
                                    </h4>
                                    <div class="grid md:grid-cols-2 gap-6">
                                        <!-- Manufacturer -->
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                                <span class="flex items-center space-x-2">
                                                    <i class="fas fa-industry text-gray-500 text-sm"></i>
                                                    <span>Manufacturer</span>
                                                </span>
                                            </label>
                                            <input type="text"
                                                name="manufacturer"
                                                value="<?php echo isset($_POST['manufacturer']) ? htmlspecialchars($_POST['manufacturer']) : ''; ?>"
                                                class="w-full px-4 py-3 rounded-xl border border-yellow-200 focus:border-yellow-500 focus:ring-2 focus:ring-yellow-200 focus:outline-none transition bg-white/80 shadow-sm"
                                                placeholder="Enter manufacturer name">
                                        </div>

                                        <!-- Strength -->
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                                <span class="flex items-center space-x-2">
                                                    <i class="fas fa-weight text-green-500 text-sm"></i>
                                                    <span>Strength</span>
                                                </span>
                                            </label>
                                            <div class="grid grid-cols-2 gap-3">
                                                <input type="text"
                                                    name="strength"
                                                    value="<?php echo isset($_POST['strength']) ? htmlspecialchars($_POST['strength']) : ''; ?>"
                                                    class="px-4 py-3 rounded-xl border border-yellow-200 focus:border-yellow-500 focus:ring-2 focus:ring-yellow-200 focus:outline-none transition bg-white/80 shadow-sm"
                                                    placeholder="e.g., 500">
                                                <input type="text"
                                                    name="unit"
                                                    value="<?php echo isset($_POST['unit']) ? htmlspecialchars($_POST['unit']) : ''; ?>"
                                                    class="px-4 py-3 rounded-xl border border-yellow-200 focus:border-yellow-500 focus:ring-2 focus:ring-yellow-200 focus:outline-none transition bg-white/80 shadow-sm"
                                                    placeholder="e.g., mg">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Description Section -->
                                <div>
                                    <h4 class="text-lg font-semibold text-gray-800 mb-4 flex items-center space-x-2">
                                        <i class="fas fa-file-alt text-yellow-500"></i>
                                        <span>Additional Information</span>
                                    </h4>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            <span class="flex items-center space-x-2">
                                                <i class="fas fa-align-left text-gray-500 text-sm"></i>
                                                <span>Description</span>
                                            </span>
                                        </label>
                                        <textarea name="description"
                                            rows="4"
                                            class="w-full px-4 py-3 rounded-xl border border-yellow-200 focus:border-yellow-500 focus:ring-2 focus:ring-yellow-200 focus:outline-none transition bg-white/80 shadow-sm"
                                            placeholder="Enter medicine description..."><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                                        <p class="text-xs text-gray-500 mt-2 flex items-center space-x-1">
                                            <i class="fas fa-info-circle text-yellow-500"></i>
                                            <span>Optional: Add usage, side effects, precautions</span>
                                        </p>
                                    </div>
                                </div>

                                <!-- Submit Button -->
                                <div class="pt-6 border-t border-yellow-100">
                                    <button type="submit"
                                        name="submit"
                                        class="w-full gradient-yellow text-white py-4 rounded-xl font-bold text-lg hover:shadow-xl transition-all duration-300 hover:scale-[1.02] group shadow relative overflow-hidden">
                                        <span class="relative z-10 flex items-center justify-center space-x-3">
                                            <i class="fas fa-plus group-hover:rotate-90 transition-transform duration-300"></i>
                                            <span>Add Medicine to Inventory</span>
                                            <i class="fas fa-arrow-right group-hover:translate-x-2 transition-transform duration-300 text-yellow-100"></i>
                                        </span>
                                        <div class="absolute inset-0 bg-gradient-to-r from-yellow-600 to-yellow-500 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                                    </button>
                                    <p class="text-center text-sm text-gray-500 mt-3">
                                        <i class="fas fa-exclamation-triangle text-yellow-500 mr-1"></i>
                                        Remember to add initial stock after registration
                                    </p>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Right Column - Info Panel -->
                <div class="space-y-6">
                    <!-- Guidelines -->
                    <div class="glass-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.2s">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center space-x-2">
                            <i class="fas fa-info-circle text-yellow-500"></i>
                            <span>Guidelines</span>
                        </h3>
                        <div class="space-y-4">
                            <div class="flex items-start space-x-3">
                                <div class="w-8 h-8 rounded-lg bg-yellow-100 flex items-center justify-center flex-shrink-0 shadow">
                                    <i class="fas fa-check text-yellow-600 text-sm"></i>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-700 mb-1">Accurate Information</p>
                                    <p class="text-xs text-gray-600">Provide correct generic names for proper identification</p>
                                </div>
                            </div>
                            <div class="flex items-start space-x-3">
                                <div class="w-8 h-8 rounded-lg bg-blue-100 flex items-center justify-center flex-shrink-0 shadow">
                                    <i class="fas fa-layer-group text-blue-600 text-sm"></i>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-700 mb-1">Proper Classification</p>
                                    <p class="text-xs text-gray-600">Select correct category and type</p>
                                </div>
                            </div>
                            <div class="flex items-start space-x-3">
                                <div class="w-8 h-8 rounded-lg bg-green-100 flex items-center justify-center flex-shrink-0 shadow">
                                    <i class="fas fa-notes-medical text-green-600 text-sm"></i>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-700 mb-1">Detailed Description</p>
                                    <p class="text-xs text-gray-600">Include usage instructions and precautions</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="glass-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.3s">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Quick Actions</h3>
                        <div class="space-y-3">
                            <a href="categories.php"
                                class="flex items-center justify-between p-3 bg-gradient-to-r from-teal-50 to-teal-100 border border-teal-200 rounded-xl hover:bg-teal-100 transition group shadow-sm">
                                <div class="flex items-center space-x-3">
                                    <div class="w-10 h-10 rounded-lg bg-teal-100 flex items-center justify-center">
                                        <i class="fas fa-tags text-teal-600"></i>
                                    </div>
                                    <div>
                                        <h4 class="font-medium text-gray-800 text-sm">Manage Categories</h4>
                                        <p class="text-xs text-gray-600">Add/edit medicine categories</p>
                                    </div>
                                </div>
                                <i class="fas fa-arrow-right text-teal-500 group-hover:translate-x-2 transition-transform"></i>
                            </a>
                            <a href="types.php"
                                class="flex items-center justify-between p-3 bg-gradient-to-r from-purple-50 to-purple-100 border border-purple-200 rounded-xl hover:bg-purple-100 transition group shadow-sm">
                                <div class="flex items-center space-x-3">
                                    <div class="w-10 h-10 rounded-lg bg-purple-100 flex items-center justify-center">
                                        <i class="fas fa-prescription-bottle text-purple-600"></i>
                                    </div>
                                    <div>
                                        <h4 class="font-medium text-gray-800 text-sm">Manage Types</h4>
                                        <p class="text-xs text-gray-600">Add/edit medicine types</p>
                                    </div>
                                </div>
                                <i class="fas fa-arrow-right text-purple-500 group-hover:translate-x-2 transition-transform"></i>
                            </a>
                            <a href="medicines.php"
                                class="flex items-center justify-between p-3 bg-gradient-to-r from-gray-50 to-gray-100 border border-gray-200 rounded-xl hover:bg-gray-100 transition group shadow-sm">
                                <div class="flex items-center space-x-3">
                                    <div class="w-10 h-10 rounded-lg bg-gray-100 flex items-center justify-center">
                                        <i class="fas fa-list text-gray-600"></i>
                                    </div>
                                    <div>
                                        <h4 class="font-medium text-gray-800 text-sm">View All Medicines</h4>
                                        <p class="text-xs text-gray-600">Browse complete inventory</p>
                                    </div>
                                </div>
                                <i class="fas fa-arrow-right text-gray-500 group-hover:translate-x-2 transition-transform"></i>
                            </a>
                        </div>
                    </div>

                    <!-- Stock Note -->
                    <div class="glass-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.4s; background: linear-gradient(to right, #dcfce7, #f0fdf4); border-left: 4px solid #22c55e;">
                        <div class="flex items-center space-x-4 mb-4">
                            <div class="w-12 h-12 rounded-xl bg-green-100 flex items-center justify-center shadow">
                                <i class="fas fa-boxes text-green-600 text-lg"></i>
                            </div>
                            <div>
                                <h4 class="font-bold text-gray-800">Add Stock</h4>
                                <p class="text-sm text-gray-600">Important next step</p>
                            </div>
                        </div>
                        <div class="space-y-3">
                            <p class="text-sm text-gray-700">After adding this medicine, you'll need to add initial stock including:</p>
                            <ul class="text-xs text-gray-600 space-y-2">
                                <li class="flex items-center space-x-2"><i class="fas fa-check-circle text-green-500 text-xs"></i> <span>Initial quantity</span></li>
                                <li class="flex items-center space-x-2"><i class="fas fa-check-circle text-green-500 text-xs"></i> <span>Batch number</span></li>
                                <li class="flex items-center space-x-2"><i class="fas fa-check-circle text-green-500 text-xs"></i> <span>Purchase price</span></li>
                                <li class="flex items-center space-x-2"><i class="fas fa-check-circle text-green-500 text-xs"></i> <span>Selling price & MRP</span></li>
                                <li class="flex items-center space-x-2"><i class="fas fa-check-circle text-green-500 text-xs"></i> <span>Expiry date</span></li>
                            </ul>
                        </div>
                    </div>

                    <!-- Form Status -->
                    <div class="glass-card rounded-2xl p-6 animate-fade-in-up" style="animation-delay: 0.5s">
                        <h4 class="text-sm font-semibold text-gray-800 mb-3">Form Status</h4>
                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-600">Required Fields</span>
                                <span class="text-xs font-medium text-green-600" id="requiredCount">0/4</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-green-500 h-2 rounded-full" id="progressBar" style="width: 0%"></div>
                            </div>
                            <p class="text-xs text-gray-500 mt-2">
                                <i class="fas fa-clock text-yellow-500 mr-1"></i>
                                Form auto-saves progress every 30 seconds
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Footer -->
    <?php include "../includes/footer.php"; ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('medicineForm');
            const requiredFields = form.querySelectorAll('[required]');
            const progressBar = document.getElementById('progressBar');
            const requiredCount = document.getElementById('requiredCount');

            function updateProgress() {
                let filled = 0;
                requiredFields.forEach(field => {
                    if (field.value.trim() !== '') filled++;
                });
                const total = requiredFields.length;
                const progress = (filled / total) * 100;
                progressBar.style.width = `${progress}%`;
                requiredCount.textContent = `${filled}/${total}`;

                // Update color
                progressBar.className = 'h-2 rounded-full ' +
                    (progress === 100 ? 'bg-green-500' : progress >= 50 ? 'bg-yellow-500' : 'bg-red-500');
            }

            // Listen to both input and change (for dropdowns)
            form.addEventListener('input', updateProgress);
            form.addEventListener('change', updateProgress);

            // Initial check
            updateProgress();

            // Auto-save
            let autoSaveTimer;

            function saveFormData() {
                const formData = new FormData(form);
                const data = Object.fromEntries(formData.entries());
                localStorage.setItem('medicineFormDraft', JSON.stringify(data));
                console.log('Form auto-saved');
            }

            form.addEventListener('input', () => {
                clearTimeout(autoSaveTimer);
                autoSaveTimer = setTimeout(saveFormData, 30000);
            });

            // Load saved draft
            const saved = localStorage.getItem('medicineFormDraft');
            if (saved) {
                const data = JSON.parse(saved);
                Object.keys(data).forEach(name => {
                    const field = form.querySelector(`[name="${name}"]`);
                    if (field) field.value = data[name];
                });
                updateProgress();
            }

            // On submit: clear draft
            form.addEventListener('submit', () => {
                localStorage.removeItem('medicineFormDraft');
            });

            // Reset form
            window.resetForm = function() {
                if (confirm('Are you sure you want to reset the form? All entered data will be lost.')) {
                    form.reset();
                    localStorage.removeItem('medicineFormDraft');
                    updateProgress();
                }
            };
        });
    </script>
</body>

</html>