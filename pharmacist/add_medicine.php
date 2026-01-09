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

// Handle modal form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check which form was submitted
    if (isset($_POST['add_category'])) {
        $category_name = trim($_POST['category_name'] ?? '');
        $category_desc = trim($_POST['category_description'] ?? '');

        if (!empty($category_name)) {
            $stmt = $conn->prepare("INSERT INTO medicine_categories (name, description) VALUES (?, ?)");
            $stmt->bind_param('ss', $category_name, $category_desc);
            if ($stmt->execute()) {
                $_SESSION['modal_success'] = "Category added successfully!";
            } else {
                $_SESSION['modal_error'] = "Failed to add category: " . $stmt->error;
            }
            $stmt->close();
        }
    }

    if (isset($_POST['add_type'])) {
        $type_name = trim($_POST['type_name'] ?? '');
        $type_desc = trim($_POST['type_description'] ?? '');

        if (!empty($type_name)) {
            $stmt = $conn->prepare("INSERT INTO medicine_types (name, description) VALUES (?, ?)");
            $stmt->bind_param('ss', $type_name, $type_desc);
            if ($stmt->execute()) {
                $_SESSION['modal_success'] = "Type added successfully!";
            } else {
                $_SESSION['modal_error'] = "Failed to add type: " . $stmt->error;
            }
            $stmt->close();
        }
    }

    if (isset($_POST['add_generic'])) {
        $generic_name = trim($_POST['generic_name'] ?? '');

        if (!empty($generic_name)) {
            $stmt = $conn->prepare("INSERT INTO medicine_generics (name, created_at) VALUES (?, NOW())");
            $stmt->bind_param('s', $generic_name);
            if ($stmt->execute()) {
                $_SESSION['modal_success'] = "Generic added successfully!";
            } else {
                $_SESSION['modal_error'] = "Failed to add generic: " . $stmt->error;
            }
            $stmt->close();
        }
    }

    // Handle main medicine form submission
    if (isset($_POST['submit'])) {
        $name = trim($_POST['name'] ?? '');
        $generic_id = intval($_POST['generic_id'] ?? 0);
        $category_id = intval($_POST['category_id'] ?? 0);
        $type_id = intval($_POST['type_id'] ?? 0);
        $description = trim($_POST['description'] ?? '');

        // Validate required fields
        if (empty($name) || empty($generic_id) || empty($category_id) || empty($type_id)) {
            $error = "Please fill in all required fields.";
        } else {
            $stmt = $conn->prepare("
                INSERT INTO medicines (name, generic_id, category_id, type_id, description, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");

            if ($stmt) {
                $stmt->bind_param('siiis', $name, $generic_id, $category_id, $type_id, $description);

                if ($stmt->execute()) {
                    $success = true;
                    $new_medicine_id = $stmt->insert_id;
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
}

// Handle success via redirect (PRG pattern)
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $success = true;
    $new_medicine_id = intval($_GET['medicine_id'] ?? 0);
}

// Fetch categories, types, and generics
$categories = mysqli_query($conn, "SELECT * FROM medicine_categories ORDER BY name");
$types = mysqli_query($conn, "SELECT * FROM medicine_types ORDER BY name");
$generics = mysqli_query($conn, "SELECT id, name FROM medicine_generics ORDER BY name");

// Store data for JavaScript
$category_data = [];
$type_data = [];
$generic_data = [];

while ($cat = mysqli_fetch_assoc($categories)) {
    $category_data[] = $cat;
}
mysqli_data_seek($categories, 0);

while ($type = mysqli_fetch_assoc($types)) {
    $type_data[] = $type;
}
mysqli_data_seek($types, 0);

while ($gen = mysqli_fetch_assoc($generics)) {
    $generic_data[] = $gen;
}
mysqli_data_seek($generics, 0);
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

        .modal-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            z-index: 1000;
        }

        .modal {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            border-radius: 1rem;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            display: none;
            z-index: 1001;
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal.active,
        .modal-backdrop.active {
            display: block;
        }

        .dropdown-search-container {
            position: relative;
        }

        .dropdown-search-input {
            width: 100%;
            padding: 0.5rem 2.5rem 0.5rem 0.75rem;
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            font-size: 0.875rem;
        }

        .dropdown-search-icon {
            position: absolute;
            right: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
        }

        .dropdown-options {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            max-height: 200px;
            overflow-y: auto;
            z-index: 100;
            display: none;
        }

        .dropdown-options.active {
            display: block;
        }

        .dropdown-option {
            padding: 0.5rem 0.75rem;
            cursor: pointer;
            transition: background 0.2s;
        }

        .dropdown-option:hover {
            background: #f3f4f6;
        }

        .dropdown-option.selected {
            background: #fef3c7;
        }
    </style>
</head>

<body class="min-h-screen overflow-x-hidden">
    <!-- Background Blobs -->
    <div class="yellow-blob top-20 right-10"></div>
    <div class="gray-blob bottom-20 left-10"></div>

    <!-- Modals -->
    <!-- Manage Categories Modal -->
    <div class="modal-backdrop" id="categoriesModalBackdrop"></div>
    <div class="modal" id="categoriesModal">
        <div class="p-6">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-bold text-gray-800">Manage Categories</h3>
                <button onclick="closeModal('categoriesModal')" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <div class="mb-6">
                <h4 class="font-medium text-gray-700 mb-3">Add New Category</h4>
                <form method="POST" id="addCategoryForm">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Category Name *</label>
                            <input type="text" name="category_name" required
                                class="w-full px-4 py-2 rounded-lg border border-yellow-200 focus:border-yellow-500 focus:ring-2 focus:ring-yellow-200 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                            <textarea name="category_description" rows="3"
                                class="w-full px-4 py-2 rounded-lg border border-yellow-200 focus:border-yellow-500 focus:ring-2 focus:ring-yellow-200 focus:outline-none"></textarea>
                        </div>
                        <div class="flex justify-end space-x-3">
                            <button type="button" onclick="closeModal('categoriesModal')"
                                class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                                Cancel
                            </button>
                            <button type="submit" name="add_category"
                                class="px-4 py-2 bg-yellow-500 text-white rounded-lg hover:bg-yellow-600">
                                Add Category
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <div>
                <h4 class="font-medium text-gray-700 mb-3">Existing Categories</h4>
                <div class="space-y-2 max-h-60 overflow-y-auto">
                    <?php foreach ($category_data as $cat): ?>
                        <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                            <div>
                                <span class="font-medium text-gray-800"><?php echo htmlspecialchars($cat['name']); ?></span>
                                <?php if (!empty($cat['description'])): ?>
                                    <p class="text-xs text-gray-600 mt-1"><?php echo htmlspecialchars($cat['description']); ?></p>
                                <?php endif; ?>
                            </div>
                            <span class="text-xs bg-blue-100 text-blue-800 px-2 py-1 rounded">ID: <?php echo $cat['id']; ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Manage Types Modal -->
    <div class="modal-backdrop" id="typesModalBackdrop"></div>
    <div class="modal" id="typesModal">
        <div class="p-6">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-bold text-gray-800">Manage Types</h3>
                <button onclick="closeModal('typesModal')" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <div class="mb-6">
                <h4 class="font-medium text-gray-700 mb-3">Add New Type</h4>
                <form method="POST" id="addTypeForm">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Type Name *</label>
                            <input type="text" name="type_name" required
                                class="w-full px-4 py-2 rounded-lg border border-yellow-200 focus:border-yellow-500 focus:ring-2 focus:ring-yellow-200 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                            <textarea name="type_description" rows="3"
                                class="w-full px-4 py-2 rounded-lg border border-yellow-200 focus:border-yellow-500 focus:ring-2 focus:ring-yellow-200 focus:outline-none"></textarea>
                        </div>
                        <div class="flex justify-end space-x-3">
                            <button type="button" onclick="closeModal('typesModal')"
                                class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                                Cancel
                            </button>
                            <button type="submit" name="add_type"
                                class="px-4 py-2 bg-purple-500 text-white rounded-lg hover:bg-purple-600">
                                Add Type
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <div>
                <h4 class="font-medium text-gray-700 mb-3">Existing Types</h4>
                <div class="space-y-2 max-h-60 overflow-y-auto">
                    <?php foreach ($type_data as $type): ?>
                        <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                            <div>
                                <span class="font-medium text-gray-800"><?php echo htmlspecialchars($type['name']); ?></span>
                                <?php if (!empty($type['description'])): ?>
                                    <p class="text-xs text-gray-600 mt-1"><?php echo htmlspecialchars($type['description']); ?></p>
                                <?php endif; ?>
                            </div>
                            <span class="text-xs bg-purple-100 text-purple-800 px-2 py-1 rounded">ID: <?php echo $type['id']; ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Manage Generics Modal -->
    <div class="modal-backdrop" id="genericsModalBackdrop"></div>
    <div class="modal" id="genericsModal">
        <div class="p-6">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-bold text-gray-800">Manage Generics</h3>
                <button onclick="closeModal('genericsModal')" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <div class="mb-6">
                <h4 class="font-medium text-gray-700 mb-3">Add New Generic</h4>
                <form method="POST" id="addGenericForm">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Generic Name *</label>
                            <input type="text" name="generic_name" required
                                class="w-full px-4 py-2 rounded-lg border border-yellow-200 focus:border-yellow-500 focus:ring-2 focus:ring-yellow-200 focus:outline-none"
                                placeholder="Enter generic name">
                        </div>
                        <div class="flex justify-end space-x-3">
                            <button type="button" onclick="closeModal('genericsModal')"
                                class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                                Cancel
                            </button>
                            <button type="submit" name="add_generic"
                                class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600">
                                Add Generic
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <div>
                <h4 class="font-medium text-gray-700 mb-3">Existing Generics</h4>
                <div class="space-y-2 max-h-60 overflow-y-auto">
                    <?php foreach ($generic_data as $gen): ?>
                        <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                            <div>
                                <span class="font-medium text-gray-800"><?php echo htmlspecialchars($gen['name']); ?></span>
                            </div>
                            <span class="text-xs bg-green-100 text-green-800 px-2 py-1 rounded">ID: <?php echo $gen['id']; ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

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

            <!-- Modal Success/Error Messages -->
            <?php if (isset($_SESSION['modal_success'])): ?>
                <div class="glass-card rounded-2xl p-4 mb-6 animate-fade-in-up bg-gradient-to-r from-green-50 to-green-100 border-l-4 border-green-500">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle text-green-600 mr-3"></i>
                        <span><?php echo htmlspecialchars($_SESSION['modal_success']); ?></span>
                    </div>
                </div>
                <?php unset($_SESSION['modal_success']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['modal_error'])): ?>
                <div class="glass-card rounded-2xl p-4 mb-6 animate-fade-in-up bg-gradient-to-r from-red-50 to-red-100 border-l-4 border-red-500">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-triangle text-red-600 mr-3"></i>
                        <span><?php echo htmlspecialchars($_SESSION['modal_error']); ?></span>
                    </div>
                </div>
                <?php unset($_SESSION['modal_error']); ?>
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

                                        <!-- Generic Name Dropdown with Search -->
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                                <span class="flex items-center space-x-2">
                                                    <i class="fas fa-dna text-blue-500 text-sm"></i>
                                                    <span>Generic Name *</span>
                                                </span>
                                            </label>
                                            <div class="dropdown-search-container">
                                                <input type="hidden" name="generic_id" id="generic_id" value="<?php echo isset($_POST['generic_id']) ? htmlspecialchars($_POST['generic_id']) : ''; ?>" required>
                                                <input type="text"
                                                    id="generic_search"
                                                    class="dropdown-search-input px-4 py-3 rounded-xl border border-yellow-200 focus:border-yellow-500 focus:ring-2 focus:ring-yellow-200 focus:outline-none transition bg-white/80 shadow-sm w-full"
                                                    placeholder="Search or select generic name"
                                                    autocomplete="off">
                                                <div class="dropdown-search-icon">
                                                    <i class="fas fa-search"></i>
                                                </div>
                                                <div class="dropdown-options" id="generic_options"></div>
                                            </div>
                                            <div class="mt-2 flex justify-between items-center">
                                                <p class="text-xs text-gray-500 flex items-center space-x-1">
                                                    <i class="fas fa-lightbulb text-blue-500"></i>
                                                    <span>Scientific/chemical name</span>
                                                </p>
                                                <button type="button" onclick="openModal('genericsModal')"
                                                    class="text-xs text-blue-600 hover:text-blue-800 flex items-center space-x-1">
                                                    <i class="fas fa-plus"></i>
                                                    <span>Add New</span>
                                                </button>
                                            </div>
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
                                        <!-- Category Dropdown with Search -->
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                                <span class="flex items-center space-x-2">
                                                    <i class="fas fa-tag text-teal-500 text-sm"></i>
                                                    <span>Category *</span>
                                                </span>
                                            </label>
                                            <div class="dropdown-search-container">
                                                <input type="hidden" name="category_id" id="category_id" value="<?php echo isset($_POST['category_id']) ? htmlspecialchars($_POST['category_id']) : ''; ?>" required>
                                                <input type="text"
                                                    id="category_search"
                                                    class="dropdown-search-input px-4 py-3 rounded-xl border border-yellow-200 focus:border-yellow-500 focus:ring-2 focus:ring-yellow-200 focus:outline-none transition bg-white/80 shadow-sm w-full"
                                                    placeholder="Search or select category"
                                                    autocomplete="off">
                                                <div class="dropdown-search-icon">
                                                    <i class="fas fa-search"></i>
                                                </div>
                                                <div class="dropdown-options" id="category_options"></div>
                                            </div>
                                            <div class="mt-2 flex justify-between items-center">
                                                <p class="text-xs text-gray-500 flex items-center space-x-1">
                                                    <i class="fas fa-lightbulb text-teal-500"></i>
                                                    <span>Medicine category</span>
                                                </p>
                                                <button type="button" onclick="openModal('categoriesModal')"
                                                    class="text-xs text-teal-600 hover:text-teal-800 flex items-center space-x-1">
                                                    <i class="fas fa-plus"></i>
                                                    <span>Add New</span>
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Type Dropdown with Search -->
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                                <span class="flex items-center space-x-2">
                                                    <i class="fas fa-prescription-bottle-alt text-purple-500 text-sm"></i>
                                                    <span>Type *</span>
                                                </span>
                                            </label>
                                            <div class="dropdown-search-container">
                                                <input type="hidden" name="type_id" id="type_id" value="<?php echo isset($_POST['type_id']) ? htmlspecialchars($_POST['type_id']) : ''; ?>" required>
                                                <input type="text"
                                                    id="type_search"
                                                    class="dropdown-search-input px-4 py-3 rounded-xl border border-yellow-200 focus:border-yellow-500 focus:ring-2 focus:ring-yellow-200 focus:outline-none transition bg-white/80 shadow-sm w-full"
                                                    placeholder="Search or select type"
                                                    autocomplete="off">
                                                <div class="dropdown-search-icon">
                                                    <i class="fas fa-search"></i>
                                                </div>
                                                <div class="dropdown-options" id="type_options"></div>
                                            </div>
                                            <div class="mt-2 flex justify-between items-center">
                                                <p class="text-xs text-gray-500 flex items-center space-x-1">
                                                    <i class="fas fa-lightbulb text-purple-500"></i>
                                                    <span>Medicine form/type</span>
                                                </p>
                                                <button type="button" onclick="openModal('typesModal')"
                                                    class="text-xs text-purple-600 hover:text-purple-800 flex items-center space-x-1">
                                                    <i class="fas fa-plus"></i>
                                                    <span>Add New</span>
                                                </button>
                                            </div>
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
                            <button onclick="openModal('categoriesModal')"
                                class="w-full flex items-center justify-between p-3 bg-gradient-to-r from-teal-50 to-teal-100 border border-teal-200 rounded-xl hover:bg-teal-100 transition group shadow-sm cursor-pointer">
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
                            </button>
                            <button onclick="openModal('typesModal')"
                                class="w-full flex items-center justify-between p-3 bg-gradient-to-r from-purple-50 to-purple-100 border border-purple-200 rounded-xl hover:bg-purple-100 transition group shadow-sm cursor-pointer">
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
                            </button>
                            <button onclick="openModal('genericsModal')"
                                class="w-full flex items-center justify-between p-3 bg-gradient-to-r from-blue-50 to-blue-100 border border-blue-200 rounded-xl hover:bg-blue-100 transition group shadow-sm cursor-pointer">
                                <div class="flex items-center space-x-3">
                                    <div class="w-10 h-10 rounded-lg bg-blue-100 flex items-center justify-center">
                                        <i class="fas fa-dna text-blue-600"></i>
                                    </div>
                                    <div>
                                        <h4 class="font-medium text-gray-800 text-sm">Manage Generics</h4>
                                        <p class="text-xs text-gray-600">Add/edit generic names</p>
                                    </div>
                                </div>
                                <i class="fas fa-arrow-right text-blue-500 group-hover:translate-x-2 transition-transform"></i>
                            </button>
                            <a href="medicines.php"
                                class="w-full flex items-center justify-between p-3 bg-gradient-to-r from-gray-50 to-gray-100 border border-gray-200 rounded-xl hover:bg-gray-100 transition group shadow-sm">
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
        // Data from PHP
        const categories = <?php echo json_encode($category_data); ?>;
        const types = <?php echo json_encode($type_data); ?>;
        const generics = <?php echo json_encode($generic_data); ?>;

        // Modal functions
        function openModal(modalId) {
            document.getElementById(modalId).classList.add('active');
            document.getElementById(modalId + 'Backdrop').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
            document.getElementById(modalId + 'Backdrop').classList.remove('active');
            document.body.style.overflow = 'auto';
        }

        // Close modal when clicking backdrop
        document.querySelectorAll('.modal-backdrop').forEach(backdrop => {
            backdrop.addEventListener('click', function() {
                const modalId = this.id.replace('Backdrop', '');
                closeModal(modalId);
            });
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal').forEach(modal => {
                    if (modal.classList.contains('active')) {
                        const modalId = modal.id;
                        closeModal(modalId);
                    }
                });
            }
        });

        // Dropdown search functionality
        function initializeDropdownSearch(searchInputId, optionsId, hiddenInputId, data, displayField = 'name') {
            const searchInput = document.getElementById(searchInputId);
            const optionsDiv = document.getElementById(optionsId);
            const hiddenInput = document.getElementById(hiddenInputId);
            let selectedOption = null;

            // Populate options on focus
            searchInput.addEventListener('focus', function() {
                populateOptions('');
                optionsDiv.classList.add('active');
            });

            // Filter on input
            searchInput.addEventListener('input', function() {
                populateOptions(this.value);
            });

            // Select option
            function selectOption(item) {
                selectedOption = item;
                searchInput.value = item[displayField];
                hiddenInput.value = item.id;
                optionsDiv.classList.remove('active');
                updateProgress(); // Update form progress

                // Highlight selected option
                optionsDiv.querySelectorAll('.dropdown-option').forEach(opt => {
                    opt.classList.remove('selected');
                    if (parseInt(opt.dataset.id) === item.id) {
                        opt.classList.add('selected');
                    }
                });
            }

            // Populate options based on search
            function populateOptions(searchTerm) {
                optionsDiv.innerHTML = '';
                const filtered = data.filter(item =>
                    item[displayField].toLowerCase().includes(searchTerm.toLowerCase())
                );

                if (filtered.length === 0) {
                    optionsDiv.innerHTML = `
                        <div class="dropdown-option p-3 text-gray-500 text-center">
                            No results found
                        </div>`;
                    return;
                }

                filtered.forEach(item => {
                    const div = document.createElement('div');
                    div.className = 'dropdown-option';
                    div.dataset.id = item.id;
                    div.textContent = item[displayField];
                    if (item.description) {
                        const desc = document.createElement('div');
                        desc.className = 'text-xs text-gray-500 mt-1';
                        desc.textContent = item.description;
                        div.appendChild(desc);
                    }
                    div.addEventListener('click', () => selectOption(item));

                    // Mark as selected if already chosen
                    if (parseInt(hiddenInput.value) === item.id) {
                        div.classList.add('selected');
                        searchInput.value = item[displayField];
                    }

                    optionsDiv.appendChild(div);
                });
            }

            // Close dropdown when clicking outside
            document.addEventListener('click', function(event) {
                if (!searchInput.contains(event.target) && !optionsDiv.contains(event.target)) {
                    optionsDiv.classList.remove('active');
                    // If we have a selected option, show its name
                    if (selectedOption) {
                        searchInput.value = selectedOption[displayField];
                    }
                }
            });

            // Load initial value if exists
            const initialValue = hiddenInput.value;
            if (initialValue) {
                const item = data.find(d => d.id == initialValue);
                if (item) {
                    selectOption(item);
                }
            }
        }

        // Initialize dropdowns when DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize dropdown searches
            initializeDropdownSearch('category_search', 'category_options', 'category_id', categories);
            initializeDropdownSearch('type_search', 'type_options', 'type_id', types);
            initializeDropdownSearch('generic_search', 'generic_options', 'generic_id', generics);

            const form = document.getElementById('medicineForm');
            const requiredFields = [
                document.querySelector('[name="name"]'),
                document.getElementById('generic_id'),
                document.getElementById('category_id'),
                document.getElementById('type_id')
            ];

            const progressBar = document.getElementById('progressBar');
            const requiredCount = document.getElementById('requiredCount');

            function updateProgress() {
                let filled = 0;
                requiredFields.forEach(field => {
                    if (field && field.value.trim() !== '') filled++;
                });
                const total = requiredFields.length;
                const progress = (filled / total) * 100;
                progressBar.style.width = `${progress}%`;
                requiredCount.textContent = `${filled}/${total}`;

                // Update color
                progressBar.className = 'h-2 rounded-full ' +
                    (progress === 100 ? 'bg-green-500' : progress >= 50 ? 'bg-yellow-500' : 'bg-red-500');
            }

            // Listen to input changes
            form.addEventListener('input', updateProgress);

            // Also update when hidden inputs change (for dropdown selections)
            document.getElementById('generic_id').addEventListener('change', updateProgress);
            document.getElementById('category_id').addEventListener('change', updateProgress);
            document.getElementById('type_id').addEventListener('change', updateProgress);

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

                // Also set hidden fields and search inputs
                if (data.generic_id) {
                    const gen = generics.find(g => g.id == data.generic_id);
                    if (gen) {
                        document.getElementById('generic_search').value = gen.name;
                        document.getElementById('generic_id').value = gen.id;
                    }
                }
                if (data.category_id) {
                    const cat = categories.find(c => c.id == data.category_id);
                    if (cat) {
                        document.getElementById('category_search').value = cat.name;
                        document.getElementById('category_id').value = cat.id;
                    }
                }
                if (data.type_id) {
                    const typ = types.find(t => t.id == data.type_id);
                    if (typ) {
                        document.getElementById('type_search').value = typ.name;
                        document.getElementById('type_id').value = typ.id;
                    }
                }

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
                    // Clear search inputs and hidden fields
                    document.getElementById('generic_search').value = '';
                    document.getElementById('generic_id').value = '';
                    document.getElementById('category_search').value = '';
                    document.getElementById('category_id').value = '';
                    document.getElementById('type_search').value = '';
                    document.getElementById('type_id').value = '';

                    localStorage.removeItem('medicineFormDraft');
                    updateProgress();

                    // Show success message
                    const toast = document.createElement('div');
                    toast.className = 'fixed top-4 right-4 bg-green-500 text-white px-4 py-2 rounded-lg shadow-lg z-50';
                    toast.textContent = 'Form reset successfully!';
                    document.body.appendChild(toast);
                    setTimeout(() => toast.remove(), 3000);
                }
            };
        });
    </script>
</body>

</html>