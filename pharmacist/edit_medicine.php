<?php
require_once "../config/db.php";
require_once "../includes/auth_check.php";

// Only pharmacists can edit medicines
if ($_SESSION['role'] !== 'pharmacist') {
    header("Location: medicines.php");
    exit;
}

// Get medicine ID
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    header("Location: medicines.php");
    exit;
}

// Fetch medicine details
$medicine_query = mysqli_query(
    $conn,
    "SELECT m.*, c.name AS category_name, t.name AS type_name, g.name AS generic_name
     FROM medicines m
     LEFT JOIN medicine_categories c ON m.category_id = c.id
     LEFT JOIN medicine_types t ON m.type_id = t.id
     LEFT JOIN medicine_generics g ON m.generic_id = g.id
     WHERE m.id = $id"
);

if (!$medicine_query || mysqli_num_rows($medicine_query) == 0) {
    header("Location: medicines.php");
    exit;
}

$medicine = mysqli_fetch_assoc($medicine_query);

// Fetch categories, types, generics for dropdowns
$categories = mysqli_query($conn, "SELECT * FROM medicine_categories ORDER BY name");
$types = mysqli_query($conn, "SELECT * FROM medicine_types ORDER BY name");
$generics = mysqli_query($conn, "SELECT * FROM medicine_generics ORDER BY name");

// Handle form submission
$success_message = '';
$error_message = '';
$form_data = [
    'name' => $medicine['name'],
    'category_id' => $medicine['category_id'],
    'type_id' => $medicine['type_id'],
    'generic_id' => $medicine['generic_id'],
    'description' => $medicine['description']
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get and validate data
    $name = isset($_POST['name']) ? mysqli_real_escape_string($conn, trim($_POST['name'])) : '';
    $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;
    $type_id = isset($_POST['type_id']) ? intval($_POST['type_id']) : 0;
    $generic_id = isset($_POST['generic_id']) ? intval($_POST['generic_id']) : 0;
    $description = isset($_POST['description']) ? mysqli_real_escape_string($conn, trim($_POST['description'])) : '';

    // Validate required fields
    $errors = [];
    
    if (empty($name)) {
        $errors[] = 'Medicine name is required';
    }
    
    if ($category_id <= 0) {
        $errors[] = 'Category is required';
    }
    
    if ($type_id <= 0) {
        $errors[] = 'Type is required';
    }
    
    if (empty($errors)) {
        // Prepare generic_id value for SQL (NULL if 0)
        $generic_sql = ($generic_id > 0) ? $generic_id : "NULL";
        
        // Update medicine
        $update_query = "UPDATE medicines SET 
            name = '$name',
            category_id = $category_id,
            type_id = $type_id,
            generic_id = $generic_sql,
            description = '$description',
            updated_at = NOW()
            WHERE id = $id";
        
        if (mysqli_query($conn, $update_query)) {
            if (mysqli_affected_rows($conn) > 0) {
                $success_message = 'Medicine updated successfully!';
                // Refresh medicine data
                $medicine_query = mysqli_query(
                    $conn,
                    "SELECT m.*, c.name AS category_name, t.name AS type_name, g.name AS generic_name
                     FROM medicines m
                     LEFT JOIN medicine_categories c ON m.category_id = c.id
                     LEFT JOIN medicine_types t ON m.type_id = t.id
                     LEFT JOIN medicine_generics g ON m.generic_id = g.id
                     WHERE m.id = $id"
                );
                $medicine = mysqli_fetch_assoc($medicine_query);
                
                // Update form data
                $form_data = [
                    'name' => $name,
                    'category_id' => $category_id,
                    'type_id' => $type_id,
                    'generic_id' => $generic_id,
                    'description' => $description
                ];
            } else {
                $error_message = 'No changes were made';
            }
        } else {
            $error_message = 'Database error: ' . mysqli_error($conn);
        }
    } else {
        $error_message = implode('<br>', $errors);
        // Keep submitted form data
        $form_data = [
            'name' => $name,
            'category_id' => $category_id,
            'type_id' => $type_id,
            'generic_id' => $generic_id,
            'description' => $description
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Medicine - MediCare Pharma</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: #f8fafc;
            min-height: 100vh;
        }

        .glass-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
        }

        .gradient-primary {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
        }

        .gradient-danger {
            background: linear-gradient(135deg, #ef4444, #dc2626);
        }

        .gradient-success {
            background: linear-gradient(135deg, #10b981, #059669);
        }

        .form-input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            transition: all 0.2s;
        }

        .form-input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            color: #374151;
        }

        .select-input {
            width: 100%;
            padding: 0.75rem 2.5rem 0.75rem 1rem;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            appearance: none;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 0.5rem center;
            background-repeat: no-repeat;
            background-size: 1.5em 1.5em;
            transition: all 0.2s;
        }

        .select-input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .required::after {
            content: ' *';
            color: #ef4444;
        }

        .alert-success {
            background: linear-gradient(135deg, #d1fae5, #a7f3d0);
            border: 1px solid #10b981;
            color: #065f46;
        }

        .alert-error {
            background: linear-gradient(135deg, #fee2e2, #fecaca);
            border: 1px solid #ef4444;
            color: #7f1d1d;
        }

        .medicine-header {
            background: linear-gradient(135deg, #3b82f6, #1e40af);
            color: white;
            border-radius: 12px 12px 0 0;
        }

        .back-btn {
            background: linear-gradient(135deg, #6b7280, #4b5563);
            color: white;
            transition: all 0.2s;
        }

        .back-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .info-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 1rem;
        }

        .info-label {
            font-size: 0.75rem;
            color: #6b7280;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .info-value {
            font-size: 0.875rem;
            color: #1f2937;
            font-weight: 500;
        }
    </style>
</head>

<body class="min-h-screen font-sans">

    <!-- Navbar -->
    <?php include "../includes/navbar.php"; ?>

    <div class="flex">
        <!-- Sidebar -->
        <?php include "includes/pharmacist_sidebar.php"; ?>

        <!-- Main Content -->
        <main class="flex-1 overflow-hidden p-4 lg:p-6">
            <!-- Success/Error Messages -->
            <?php if ($success_message): ?>
                <div class="alert-success p-4 rounded-lg mb-6 flex items-center gap-3">
                    <i class="fas fa-check-circle text-lg"></i>
                    <span class="font-medium"><?php echo $success_message; ?></span>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="alert-error p-4 rounded-lg mb-6 flex items-center gap-3">
                    <i class="fas fa-exclamation-circle text-lg"></i>
                    <span class="font-medium"><?php echo $error_message; ?></span>
                </div>
            <?php endif; ?>

            <!-- Page Header -->
            <div class="glass-card mb-6 overflow-hidden">
                <div class="medicine-header p-6">
                    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                        <div>
                            <div class="flex items-center gap-3 mb-2">
                                <a href="medicines.php" class="back-btn px-4 py-2 rounded-lg font-medium flex items-center gap-2">
                                    <i class="fas fa-arrow-left"></i>
                                    <span>Back to Medicines</span>
                                </a>
                            </div>
                            <h1 class="text-2xl lg:text-3xl font-bold">
                                <i class="fas fa-edit mr-2"></i>
                                Edit Medicine
                            </h1>
                            <p class="text-blue-100 text-sm lg:text-base mt-1">
                                Update medicine information and details
                            </p>
                        </div>
                        <div class="flex items-center gap-4">
                            <div class="text-right">
                                <div class="text-sm text-blue-200">Medicine ID</div>
                                <div class="text-lg font-bold">MED-<?php echo str_pad($medicine['id'], 6, '0', STR_PAD_LEFT); ?></div>
                            </div>
                            <div class="w-12 h-12 rounded-full bg-white/20 flex items-center justify-center">
                                <i class="fas fa-pills text-xl"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Medicine Info Summary -->
                <div class="p-6 border-b border-gray-200">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="info-card">
                            <div class="info-label">Current Name</div>
                            <div class="info-value"><?php echo htmlspecialchars($medicine['name']); ?></div>
                        </div>
                        <div class="info-card">
                            <div class="info-label">Category</div>
                            <div class="info-value"><?php echo htmlspecialchars($medicine['category_name'] ?? 'Not specified'); ?></div>
                        </div>
                        <div class="info-card">
                            <div class="info-label">Type</div>
                            <div class="info-value"><?php echo htmlspecialchars($medicine['type_name'] ?? 'Not specified'); ?></div>
                        </div>
                    </div>
                </div>

                <!-- Edit Form -->
                <div class="p-6">
                    <form method="POST" class="space-y-8">
                        <!-- Medicine Name -->
                        <div>
                            <label for="name" class="form-label required">Medicine Name</label>
                            <input type="text"
                                id="name"
                                name="name"
                                value="<?php echo htmlspecialchars($form_data['name']); ?>"
                                class="form-input"
                                required
                                placeholder="Enter medicine name">
                            <p class="text-gray-500 text-xs mt-2">
                                Enter the brand or trade name of the medicine
                            </p>
                        </div>

                        <!-- Category and Type -->
                        <div class="grid md:grid-cols-2 gap-6">
                            <div>
                                <label for="category_id" class="form-label required">Category</label>
                                <select id="category_id"
                                    name="category_id"
                                    class="select-input"
                                    required>
                                    <option value="">Select Category</option>
                                    <?php 
                                    mysqli_data_seek($categories, 0);
                                    while ($cat = mysqli_fetch_assoc($categories)): ?>
                                        <option value="<?php echo $cat['id']; ?>"
                                            <?php echo $form_data['category_id'] == $cat['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cat['name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                                <p class="text-gray-500 text-xs mt-2">
                                    Select the therapeutic category
                                </p>
                            </div>

                            <div>
                                <label for="type_id" class="form-label required">Type</label>
                                <select id="type_id"
                                    name="type_id"
                                    class="select-input"
                                    required>
                                    <option value="">Select Type</option>
                                    <?php 
                                    mysqli_data_seek($types, 0);
                                    while ($type = mysqli_fetch_assoc($types)): ?>
                                        <option value="<?php echo $type['id']; ?>"
                                            <?php echo $form_data['type_id'] == $type['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($type['name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                                <p class="text-gray-500 text-xs mt-2">
                                    Select the dosage form (tablet, syrup, etc.)
                                </p>
                            </div>
                        </div>

                        <!-- Generic Name -->
                        <div>
                            <label for="generic_id" class="form-label">Generic Name</label>
                            <select id="generic_id"
                                name="generic_id"
                                class="select-input">
                                <option value="">Select Generic (Optional)</option>
                                <?php 
                                mysqli_data_seek($generics, 0);
                                while ($generic = mysqli_fetch_assoc($generics)): ?>
                                    <option value="<?php echo $generic['id']; ?>"
                                        <?php echo $form_data['generic_id'] == $generic['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($generic['name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                            <p class="text-gray-500 text-xs mt-2">
                                Select the generic name if available (optional)
                            </p>
                        </div>

                        <!-- Description -->
                        <div>
                            <label for="description" class="form-label">Description</label>
                            <textarea id="description"
                                name="description"
                                rows="6"
                                class="form-input"
                                placeholder="Enter medicine description (usage, side effects, precautions, storage instructions, etc.)"><?php echo htmlspecialchars($form_data['description']); ?></textarea>
                            <p class="text-gray-500 text-xs mt-2">
                                Include important information about the medicine
                            </p>
                        </div>

                        <!-- Form Actions -->
                        <div class="flex flex-col md:flex-row gap-4 pt-8 border-t border-gray-200">
                            <button type="submit"
                                class="gradient-primary text-white px-8 py-4 rounded-xl font-semibold hover:shadow-lg transition-all duration-200 flex items-center justify-center gap-2 shadow-md flex-1">
                                <i class="fas fa-save"></i>
                                <span>Save Changes</span>
                            </button>

                            <a href="medicines.php"
                                class="px-8 py-4 border-2 border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 transition font-medium flex items-center justify-center gap-2 flex-1">
                                <i class="fas fa-times"></i>
                                <span>Cancel</span>
                            </a>

                            <a href="delete_medicine.php?id=<?php echo $medicine['id']; ?>"
                                onclick="return confirm('Are you sure you want to delete this medicine? This action cannot be undone.')"
                                class="gradient-danger text-white px-8 py-4 rounded-xl font-semibold hover:shadow-lg transition-all duration-200 flex items-center justify-center gap-2 shadow-md flex-1">
                                <i class="fas fa-trash"></i>
                                <span>Delete Medicine</span>
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Additional Information -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Created/Updated Info -->
                <div class="glass-card p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">
                        <i class="fas fa-info-circle text-blue-500 mr-2"></i>
                        System Information
                    </h3>
                    <div class="space-y-4">
                        <div class="flex justify-between items-center py-3 border-b border-gray-100">
                            <span class="text-gray-600">Created On</span>
                            <span class="font-medium text-gray-900">
                                <?php echo date('F j, Y', strtotime($medicine['created_at'])); ?>
                            </span>
                        </div>
                        <div class="flex justify-between items-center py-3 border-b border-gray-100">
                            <span class="text-gray-600">Created Time</span>
                            <span class="font-medium text-gray-900">
                                <?php echo date('h:i A', strtotime($medicine['created_at'])); ?>
                            </span>
                        </div>
                        <div class="flex justify-between items-center py-3">
                            <span class="text-gray-600">Last Updated</span>
                            <span class="font-medium text-gray-900">
                                <?php 
                                if (!empty($medicine['updated_at']) && $medicine['updated_at'] != '0000-00-00 00:00:00') {
                                    echo date('F j, Y h:i A', strtotime($medicine['updated_at']));
                                } else {
                                    echo 'Never updated';
                                }
                                ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="glass-card p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">
                        <i class="fas fa-bolt text-yellow-500 mr-2"></i>
                        Quick Actions
                    </h3>
                    <div class="grid grid-cols-2 gap-4">
                        <a href="stock.php?medicine_id=<?php echo $medicine['id']; ?>"
                            class="bg-blue-50 hover:bg-blue-100 border border-blue-200 rounded-xl p-4 text-center transition">
                            <div class="w-10 h-10 rounded-lg bg-blue-100 flex items-center justify-center mx-auto mb-2">
                                <i class="fas fa-boxes text-blue-600"></i>
                            </div>
                            <div class="font-medium text-blue-700">Manage Stock</div>
                            <div class="text-xs text-blue-500 mt-1">Add/Update stock</div>
                        </a>
                        
                        <a href="view_medicine.php?id=<?php echo $medicine['id']; ?>"
                            class="bg-green-50 hover:bg-green-100 border border-green-200 rounded-xl p-4 text-center transition">
                            <div class="w-10 h-10 rounded-lg bg-green-100 flex items-center justify-center mx-auto mb-2">
                                <i class="fas fa-eye text-green-600"></i>
                            </div>
                            <div class="font-medium text-green-700">View Details</div>
                            <div class="text-xs text-green-500 mt-1">Complete information</div>
                        </a>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Footer -->
    <?php include "../includes/footer.php"; ?>

    <script>
        // Auto-focus on medicine name input
        document.addEventListener('DOMContentLoaded', function() {
            const nameInput = document.getElementById('name');
            if (nameInput) {
                nameInput.focus();
                nameInput.select();
            }
        });

        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const name = document.getElementById('name').value.trim();
            const category = document.getElementById('category_id').value;
            const type = document.getElementById('type_id').value;
            
            let errors = [];
            
            if (!name) {
                errors.push('Medicine name is required');
                document.getElementById('name').classList.add('border-red-500');
            } else {
                document.getElementById('name').classList.remove('border-red-500');
            }
            
            if (!category) {
                errors.push('Category is required');
                document.getElementById('category_id').classList.add('border-red-500');
            } else {
                document.getElementById('category_id').classList.remove('border-red-500');
            }
            
            if (!type) {
                errors.push('Type is required');
                document.getElementById('type_id').classList.add('border-red-500');
            } else {
                document.getElementById('type_id').classList.remove('border-red-500');
            }
            
            if (errors.length > 0) {
                e.preventDefault();
                alert('Please fix the following errors:\n\n' + errors.join('\n'));
            }
        });

        // Delete confirmation with medicine name
        document.querySelectorAll('a[href*="delete_medicine.php"]').forEach(link => {
            link.addEventListener('click', function(e) {
                const medicineName = '<?php echo addslashes($medicine['name']); ?>';
                if (!confirm(`Are you sure you want to delete "${medicineName}"?\n\nThis will also delete all associated stock records.\nThis action cannot be undone.`)) {
                    e.preventDefault();
                }
            });
        });

        // Show character count for description
        const descriptionTextarea = document.getElementById('description');
        if (descriptionTextarea) {
            const charCount = document.createElement('div');
            charCount.className = 'text-xs text-gray-500 mt-1 text-right';
            charCount.innerHTML = `Characters: <span id="charCount">${descriptionTextarea.value.length}</span> / 2000`;
            descriptionTextarea.parentNode.insertBefore(charCount, descriptionTextarea.nextSibling);
            
            descriptionTextarea.addEventListener('input', function() {
                document.getElementById('charCount').textContent = this.value.length;
            });
        }
    </script>
</body>

</html>